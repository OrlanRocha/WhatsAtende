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
        private EvolutionService $evolution,
        private GroupService $groups
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function seriousnessOptions(): array
    {
        return [
            Ticket::SERIOUSNESS_INFORMATION => 'Informação',
            Ticket::SERIOUSNESS_LOW => 'Baixa',
            Ticket::SERIOUSNESS_MEDIUM => 'Média',
            Ticket::SERIOUSNESS_HIGH => 'Alta',
            Ticket::SERIOUSNESS_CRITICAL => 'Crítica',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listGroups(): array
    {
        return $this->groups->listGroups();
    }

    /**
     * @return array<int, int>
     */
    public function getUserGroupIds(int $userId): array
    {
        return $this->groups->getUserGroupIds($userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenQueue(?DateTimeImmutable $openedDate = null, ?int $userId = null): array
    {
        $sql = 'SELECT t.*, c.display_name AS contact_name, sg.name AS group_name, sg.slug AS group_slug '
            . 'FROM tickets t'
            . ' INNER JOIN contacts c ON c.id = t.contact_id'
            . ' LEFT JOIN support_groups sg ON sg.id = t.group_id'
            . ' WHERE t.status = :status';

        $params = ['status' => Ticket::STATUS_OPEN];

        if ($userId !== null) {
            $groupIds = $this->groups->getUserGroupIds($userId);
            if ($groupIds === []) {
                $default = $this->groups->defaultGroupId();
                if ($default !== null) {
                    $groupIds = [$default];
                }
            }

            if (!empty($groupIds)) {
                $placeholders = [];
                foreach ($groupIds as $index => $groupId) {
                    $key = 'group_' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $groupId;
                }
                $sql .= ' AND t.group_id IN (' . implode(',', $placeholders) . ')';
            }
        }

        if ($openedDate !== null) {
            $sql .= ' AND DATE(t.opened_at) = :opened_date';
            $params['opened_date'] = $openedDate->format('Y-m-d');
        }

        $sql .= ' ORDER BY t.opened_at ASC';

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $param = ':' . $key;
            if (str_starts_with($key, 'group_')) {
                $stmt->bindValue($param, (int) $value, PDO::PARAM_INT);
                continue;
            }
            $stmt->bindValue($param, $value);
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

            $row['group_name'] = $row['group_name'] ?? null;
            $row['group_slug'] = $row['group_slug'] ?? null;

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

    public function startNativeConversation(string $remoteJid, ?string $contactName, int $userId, ?string $lastMessageId = null): int
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

        $this->assignToUser($ticketId, $userId, $lastMessageId);

        return $ticketId;
    }

    public function assignToUser(int $ticketId, int $userId, ?string $lastMessageId = null): void
    {
        $ticketSnapshotStmt = $this->connection->prepare(
            'SELECT opened_at, sla_due_at FROM tickets WHERE id = :id'
        );
        $ticketSnapshotStmt->execute(['id' => $ticketId]);
        $ticketSnapshot = $ticketSnapshotStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $this->connection->prepare(
            'INSERT INTO ticket_metrics (ticket_id) VALUES (:ticket_id) ' .
            'ON DUPLICATE KEY UPDATE ticket_id = ticket_id'
        )->execute(['ticket_id' => $ticketId]);

        $this->connection->prepare(
            'UPDATE tickets SET status = :status, assigned_user_id = :user_id WHERE id = :id'
        )->execute([
            'status' => Ticket::STATUS_ASSIGNED,
            'user_id' => $userId,
            'id' => $ticketId,
        ]);

        $metricFragments = ['last_touch_at = NOW()'];
        $metricParams = ['ticket_id' => $ticketId];
        if (!empty($ticketSnapshot['opened_at'])) {
            $metricFragments[] = 'queue_time_sec = COALESCE(queue_time_sec, TIMESTAMPDIFF(SECOND, :opened_at, NOW()))';
            $metricParams['opened_at'] = $ticketSnapshot['opened_at'];
        }
        if (!empty($ticketSnapshot['sla_due_at'])) {
            $metricFragments[] = 'sla_due_at = COALESCE(sla_due_at, :sla_due_at)';
            $metricParams['sla_due_at'] = $ticketSnapshot['sla_due_at'];
            $status = $this->calculateSlaStatus((string) $ticketSnapshot['sla_due_at'], new DateTimeImmutable());
            if ($status !== null) {
                $metricFragments[] = 'sla_status = :sla_status';
                $metricParams['sla_status'] = $status;
            }
        }
        $metricSql = 'UPDATE ticket_metrics SET ' . implode(', ', $metricFragments) . ' WHERE ticket_id = :ticket_id';
        $metricStmt = $this->connection->prepare($metricSql);
        $metricStmt->execute($metricParams);

        $this->logger->info('ticket.assigned', [
            'ticket_id' => $ticketId,
            'assigned_user_id' => $userId,
            'message' => 'Chamado atribuído ao atendente.',
        ]);

        $integration = $this->settings->integrationSettings();
        if (($integration['integration_mode'] ?? 'webhook') === 'native') {
            $contactExternalId = $this->getContactExternalId($ticketId);
            if ($contactExternalId) {
                $this->acknowledgeNativeConversation($contactExternalId, $lastMessageId);
                $this->evolution->sendDefaultTemplate($contactExternalId);
            }
        }
    }

    public function acknowledgeNativeConversation(string $remoteJid, ?string $messageId = null): void
    {
        $remoteJid = trim($remoteJid);
        if ($remoteJid === '') {
            return;
        }

        $integration = $this->settings->integrationSettings();
        if (($integration['integration_mode'] ?? 'webhook') !== 'native' || !$this->evolution->isNativeEnabled()) {
            return;
        }

        $targetMessageId = null;

        if ($messageId !== null) {
            $messageId = trim($messageId);
            if ($messageId !== '') {
                $targetMessageId = $messageId;
            }
        }

        $messages = [];
        if ($targetMessageId === null) {
            $history = $this->evolution->fetchConversationMessages($remoteJid, 50);
            if (!($history['success'] ?? false)) {
                $this->logger->warning('ticket.native_mark_read_failed', [
                    'remote_jid' => $remoteJid,
                    'error' => $history['error'] ?? 'Falha ao buscar histórico da conversa para marcar como lida.',
                ]);

                return;
            }

            $messages = is_array($history['messages'] ?? null) ? $history['messages'] : [];
        }

        if ($targetMessageId === null) {
            foreach (array_reverse($messages) as $message) {
                if (!is_array($message)) {
                    continue;
                }

                $fromMe = (bool) ($message['from_me'] ?? false);
                if ($fromMe) {
                    continue;
                }

                $status = strtoupper((string) ($message['status'] ?? ''));
                if ($status === 'READ') {
                    continue;
                }

                $candidate = $message['id'] ?? null;
                if (is_string($candidate) && $candidate !== '') {
                    $targetMessageId = $candidate;
                    break;
                }
            }
        }

        if ($targetMessageId === null) {
            foreach (array_reverse($messages) as $message) {
                if (!is_array($message)) {
                    continue;
                }

                if (!($message['from_me'] ?? false)) {
                    $candidate = $message['id'] ?? null;
                    if (is_string($candidate) && $candidate !== '') {
                        $targetMessageId = $candidate;
                        break;
                    }
                }
            }
        }

        if ($targetMessageId === null) {
            return;
        }

        $result = $this->evolution->markAsRead($remoteJid, $targetMessageId);
        if (!($result['success'] ?? false)) {
            $this->logger->warning('ticket.native_mark_read_failed', [
                'remote_jid' => $remoteJid,
                'message_id' => $targetMessageId,
                'status' => $result['status'] ?? null,
                'error' => $result['error'] ?? $result['error_detail'] ?? null,
            ]);

            return;
        }

        $this->logger->info('ticket.native_marked_read', [
            'remote_jid' => $remoteJid,
            'message_id' => $targetMessageId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTicketWithMessages(int $ticketId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT t.*, c.display_name AS contact_name, c.external_id AS contact_external_id, '
            . 'au.full_name AS agent_name, sg.name AS group_name, sg.slug AS group_slug'
            . ' FROM tickets t'
            . ' INNER JOIN contacts c ON c.id = t.contact_id'
            . ' LEFT JOIN users au ON au.id = t.assigned_user_id'
            . ' LEFT JOIN support_groups sg ON sg.id = t.group_id'
            . ' WHERE t.id = :id'
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
        $contactExternalId = null;

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
            'UPDATE ticket_metrics SET last_touch_at = :now, first_response_at = COALESCE(first_response_at, :now) WHERE ticket_id = :ticket_id'
        )->execute([
            'now' => $now,
            'ticket_id' => $ticketId,
        ]);

        $this->logger->info('ticket.agent_response', [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'media_type' => $mediaType,
            'message' => 'Mensagem do atendente registrada.',
            'remote_jid' => $contactExternalId,
            'body_length' => strlen($body),
            'has_attachment' => $attachment !== null,
        ]);
    }

    public function resolveTicket(int $ticketId): void
    {
        $nowDate = new DateTimeImmutable();
        $now = $nowDate->format('Y-m-d H:i:s');
        $stmtTicket = $this->connection->prepare('SELECT opened_at, sla_due_at FROM tickets WHERE id = :id');
        $stmtTicket->execute(['id' => $ticketId]);
        $ticketRow = $stmtTicket->fetch(\PDO::FETCH_ASSOC) ?: null;

        $this->connection->prepare(
            'UPDATE tickets SET status = :status, closed_at = :closed_at WHERE id = :id'
        )->execute([
            'status' => Ticket::STATUS_RESOLVED,
            'closed_at' => $now,
            'id' => $ticketId,
        ]);

        $metricSql = 'UPDATE ticket_metrics SET last_touch_at = :closed_at';
        $metricParams = [
            'closed_at' => $now,
            'ticket_id' => $ticketId,
        ];

        if ($ticketRow && !empty($ticketRow['opened_at'])) {
            $metricSql .= ', resolution_time_sec = TIMESTAMPDIFF(SECOND, :opened_at, :closed_at)';
            $metricParams['opened_at'] = $ticketRow['opened_at'];
        }

        if ($ticketRow && !empty($ticketRow['sla_due_at'])) {
            $status = $this->calculateSlaStatus((string) $ticketRow['sla_due_at'], $nowDate);
            if ($status !== null) {
                $metricSql .= ', sla_status = :sla_status';
                $metricParams['sla_status'] = $status;
            }
        }

        $metricSql .= ' WHERE ticket_id = :ticket_id';
        $this->connection->prepare($metricSql)->execute($metricParams);

        $this->logger->info('ticket.resolved', ['ticket_id' => $ticketId]);

        $this->sendClosureSurvey($ticketId);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateTicketStatus(int $ticketId, string $status, int $actorId): array
    {
        $status = strtolower(trim($status));

        $allowed = [
            Ticket::STATUS_OPEN,
            Ticket::STATUS_ASSIGNED,
            Ticket::STATUS_RESOLVED,
            Ticket::STATUS_CLOSED,
        ];

        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Status informado não é suportado.');
        }

        $currentStmt = $this->connection->prepare('SELECT status FROM tickets WHERE id = :id');
        $currentStmt->execute(['id' => $ticketId]);
        $currentValue = $currentStmt->fetchColumn();

        if ($currentValue === false) {
            throw new RuntimeException('Ticket não encontrado.');
        }

        $currentStatus = strtolower((string) $currentValue);
        if ($currentStatus === $status) {
            return $this->fetchTicketStatus($ticketId);
        }

        if ($status === Ticket::STATUS_RESOLVED) {
            $this->resolveTicket($ticketId);
        } else {
            $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $query = 'UPDATE tickets SET status = :status';
            $params = [
                'status' => $status,
                'id' => $ticketId,
            ];

            if ($status === Ticket::STATUS_CLOSED) {
                $query .= ', closed_at = :closed_at';
                $params['closed_at'] = $now;
            } else {
                $query .= ', closed_at = NULL';
            }

            $query .= ' WHERE id = :id';
            $statement = $this->connection->prepare($query);
            $statement->execute($params);

            $this->connection->prepare(
                'UPDATE ticket_metrics SET last_touch_at = :now WHERE ticket_id = :ticket_id'
            )->execute([
                'now' => $now,
                'ticket_id' => $ticketId,
            ]);
        }

        $ticket = $this->fetchTicketStatus($ticketId);

        $this->logger->info('ticket.status_changed', [
            'ticket_id' => $ticketId,
            'status' => $ticket['status'] ?? $status,
            'user_id' => $actorId,
        ]);

        return $ticket;
    }

    /**
     * @return array<string, mixed>
     */
    public function updateTicketMeta(int $ticketId, array $data, int $actorId): array
    {
        $before = $this->fetchTicketMeta($ticketId);

        $fields = [];
        $params = ['id' => $ticketId];
        $changes = [];

        if (array_key_exists('subject', $data)) {
            $subject = $data['subject'];
            if ($subject !== null) {
                $subject = trim((string) $subject);
                if ($subject === '') {
                    $subject = null;
                } else {
                    $subject = mb_substr($subject, 0, 191);
                }
            }

            $previous = $before['subject'] ?? null;
            if ($subject !== $previous) {
                $fields[] = 'subject = :subject';
                $params['subject'] = $subject;
                $changes['subject'] = ['from' => $previous, 'to' => $subject];
            }
        }

        if (array_key_exists('seriousness', $data)) {
            $seriousness = strtolower(trim((string) $data['seriousness']));
            $allowedSeriousness = array_keys($this->seriousnessOptions());

            if ($seriousness === '') {
                $seriousness = Ticket::SERIOUSNESS_INFORMATION;
            }

            if (!in_array($seriousness, $allowedSeriousness, true)) {
                throw new InvalidArgumentException('Nível de seriedade inválido.');
            }

            $previousSeriousness = strtolower((string) ($before['seriousness'] ?? Ticket::SERIOUSNESS_INFORMATION));
            if ($seriousness !== $previousSeriousness) {
                $fields[] = 'seriousness = :seriousness';
                $params['seriousness'] = $seriousness;
                $changes['seriousness'] = ['from' => $previousSeriousness, 'to' => $seriousness];
            }
        }

        if (array_key_exists('group_id', $data)) {
            $groupId = $data['group_id'];
            if ($groupId === '' || $groupId === null) {
                $groupId = null;
            }

            if ($groupId !== null) {
                $groupId = (int) $groupId;
                if ($groupId <= 0 || !$this->groups->groupExists($groupId)) {
                    throw new InvalidArgumentException('Grupo informado é inválido.');
                }
            }

            $previousGroup = isset($before['group_id']) ? (int) $before['group_id'] : null;
            if ($groupId !== $previousGroup) {
                $fields[] = 'group_id = :group_id';
                $params['group_id'] = $groupId;
                $changes['group_id'] = ['from' => $previousGroup, 'to' => $groupId];
            }
        }

        if ($fields === []) {
            return $before;
        }

        $sql = 'UPDATE tickets SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $statement = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === 'group_id' && $value === null) {
                $statement->bindValue(':' . $key, null, PDO::PARAM_NULL);
                continue;
            }
            $statement->bindValue(':' . $key, $value);
        }
        $statement->execute();

        $this->logger->info('ticket.meta_updated', [
            'ticket_id' => $ticketId,
            'user_id' => $actorId,
            'changes' => $changes,
        ]);

        return $this->fetchTicketMeta($ticketId);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTicketStatus(int $ticketId): array
    {
        $statement = $this->connection->prepare(
            'SELECT t.id, t.status, t.closed_at, tm.sla_status, tm.sla_due_at '
            . 'FROM tickets t LEFT JOIN ticket_metrics tm ON tm.ticket_id = t.id WHERE t.id = :id'
        );
        $statement->execute(['id' => $ticketId]);
        $ticket = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            throw new RuntimeException('Ticket não encontrado.');
        }

        $ticketStatus = strtolower((string) ($ticket['status'] ?? Ticket::STATUS_OPEN));
        $labels = [
            Ticket::STATUS_OPEN => 'Aberto',
            Ticket::STATUS_ASSIGNED => 'Em atendimento',
            Ticket::STATUS_RESOLVED => 'Resolvido',
            Ticket::STATUS_CLOSED => 'Encerrado',
        ];

        $ticket['status'] = $ticketStatus;
        $ticket['label'] = $labels[$ticketStatus] ?? ucfirst($ticketStatus);

        return $ticket;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTicketMeta(int $ticketId): array
    {
        $statement = $this->connection->prepare(
            'SELECT t.id, t.subject, t.seriousness, t.group_id, sg.name AS group_name, sg.slug AS group_slug '
            . 'FROM tickets t '
            . 'LEFT JOIN support_groups sg ON sg.id = t.group_id '
            . 'WHERE t.id = :id'
        );
        $statement->execute(['id' => $ticketId]);
        $meta = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$meta) {
            throw new RuntimeException('Ticket não encontrado.');
        }

        return $meta;
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
                    $normalizedNative,
                    $remoteJid
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

        $nativeCount = count($normalizedNative);
        $databaseCount = count($normalizedDb);

        $existing = [];
        foreach ($normalizedNative as $message) {
            $existing[$this->messageSignature($message)] = true;
        }

        $duplicates = 0;
        foreach ($normalizedDb as $message) {
            $signature = $this->messageSignature($message);
            if (!isset($existing[$signature])) {
                $normalizedNative[] = $message;
            } else {
                $duplicates++;
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

        $mergedTotal = count($normalizedNative);
        $addedFromDatabase = $mergedTotal - $nativeCount;
        if ($addedFromDatabase < 0) {
            $addedFromDatabase = 0;
        }

        $this->logger->info('ticket.native_history_synced', [
            'ticket_id' => $ticket['id'] ?? null,
            'remote_jid' => $remoteJid,
            'native_messages' => $nativeCount,
            'database_messages' => $databaseCount,
            'merged_total' => $mergedTotal,
            'deduplicated' => $duplicates,
            'added_from_database' => $addedFromDatabase,
        ]);

        if ($remoteJid !== '') {
            $lastUnreadRemoteId = null;
            foreach (array_reverse($normalizedNative) as $message) {
                if (($message['sender_type'] ?? '') !== 'contact') {
                    continue;
                }

                $status = strtoupper((string) ($message['status'] ?? ''));
                if ($status === 'READ') {
                    continue;
                }

                $candidate = $message['remote_id'] ?? $message['id'] ?? null;
                if (is_string($candidate) && $candidate !== '') {
                    $lastUnreadRemoteId = $candidate;
                    break;
                }
            }

            if ($lastUnreadRemoteId !== null) {
                $this->acknowledgeNativeConversation($remoteJid, $lastUnreadRemoteId);
            }
        }

        return $normalizedNative;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDatabaseMessages(array $messages): array
    {
        $normalized = array_map(function (array $message): array {
            $remoteId = null;
            $metadata = $message['metadata'] ?? null;
            if (is_string($metadata) && $metadata !== '') {
                $decoded = json_decode($metadata, true);
                if (is_array($decoded)) {
                    $remoteId = isset($decoded['remote_id']) ? (string) $decoded['remote_id'] : null;
                    if (!isset($message['status']) && isset($decoded['status'])) {
                        $message['status'] = $decoded['status'];
                    }
                }
            }

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
                'remote_id' => $remoteId,
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
                'remote_id' => $id,
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
    private function persistNativeMessages(int $ticketId, ?int $contactId, array $messages, string $remoteJid): void
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
        $stored = 0;
        $skipped = 0;

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
                $skipped++;
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
            $stored++;
        }

        if ($updatedContact && $contactId !== null) {
            $this->connection->prepare('UPDATE contacts SET last_interaction_at = :now WHERE id = :id')->execute([
                'now' => $latestSentAt ?? $now,
                'id' => $contactId,
            ]);
        }

        if ($stored > 0) {
            $this->logger->info('ticket.native_messages_persisted', [
                'ticket_id' => $ticketId,
                'remote_jid' => $remoteJid,
                'stored_messages' => $stored,
                'skipped_existing' => $skipped,
            ]);
        } elseif ($skipped > 0) {
            $this->logger->info('ticket.native_messages_skipped', [
                'ticket_id' => $ticketId,
                'remote_jid' => $remoteJid,
                'skipped_existing' => $skipped,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function messageSignature(array $message): string
    {
        $remoteId = isset($message['remote_id']) ? (string) $message['remote_id'] : '';
        if ($remoteId !== '') {
            return 'remote:' . $remoteId;
        }

        $id = isset($message['id']) ? (string) $message['id'] : '';
        if ($id !== '') {
            return 'id:' . $id;
        }

        $parts = [
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
        $defaultGroup = $this->groups->defaultGroupId();

        $stmt = $this->connection->prepare(
            'INSERT INTO tickets (contact_id, status, priority, seriousness, group_id, channel)'
            . ' VALUES (:contact_id, :status, :priority, :seriousness, :group_id, :channel)'
        );
        $stmt->bindValue(':contact_id', $contactId, PDO::PARAM_INT);
        $stmt->bindValue(':status', Ticket::STATUS_OPEN);
        $stmt->bindValue(':priority', Ticket::PRIORITY_NORMAL);
        $stmt->bindValue(':seriousness', Ticket::SERIOUSNESS_INFORMATION);
        if ($defaultGroup !== null) {
            $stmt->bindValue(':group_id', $defaultGroup, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':group_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':channel', $channel);
        $stmt->execute();

        $ticketId = (int) $this->connection->lastInsertId();

        $this->connection->prepare(
            'INSERT INTO ticket_metrics (ticket_id) VALUES (:ticket_id)'
            . ' ON DUPLICATE KEY UPDATE ticket_id = ticket_id'
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
     * @param array<string, mixed>|string|null $filters
     * @return array<int, array<string, mixed>>
     */
    public function listTickets(array|string|null $filters = null): array
    {
        $options = [
            'status' => null,
            'priority' => null,
            'seriousness' => null,
            'assigned' => null,
            'group' => null,
            'search' => null,
            'exclude' => null,
        ];

        if (is_string($filters) && $filters !== '') {
            $options['status'] = $filters;
        } elseif (is_array($filters)) {
            foreach ($options as $key => $value) {
                if (array_key_exists($key, $filters)) {
                    $options[$key] = $filters[$key];
                }
            }
        }

        $sql = 'SELECT t.id, t.status, t.priority, t.seriousness, t.group_id, t.opened_at, t.closed_at, t.sla_due_at, t.channel, '
            . 'c.display_name AS contact_name, u.full_name AS agent_name, sg.name AS group_name '
            . 'FROM tickets t '
            . 'INNER JOIN contacts c ON c.id = t.contact_id '
            . 'LEFT JOIN users u ON u.id = t.assigned_user_id '
            . 'LEFT JOIN support_groups sg ON sg.id = t.group_id';

        $conditions = [];
        $params = [];

        if (!empty($options['status'])) {
            $conditions[] = 't.status = :status';
            $params['status'] = $options['status'];
        }

        if (!empty($options['priority'])) {
            $conditions[] = 't.priority = :priority';
            $params['priority'] = $options['priority'];
        }

        if (!empty($options['seriousness'])) {
            $conditions[] = 't.seriousness = :seriousness';
            $params['seriousness'] = $options['seriousness'];
        }

        if (!empty($options['assigned']) && is_numeric($options['assigned'])) {
            $conditions[] = 't.assigned_user_id = :assigned_user_id';
            $params['assigned_user_id'] = (int) $options['assigned'];
        }

        if (!empty($options['group'])) {
            if (is_array($options['group'])) {
                $groupValues = [];
                foreach ($options['group'] as $index => $group) {
                    $key = 'group_' . $index;
                    $groupValues[] = ':' . $key;
                    $params[$key] = (int) $group;
                }
                if ($groupValues !== []) {
                    $conditions[] = 't.group_id IN (' . implode(',', $groupValues) . ')';
                }
            } else {
                $conditions[] = 't.group_id = :group_id';
                $params['group_id'] = (int) $options['group'];
            }
        }

        if (!empty($options['exclude']) && is_array($options['exclude'])) {
            $placeholders = [];
            foreach ($options['exclude'] as $index => $status) {
                $key = ':exclude_' . $index;
                $placeholders[] = $key;
                $params['exclude_' . $index] = $status;
            }
            if ($placeholders !== []) {
                $conditions[] = 't.status NOT IN (' . implode(',', $placeholders) . ')';
            }
        }

        if (!empty($options['search'])) {
            $conditions[] = '(c.display_name LIKE :search OR CAST(t.id AS CHAR) LIKE :search)';
            $params['search'] = '%' . trim((string) $options['search']) . '%';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY t.opened_at DESC';

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            $param = ':' . $key;
            if ($key === 'assigned_user_id') {
                $stmt->bindValue($param, (int) $value, PDO::PARAM_INT);
                continue;
            }

            if (str_starts_with($key, 'group_')) {
                $stmt->bindValue($param, (int) $value, PDO::PARAM_INT);
                continue;
            }

            if ($key === 'group_id') {
                $stmt->bindValue($param, (int) $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($param, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $now = new DateTimeImmutable('now');

        return array_map(static function (array $row) use ($now): array {
            $slaDue = $row['sla_due_at'] ?? null;
            $row['sla_status'] = 'ok';
            $row['sla_remaining'] = null;
            $row['group_name'] = $row['group_name'] ?? null;

            if (is_string($slaDue) && $slaDue !== '') {
                try {
                    $due = new DateTimeImmutable($slaDue);
                    $diff = $due->getTimestamp() - $now->getTimestamp();
                    $row['sla_remaining'] = $diff;
                    if ($diff <= 0) {
                        $row['sla_status'] = 'breach';
                    } elseif ($diff <= 3600) {
                        $row['sla_status'] = 'warning';
                    }
                } catch (Throwable $exception) {
                    $row['sla_status'] = 'unknown';
                }
            } else {
                $row['sla_status'] = 'unset';
            }

            return $row;
        }, $rows);
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


    private function calculateSlaStatus(?string $slaDueAt, DateTimeImmutable $reference): ?string
    {
        if ($slaDueAt === null || trim($slaDueAt) === '') {
            return null;
        }

        try {
            $due = new DateTimeImmutable($slaDueAt);
        } catch (\Throwable) {
            return null;
        }

        if ($due < $reference) {
            return 'breach';
        }

        $delta = $due->getTimestamp() - $reference->getTimestamp();
        if ($delta <= 900) {
            return 'warning';
        }

        return 'ok';
    }
}
