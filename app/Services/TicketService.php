<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use InvalidArgumentException;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

class TicketService
{
    public function __construct(
        private PDO $connection,
        private LoggerService $logger,
        private SettingService $settings,
        private EvolutionService $evolution
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenQueue(?DateTimeImmutable $openedDate = null): array
    {
        $sql = 'SELECT t.*, c.display_name AS contact_name FROM tickets t'
            . ' INNER JOIN contacts c ON c.id = t.contact_id'
            . ' WHERE t.status = :status';

        $params = ['status' => Ticket::STATUS_OPEN];
        if ($openedDate !== null) {
            $sql .= ' AND DATE(t.opened_at) = :opened_date';
            $params['opened_date'] = $openedDate->format('Y-m-d');
        }

        $sql .= ' ORDER BY t.opened_at ASC';

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');

        return array_map(static function (array $row) use ($today): array {
            $row['opened_today'] = false;

            $openedAt = $row['opened_at'] ?? null;
            if (is_string($openedAt) && $openedAt !== '') {
                try {
                    $opened = new DateTimeImmutable($openedAt);
                    $row['opened_today'] = $opened->format('Y-m-d') === $today;
                } catch (Throwable $exception) {
                    // Ignore parse failures and keep opened_today as false.
                }
            }

            return $row;
        }, $rows);
    }

    /**
     * @return array{enabled:bool,chats:array<int, array<string, mixed>>,error:?string}
     */
    public function listNativeChats(): array
    {
        $enabled = $this->evolution->isNativeEnabled();
        $result = [
            'enabled' => $enabled,
            'chats' => [],
            'error' => null,
        ];

        if (!$enabled) {
            return $result;
        }

        $overview = $this->evolution->fetchChatsOverview();
        if (!($overview['success'] ?? false)) {
            $result['error'] = $overview['error'] ?? 'Falha ao consultar Evolution API.';
            return $result;
        }

        $activeContacts = $this->fetchActiveExternalIds();
        $chats = [];
        foreach ($overview['chats'] ?? [] as $chat) {
            if (!is_array($chat)) {
                continue;
            }

            $remoteJid = (string) ($chat['id'] ?? '');
            if ($remoteJid === '') {
                continue;
            }

            if (isset($activeContacts[$remoteJid])) {
                continue;
            }

            $unread = (int) ($chat['unread'] ?? 0);
            if ($unread <= 0) {
                continue;
            }

            $chats[] = $chat;
        }

        $result['chats'] = array_values($chats);

        return $result;
    }

    public function startNativeConversation(string $remoteJid, ?string $contactName, int $userId): int
    {
        $remoteJid = trim($remoteJid);
        if ($remoteJid === '') {
            throw new InvalidArgumentException('Identificador do contato é obrigatório.');
        }

        $integration = $this->settings->integrationSettings();
        if (($integration['integration_mode'] ?? 'webhook') !== 'native' || !$this->evolution->isNativeEnabled()) {
            throw new RuntimeException('Integração nativa não está habilitada.');
        }

        $normalizedName = $this->normalizeContactName($contactName);
        $channel = 'whatsapp';

        $this->connection->beginTransaction();
        try {
            $contactId = $this->findOrCreateContactByExternalId($remoteJid, $normalizedName);
            $ticketId = $this->findActiveTicketForContact($contactId);
            if ($ticketId === null) {
                $ticketId = $this->createTicketForContact($contactId, $channel);
            }
            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $this->assignToUser($ticketId, $userId);

        return $ticketId;
    }

    public function assignToUser(int $ticketId, int $userId): void
    {
        $this->connection->prepare(
            'INSERT INTO ticket_metrics (ticket_id) VALUES (:ticket_id) '
            . 'ON DUPLICATE KEY UPDATE ticket_id = ticket_id'
        )->execute(['ticket_id' => $ticketId]);

        $stmt = $this->connection->prepare(
            'UPDATE tickets SET status = :status, assigned_user_id = :user_id WHERE id = :id'
        );

        $stmt->execute([
            'status' => Ticket::STATUS_ASSIGNED,
            'user_id' => $userId,
            'id' => $ticketId,
        ]);

        $this->logger->info('ticket.assigned', [
            'ticket_id' => $ticketId,
            'assigned_user_id' => $userId,
            'message' => 'Chamado atribuído ao atendente.',
        ]);

        $integration = $this->settings->integrationSettings();
        if (($integration['integration_mode'] ?? 'webhook') === 'native') {
            $contactExternalId = $this->getContactExternalId($ticketId);
            if ($contactExternalId) {
                $this->evolution->sendDefaultTemplate($contactExternalId);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getTicketWithMessages(int $ticketId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT t.*, c.display_name AS contact_name, c.external_id AS contact_external_id, au.full_name AS agent_name
             FROM tickets t
             INNER JOIN contacts c ON c.id = t.contact_id
             LEFT JOIN users au ON au.id = t.assigned_user_id
             WHERE t.id = :id'
        );
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            throw new \RuntimeException('Ticket not found.');
        }

        $stmtMessages = $this->connection->prepare(
            'SELECT m.*, u.full_name AS agent_name
             FROM messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.ticket_id = :id
             ORDER BY m.sent_at ASC'
        );
        $stmtMessages->execute(['id' => $ticketId]);
        $dbMessages = $stmtMessages->fetchAll(PDO::FETCH_ASSOC);
        $ticket['messages'] = $this->composeTicketMessages($ticket, $dbMessages);

        return $ticket;
    }

    /**
     * @param array{path:string,url:string,mime:string,type:string,name:string}|null $attachment
     */
    public function appendAgentMessage(int $ticketId, int $userId, string $body, ?array $attachment = null): void
    {
        $body = trim($body);
        $mediaType = $this->normalizeMediaType($attachment['type'] ?? 'text');
        $mediaUrl = $attachment['url'] ?? null;
        $metadata = null;

        if ($attachment !== null) {
            $metadata = [
                'source' => 'agent_upload',
                'filename' => $attachment['name'] ?? null,
                'mime_type' => $attachment['mime'] ?? null,
            ];
        }

        $integration = $this->settings->integrationSettings();
        if (($integration['integration_mode'] ?? 'webhook') === 'native') {
            $contactExternalId = $this->getContactExternalId($ticketId);
            if ($contactExternalId === null) {
                $this->logger->error('ticket.native_contact_missing', [
                    'ticket_id' => $ticketId,
                    'user_id' => $userId,
                ]);

                throw new RuntimeException('Contato não possui identificador externo para envio via Evolution.');
            }

            if ($attachment !== null) {
                $sendResult = $this->evolution->sendMedia($contactExternalId, $attachment['path'], [
                    'caption' => $body,
                    'filename' => $attachment['name'] ?? null,
                    'mime_type' => $attachment['mime'] ?? null,
                    'type' => $mediaType === 'file' ? 'document' : $mediaType,
                ]);
            } else {
                $sendResult = $this->evolution->sendText($contactExternalId, $body);
            }

            if (!$sendResult['success']) {
                $this->logger->error('ticket.native_send_failed', [
                    'ticket_id' => $ticketId,
                    'user_id' => $userId,
                    'contact' => $contactExternalId,
                    'error' => $sendResult['error'],
                    'media_type' => $mediaType,
                ]);

                throw new RuntimeException($sendResult['error'] ?? 'Falha ao enviar mensagem via Evolution.');
            }
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->connection->prepare(
            'INSERT INTO messages (ticket_id, sender_type, user_id, body, media_type, media_url, metadata, sent_at) '
            . 'VALUES (:ticket_id, :sender_type, :user_id, :body, :media_type, :media_url, :metadata, :sent_at)'
        );
        $stmt->execute([
            'ticket_id' => $ticketId,
            'sender_type' => 'agent',
            'user_id' => $userId,
            'body' => $body !== '' ? $body : null,
            'media_type' => $mediaType,
            'media_url' => $mediaUrl,
            'metadata' => $metadata !== null ? (json_encode($metadata, JSON_UNESCAPED_UNICODE) ?: null) : null,
            'sent_at' => $now,
        ]);

        $this->connection->prepare(
            'UPDATE ticket_metrics SET last_response_at = :now, first_response_at = COALESCE(first_response_at, :now) WHERE ticket_id = :ticket_id'
        )->execute([
            'now' => $now,
            'ticket_id' => $ticketId,
        ]);

        $this->logger->info('ticket.agent_response', [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'media_type' => $mediaType,
            'message' => 'Mensagem do atendente registrada.',
        ]);
    }

    public function resolveTicket(int $ticketId): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmtTicket = $this->connection->prepare('SELECT opened_at FROM tickets WHERE id = :id');
        $stmtTicket->execute(['id' => $ticketId]);
        $openedAt = $stmtTicket->fetchColumn();

        $stmt = $this->connection->prepare(
            'UPDATE tickets SET status = :status, closed_at = :closed_at WHERE id = :id'
        );
        $stmt->execute([
            'status' => Ticket::STATUS_RESOLVED,
            'closed_at' => $now,
            'id' => $ticketId,
        ]);

        $this->connection->prepare(
            'UPDATE ticket_metrics SET resolution_time_seconds = TIMESTAMPDIFF(SECOND, :opened_at, :closed_at)
             WHERE ticket_id = :ticket_id'
        )->execute([
            'opened_at' => $openedAt,
            'closed_at' => $now,
            'ticket_id' => $ticketId,
        ]);

        $this->logger->info('ticket.resolved', ['ticket_id' => $ticketId]);

        $this->sendClosureSurvey($ticketId);
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<int, array<string, mixed>> $dbMessages
     * @return array<int, array<string, mixed>>
     */
    private function composeTicketMessages(array $ticket, array $dbMessages): array
    {
        $normalizedDb = $this->normalizeDatabaseMessages($dbMessages);

        $integration = $this->settings->integrationSettings();
        $isNative = ($integration['integration_mode'] ?? 'webhook') === 'native' && $this->evolution->isNativeEnabled();
        $remoteJid = trim((string) ($ticket['contact_external_id'] ?? ''));

        if (!$isNative || $remoteJid === '') {
            return $normalizedDb;
        }

        $native = $this->evolution->fetchConversationMessages($remoteJid);
        if (!($native['success'] ?? false)) {
            $this->logger->error('evolution.ticket_messages_failed', [
                'ticket_id' => $ticket['id'] ?? null,
                'remote_jid' => $remoteJid,
                'error' => $native['error'] ?? 'Falha desconhecida ao sincronizar mensagens nativas.',
            ]);

            return $normalizedDb;
        }

        $normalizedNative = $this->normalizeNativeMessages($ticket, $native['messages'] ?? []);
        $normalizedNative = $this->filterNativeMessagesByTicket($ticket, $normalizedNative);

        if ($normalizedNative !== []) {
            try {
                $this->persistNativeMessages(
                    (int) ($ticket['id'] ?? 0),
                    isset($ticket['contact_id']) ? (int) $ticket['contact_id'] : null,
                    $normalizedNative
                );
            } catch (Throwable $exception) {
                $this->logger->error('ticket.native_history_persist_failed', [
                    'ticket_id' => $ticket['id'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($normalizedNative === []) {
            return $normalizedDb;
        }

        $existing = [];
        foreach ($normalizedNative as $message) {
            $existing[$this->messageSignature($message)] = true;
        }

        foreach ($normalizedDb as $message) {
            $signature = $this->messageSignature($message);
            if (!isset($existing[$signature])) {
                $normalizedNative[] = $message;
            }
        }

        usort($normalizedNative, static function (array $a, array $b): int {
            $aTime = $a['sent_at'] ?? '';
            $bTime = $b['sent_at'] ?? '';

            if ($aTime === $bTime) {
                return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
            }

            return strcmp((string) $aTime, (string) $bTime);
        });

        return $normalizedNative;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDatabaseMessages(array $messages): array
    {
        $normalized = array_map(static function (array $message): array {
            return [
                'id' => $message['id'] ?? null,
                'ticket_id' => $message['ticket_id'] ?? null,
                'sender_type' => $message['sender_type'] ?? 'contact',
                'user_id' => $message['user_id'] ?? null,
                'body' => (string) ($message['body'] ?? ''),
                'media_type' => $this->normalizeMediaType($message['media_type'] ?? 'text'),
                'media_url' => isset($message['media_url']) && $message['media_url'] !== null
                    ? (string) $message['media_url']
                    : null,
                'sent_at' => isset($message['sent_at']) ? (string) $message['sent_at'] : null,
                'agent_name' => $message['agent_name'] ?? null,
                'status' => $message['status'] ?? null,
            ];
        }, $messages);

        usort($normalized, static function (array $a, array $b): int {
            $aTime = $a['sent_at'] ?? '';
            $bTime = $b['sent_at'] ?? '';

            if ($aTime === $bTime) {
                return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
            }

            return strcmp((string) $aTime, (string) $bTime);
        });

        return $normalized;
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNativeMessages(array $ticket, array $messages): array
    {
        $ticketId = $ticket['id'] ?? null;
        $assignedUserId = $ticket['assigned_user_id'] ?? null;
        $agentName = $ticket['agent_name'] ?? null;

        $normalized = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $id = isset($message['id']) ? (string) $message['id'] : '';
            if ($id === '') {
                continue;
            }

            $sentAt = $message['sent_at'] ?? null;
            if (!is_string($sentAt) || $sentAt === '') {
                continue;
            }

            $fromMe = (bool) ($message['from_me'] ?? false);

            $normalized[] = [
                'id' => $id,
                'ticket_id' => $ticketId,
                'sender_type' => $fromMe ? 'agent' : 'contact',
                'user_id' => $fromMe ? $assignedUserId : null,
                'body' => (string) ($message['body'] ?? ''),
                'media_type' => $this->normalizeMediaType($message['media_type'] ?? 'text'),
                'media_url' => $message['media_url'] ?? null,
                'sent_at' => $sentAt,
                'agent_name' => $fromMe ? ($agentName ?: 'Você') : null,
                'status' => $message['status'] ?? null,
                'raw' => $message['raw'] ?? null,
                'from_me' => $fromMe,
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            $aTime = $a['sent_at'] ?? '';
            $bTime = $b['sent_at'] ?? '';

            if ($aTime === $bTime) {
                return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
            }

            return strcmp((string) $aTime, (string) $bTime);
        });

        return $normalized;
    }

    /**
     * @param array<string, mixed> $ticket
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function filterNativeMessagesByTicket(array $ticket, array $messages): array
    {
        $openedAt = $ticket['opened_at'] ?? null;
        if (!is_string($openedAt) || $openedAt === '') {
            return $messages;
        }

        try {
            $opened = new DateTimeImmutable($openedAt);
        } catch (Throwable $exception) {
            $this->logger->warning('ticket.native_history_filter_failed', [
                'ticket_id' => $ticket['id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return $messages;
        }

        return array_values(array_filter($messages, static function (array $message) use ($opened): bool {
            $sentAt = $message['sent_at'] ?? null;
            if (!is_string($sentAt) || $sentAt === '') {
                return false;
            }

            try {
                $sent = new DateTimeImmutable($sentAt);
            } catch (Throwable $exception) {
                return false;
            }

            return $sent >= $opened;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     */
    private function persistNativeMessages(int $ticketId, ?int $contactId, array $messages): void
    {
        if ($ticketId <= 0 || $messages === []) {
            return;
        }

        $select = $this->connection->prepare(
            'SELECT id FROM messages WHERE ticket_id = :ticket_id AND metadata IS NOT NULL'
            . ' AND JSON_EXTRACT(metadata, "$.remote_id") = :remote_id LIMIT 1'
        );
        $insert = $this->connection->prepare(
            'INSERT INTO messages (ticket_id, sender_type, user_id, body, media_type, media_url, metadata, sent_at) '
            . 'VALUES (:ticket_id, :sender_type, NULL, :body, :media_type, :media_url, :metadata, :sent_at)'
        );

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $updatedContact = false;
        $latestSentAt = null;

        foreach ($messages as $message) {
            if (!empty($message['from_me'])) {
                continue;
            }

            $remoteId = $message['id'] ?? null;
            if (!is_string($remoteId) || $remoteId === '') {
                continue;
            }

            $sentAt = $message['sent_at'] ?? null;
            if (!is_string($sentAt) || $sentAt === '') {
                continue;
            }

            $select->execute([
                'ticket_id' => $ticketId,
                'remote_id' => $remoteId,
            ]);

            if ($select->fetchColumn()) {
                continue;
            }

            $metadata = json_encode([
                'remote_id' => $remoteId,
                'status' => $message['status'] ?? null,
                'source' => 'evolution_native',
            ], JSON_UNESCAPED_UNICODE) ?: null;

            $insert->execute([
                'ticket_id' => $ticketId,
                'sender_type' => 'contact',
                'body' => isset($message['body']) && $message['body'] !== '' ? (string) $message['body'] : null,
                'media_type' => $this->normalizeMediaType($message['media_type'] ?? 'text'),
                'media_url' => $message['media_url'] ?? null,
                'metadata' => $metadata,
                'sent_at' => $sentAt,
            ]);

            $updatedContact = true;
            if ($latestSentAt === null || strcmp($sentAt, $latestSentAt) > 0) {
                $latestSentAt = $sentAt;
            }
        }

        if ($updatedContact && $contactId !== null) {
            $this->connection->prepare('UPDATE contacts SET last_interaction_at = :now WHERE id = :id')->execute([
                'now' => $latestSentAt ?? $now,
                'id' => $contactId,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function messageSignature(array $message): string
    {
        $parts = [
            (string) ($message['id'] ?? ''),
            (string) ($message['sent_at'] ?? ''),
            (string) ($message['sender_type'] ?? ''),
            (string) ($message['body'] ?? ''),
            (string) ($message['media_type'] ?? ''),
            (string) ($message['media_url'] ?? ''),
        ];

        return md5(implode('|', $parts));
    }

    private function sendClosureSurvey(int $ticketId): void
    {
        $integration = $this->settings->integrationSettings();
        if (($integration['integration_mode'] ?? 'webhook') !== 'native' || !$this->evolution->isNativeEnabled()) {
            return;
        }

        $contactExternalId = $this->getContactExternalId($ticketId);
        if ($contactExternalId === null) {
            return;
        }

        $configured = $this->settings->get('ticket_closure_message');
        $message = trim((string) ($configured ?? ''));
        if ($message === '') {
            $message = 'Agradecemos seu contato! Conte com a gente sempre que precisar. Avalie nosso atendimento respondendo com uma nota de 1 a 5.';
        }

        try {
            $result = $this->evolution->sendText($contactExternalId, $message);
            if (!$result['success']) {
                $this->logger->warning('ticket.closure_message_failed', [
                    'ticket_id' => $ticketId,
                    'contact' => $contactExternalId,
                    'error' => $result['error'] ?? 'Falha ao enviar mensagem de encerramento.',
                ]);
            }
            if ($result['success']) {
                $this->logger->info('ticket.closure_message_sent', [
                    'ticket_id' => $ticketId,
                    'contact' => $contactExternalId,
                ]);
            }

            $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $metadata = [
                'automation' => 'ticket_closure',
                'success' => $result['success'],
            ];
            if (!$result['success'] && isset($result['error'])) {
                $metadata['error'] = $result['error'];
            }

            $this->connection->prepare(
                'INSERT INTO messages (ticket_id, sender_type, user_id, body, media_type, media_url, metadata, sent_at) '
                . 'VALUES (:ticket_id, :sender_type, NULL, :body, :media_type, NULL, :metadata, :sent_at)'
            )->execute([
                'ticket_id' => $ticketId,
                'sender_type' => 'system',
                'body' => $message,
                'media_type' => 'text',
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE) ?: null,
                'sent_at' => $now,
            ]);
        } catch (Throwable $exception) {
            $this->logger->error('ticket.closure_message_store_failed', [
                'ticket_id' => $ticketId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeMediaType(?string $type): string
    {
        $normalized = strtolower((string) $type);

        return match ($normalized) {
            'image', 'photo', 'sticker' => 'image',
            'audio', 'ptt', 'voice' => 'audio',
            'video' => 'video',
            'file', 'document', 'application', 'doc', 'pdf' => 'file',
            default => 'text',
        };
    }

    /**
     * @return array<string, bool>
     */
    private function fetchActiveExternalIds(): array
    {
        $stmt = $this->connection->prepare(
            'SELECT DISTINCT c.external_id'
            . ' FROM tickets t'
            . ' INNER JOIN contacts c ON c.id = t.contact_id'
            . ' WHERE t.status IN (:status_open, :status_assigned)'
        );
        $stmt->execute([
            'status_open' => Ticket::STATUS_OPEN,
            'status_assigned' => Ticket::STATUS_ASSIGNED,
        ]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $externalId) {
            if (is_string($externalId) && $externalId !== '') {
                $map[$externalId] = true;
            }
        }

        return $map;
    }

    private function findOrCreateContactByExternalId(string $externalId, ?string $displayName): int
    {
        $stmt = $this->connection->prepare('SELECT id, display_name FROM contacts WHERE external_id = :external_id');
        $stmt->execute(['external_id' => $externalId]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        if ($contact) {
            $updates = ['last_interaction_at' => $now, 'id' => (int) $contact['id']];
            $set = 'last_interaction_at = :last_interaction_at';

            $currentName = trim((string) ($contact['display_name'] ?? ''));
            if ($displayName !== null && $currentName === '') {
                $updates['display_name'] = $displayName;
                $set .= ', display_name = :display_name';
            }

            $updateStmt = $this->connection->prepare('UPDATE contacts SET ' . $set . ' WHERE id = :id');
            $updateStmt->execute($updates);

            return (int) $contact['id'];
        }

        $stmtInsert = $this->connection->prepare(
            'INSERT INTO contacts (external_id, display_name, last_interaction_at)'
            . ' VALUES (:external_id, :display_name, :last_interaction_at)'
        );
        $stmtInsert->execute([
            'external_id' => $externalId,
            'display_name' => $displayName,
            'last_interaction_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function findActiveTicketForContact(int $contactId): ?int
    {
        $stmt = $this->connection->prepare(
            'SELECT id FROM tickets'
            . ' WHERE contact_id = :contact_id'
            . ' AND status IN (:status_open, :status_assigned)'
            . ' ORDER BY opened_at DESC LIMIT 1'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'status_open' => Ticket::STATUS_OPEN,
            'status_assigned' => Ticket::STATUS_ASSIGNED,
        ]);

        $ticketId = $stmt->fetchColumn();

        return $ticketId !== false ? (int) $ticketId : null;
    }

    private function createTicketForContact(int $contactId, string $channel): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO tickets (contact_id, status, priority, channel)'
            . ' VALUES (:contact_id, :status, :priority, :channel)'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_NORMAL,
            'channel' => $channel,
        ]);

        $ticketId = (int) $this->connection->lastInsertId();

        $this->connection->prepare(
            'INSERT INTO ticket_metrics (ticket_id, first_response_at) VALUES (:ticket_id, NULL)'
        )->execute(['ticket_id' => $ticketId]);

        $this->logger->info('ticket.created_from_native', [
            'ticket_id' => $ticketId,
            'contact_id' => $contactId,
            'message' => 'Ticket criado a partir da fila nativa.',
        ]);

        return $ticketId;
    }

    private function normalizeContactName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 150);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessageTemplates(): array
    {
        $stmt = $this->connection->query(
            'SELECT id, title, body, category FROM templates ORDER BY title ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTickets(?string $status = null): array
    {
        $sql = 'SELECT t.id, t.status, t.priority, t.opened_at, t.closed_at, t.channel, '
            . 'c.display_name AS contact_name, u.full_name AS agent_name '
            . 'FROM tickets t '
            . 'INNER JOIN contacts c ON c.id = t.contact_id '
            . 'LEFT JOIN users u ON u.id = t.assigned_user_id';

        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE t.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY t.opened_at DESC';

        $stmt = $this->connection->prepare($sql);
        if (isset($params['status'])) {
            $stmt->bindValue(':status', $params['status']);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getContactExternalId(int $ticketId): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT c.external_id FROM tickets t INNER JOIN contacts c ON c.id = t.contact_id WHERE t.id = :id'
        );
        $stmt->execute(['id' => $ticketId]);
        $externalId = $stmt->fetchColumn();

        return $externalId !== false ? (string) $externalId : null;
    }
}
