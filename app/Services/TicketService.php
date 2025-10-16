<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use DateTimeImmutable;
use PDO;

class TicketService
{
    public function __construct(
        private PDO $connection,
        private LoggerService $logger
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenQueue(): array
    {
        $stmt = $this->connection->prepare(
            'SELECT t.*, c.display_name AS contact_name FROM tickets t
            INNER JOIN contacts c ON c.id = t.contact_id
            WHERE t.status = :status ORDER BY t.opened_at ASC'
        );
        $stmt->execute(['status' => Ticket::STATUS_OPEN]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignToUser(int $ticketId, int $userId): void
    {
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
        ]);
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
}
