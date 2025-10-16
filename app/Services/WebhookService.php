<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use DateTimeImmutable;
use PDO;
use Throwable;

class WebhookService
{
    public function __construct(private PDO $connection, private LoggerService $logger)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function processIncomingMessage(array $payload, string $provider = 'evolution'): int
    {
        $this->connection->beginTransaction();

        try {
            $messageId = $payload['message']['id'] ?? null;
            $contactExternalId = $payload['contact']['id'] ?? null;
            $contactName = $payload['contact']['name'] ?? null;
            $channel = $payload['channel'] ?? 'whatsapp';
            $messageBody = $payload['message']['text'] ?? null;
            $mediaUrl = $payload['message']['mediaUrl'] ?? null;
            $mediaType = $payload['message']['type'] ?? 'text';

            if ($contactExternalId === null) {
                throw new \InvalidArgumentException('Contact identifier is required.');
            }

            $this->storeWebhookEvent($provider, $messageId, $payload);

            $contactId = $this->findOrCreateContact($contactExternalId, $contactName);
            $ticketId = $this->findOrCreateTicket($contactId, $channel);

            $stmt = $this->connection->prepare(
                'INSERT INTO messages (ticket_id, sender_type, body, media_type, media_url, metadata)
                 VALUES (:ticket_id, :sender_type, :body, :media_type, :media_url, :metadata)'
            );
            $stmt->execute([
                'ticket_id' => $ticketId,
                'sender_type' => 'contact',
                'body' => $messageBody,
                'media_type' => $mediaType,
                'media_url' => $mediaUrl,
                'metadata' => json_encode($payload['message'] ?? []),
            ]);

            $this->connection->prepare(
                'UPDATE contacts SET last_interaction_at = :now WHERE id = :id'
            )->execute([
                'now' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $contactId,
            ]);

            $this->connection->commit();

            $this->logger->info('webhook.message_processed', [
                'ticket_id' => $ticketId,
                'contact_id' => $contactId,
                'message_id' => $messageId,
            ]);

            return $ticketId;
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            $this->logger->error('webhook.processing_failed', [
                'message' => $exception->getMessage(),
                'payload' => $payload,
            ]);

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function storeWebhookEvent(string $provider, ?string $messageId, array $payload): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO webhook_events (provider, external_message_id, payload, processed, processed_at)
             VALUES (:provider, :external_message_id, :payload, 1, :processed_at)
             ON DUPLICATE KEY UPDATE processed = 1, processed_at = VALUES(processed_at)'
        );

        $stmt->execute([
            'provider' => $provider,
            'external_message_id' => $messageId,
            'payload' => json_encode($payload),
            'processed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function findOrCreateContact(string $externalId, ?string $name): int
    {
        $stmt = $this->connection->prepare('SELECT id FROM contacts WHERE external_id = :external_id');
        $stmt->execute(['external_id' => $externalId]);
        $contactId = $stmt->fetchColumn();

        if ($contactId) {
            return (int) $contactId;
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO contacts (external_id, display_name) VALUES (:external_id, :display_name)'
        );
        $stmt->execute([
            'external_id' => $externalId,
            'display_name' => $name,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function findOrCreateTicket(int $contactId, string $channel): int
    {
        $stmt = $this->connection->prepare(
            'SELECT id FROM tickets WHERE contact_id = :contact_id AND DATE(opened_at) = CURRENT_DATE()
             AND status IN (:status_open, :status_assigned) ORDER BY opened_at DESC LIMIT 1'
        );
        $stmt->execute([
            'contact_id' => $contactId,
            'status_open' => Ticket::STATUS_OPEN,
            'status_assigned' => Ticket::STATUS_ASSIGNED,
        ]);
        $ticketId = $stmt->fetchColumn();

        if ($ticketId) {
            return (int) $ticketId;
        }

        $stmtInsert = $this->connection->prepare(
            'INSERT INTO tickets (contact_id, status, priority, channel) VALUES (:contact_id, :status, :priority, :channel)'
        );
        $stmtInsert->execute([
            'contact_id' => $contactId,
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_NORMAL,
            'channel' => $channel,
        ]);

        $newTicketId = (int) $this->connection->lastInsertId();

        $this->connection->prepare(
            'INSERT INTO ticket_metrics (ticket_id, first_response_at) VALUES (:ticket_id, NULL)'
        )->execute(['ticket_id' => $newTicketId]);

        $this->logger->info('ticket.created_from_webhook', [
            'ticket_id' => $newTicketId,
            'contact_id' => $contactId,
            'message' => 'Novo ticket criado a partir do webhook.',
        ]);

        return $newTicketId;
    }
}
