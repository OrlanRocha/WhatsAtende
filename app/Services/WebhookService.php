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

            // Evolution API v2 normalization
            if (isset($payload['event'])) {
                if ($payload['event'] !== 'messages.upsert') {
                    $this->storeWebhookEvent($provider, null, $payload);
                    $this->connection->commit();
                    return 0;
                }
                
                if (isset($payload['data'])) {
                    $data = $payload['data'];
                    $messageId = $data['key']['id'] ?? $messageId;
                $contactExternalId = $data['key']['remoteJid'] ?? $contactExternalId;
                $contactName = $data['pushName'] ?? $contactName;
                
                // Content resolution
                $evMessage = $data['message'] ?? [];
                $messageBody = $evMessage['conversation'] 
                    ?? $evMessage['extendedTextMessage']['text'] 
                    ?? $evMessage['imageMessage']['caption'] 
                    ?? $evMessage['videoMessage']['caption'] 
                    ?? $evMessage['documentMessage']['fileName'] 
                    ?? $messageBody;
                
                // Media Type detection
                if (isset($evMessage['imageMessage'])) $mediaType = 'image';
                elseif (isset($evMessage['videoMessage'])) $mediaType = 'video';
                elseif (isset($evMessage['audioMessage'])) $mediaType = 'audio';
                elseif (isset($evMessage['documentMessage'])) $mediaType = 'file';
                }
            }

            if ($contactExternalId === null) {
                // Try one more fallback for non-v2 or different formats
                $contactExternalId = $payload['key']['remoteJid'] ?? null;
                if ($contactExternalId === null) {
                    throw new \InvalidArgumentException('Contact identifier is required.');
                }
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
            'SELECT id FROM tickets WHERE contact_id = :contact_id
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

        $stmtSla = $this->connection->query("SELECT `value` FROM settings WHERE `key` = 'default_tma_minutes'");
        $slaSetting = $stmtSla->fetch(\PDO::FETCH_ASSOC);
        $slaMinutes = isset($slaSetting['value']) ? (int) $slaSetting['value'] : 15;
        
        $now = new \DateTimeImmutable();
        $slaDueAt = $now->modify('+' . $slaMinutes . ' minutes')->format('Y-m-d H:i:s');

        $stmtInsert = $this->connection->prepare(
            'INSERT INTO tickets (contact_id, status, priority, channel, sla_due_at) VALUES (:contact_id, :status, :priority, :channel, :sla_due_at)'
        );
        $stmtInsert->execute([
            'contact_id' => $contactId,
            'status' => Ticket::STATUS_OPEN,
            'priority' => Ticket::PRIORITY_NORMAL,
            'channel' => $channel,
            'sla_due_at' => $slaDueAt,
        ]);

        $newTicketId = (int) $this->connection->lastInsertId();

        $this->connection->prepare(
            'INSERT INTO ticket_metrics (ticket_id) VALUES (:ticket_id)'
            . ' ON DUPLICATE KEY UPDATE ticket_id = ticket_id'
        )->execute(['ticket_id' => $newTicketId]);

        $this->logger->info('ticket.created_from_webhook', [
            'ticket_id' => $newTicketId,
            'contact_id' => $contactId,
            'message' => 'Novo ticket criado a partir do webhook.',
        ]);

        return $newTicketId;
    }
}
