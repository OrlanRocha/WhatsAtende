<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
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
        $ticket['messages'] = $stmtMessages->fetchAll(PDO::FETCH_ASSOC);

        return $ticket;
    }

    public function appendAgentMessage(int $ticketId, int $userId, string $body): void
    {
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

            $result = $this->evolution->sendText($contactExternalId, $body);
            if (!$result['success']) {
                $this->logger->error('ticket.native_send_failed', [
                    'ticket_id' => $ticketId,
                    'user_id' => $userId,
                    'contact' => $contactExternalId,
                    'error' => $result['error'],
                ]);

                throw new RuntimeException($result['error'] ?? 'Falha ao enviar mensagem via Evolution.');
            }
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO messages (ticket_id, sender_type, user_id, body, media_type)
             VALUES (:ticket_id, :sender_type, :user_id, :body, :media_type)'
        );
        $stmt->execute([
            'ticket_id' => $ticketId,
            'sender_type' => 'agent',
            'user_id' => $userId,
            'body' => $body,
            'media_type' => 'text',
        ]);

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->prepare(
            'UPDATE ticket_metrics SET last_response_at = :now, first_response_at = COALESCE(first_response_at, :now) WHERE ticket_id = :ticket_id'
        )->execute([
            'now' => $now,
            'ticket_id' => $ticketId,
        ]);

        $this->logger->info('ticket.agent_response', [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
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
