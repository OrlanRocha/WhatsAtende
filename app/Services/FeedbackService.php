<?php

declare(strict_types=1);

namespace App\Services;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class FeedbackService
{
    public function __construct(
        private PDO $connection,
        private LoggerService $logger
    ) {
    }

    /**
     * @return array{token:string,expires_at:string}
     */
    public function issueToken(int $ticketId): array
    {
        if ($ticketId <= 0) {
            throw new InvalidArgumentException('Ticket inválido para gerar avaliação.');
        }

        $token = $this->generateToken();
        $expiresAt = (new DateTimeImmutable())
            ->add(new DateInterval('PT24H'))
            ->format('Y-m-d H:i:s');

        $statement = $this->connection->prepare(
            'INSERT INTO ticket_feedback (ticket_id, token, token_expires_at)
             VALUES (:ticket_id, :token, :expires_at)
             ON DUPLICATE KEY UPDATE
                token = VALUES(token),
                token_expires_at = VALUES(token_expires_at),
                rating = NULL,
                note = NULL,
                submitted_at = NULL,
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'ticket_id' => $ticketId,
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        $this->logger->info('ticket.feedback_token_issued', [
            'ticket_id' => $ticketId,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $statement = $this->connection->prepare(
            'SELECT tf.*, t.subject, t.opened_at, t.closed_at, t.group_id, c.display_name AS contact_name, c.external_id,
                    sg.name AS group_name
             FROM ticket_feedback tf
             INNER JOIN tickets t ON t.id = tf.ticket_id
             INNER JOIN contacts c ON c.id = t.contact_id
             LEFT JOIN support_groups sg ON sg.id = t.group_id
             WHERE tf.token = :token
             LIMIT 1'
        );
        $statement->execute(['token' => $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row === null) {
            return null;
        }

        $expiresAtRaw = $row['token_expires_at'] ?? null;
        $submittedAtRaw = $row['submitted_at'] ?? null;
        $expiresAt = is_string($expiresAtRaw) ? $expiresAtRaw : null;
        $submittedAt = is_string($submittedAtRaw) ? $submittedAtRaw : null;

        $isExpired = false;
        if ($expiresAt !== null && $expiresAt !== '') {
            try {
                $expiry = new DateTimeImmutable($expiresAt);
                $isExpired = $expiry < new DateTimeImmutable();
            } catch (Throwable) {
                $isExpired = false;
            }
        }

        $rating = $row['rating'] ?? null;
        $rating = $rating !== null ? (int) $rating : null;

        return [
            'ticket_id' => (int) ($row['ticket_id'] ?? 0),
            'token' => (string) $row['token'],
            'token_expires_at' => $expiresAt,
            'rating' => $rating,
            'note' => $row['note'] ?? null,
            'submitted_at' => $submittedAt,
            'is_submitted' => $submittedAt !== null,
            'is_expired' => $isExpired,
            'subject' => $row['subject'] ?? null,
            'contact_name' => $row['contact_name'] ?? null,
            'contact_external_id' => $row['external_id'] ?? null,
            'group_id' => isset($row['group_id']) ? (int) $row['group_id'] : null,
            'group_name' => $row['group_name'] ?? null,
            'opened_at' => $row['opened_at'] ?? null,
            'closed_at' => $row['closed_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submitFeedback(string $token, int $rating, ?string $note = null): array
    {
        $details = $this->getByToken($token);
        if ($details === null) {
            throw new RuntimeException('Avaliação não encontrada.');
        }

        if ($details['is_submitted']) {
            throw new RuntimeException('Esta avaliação já foi registrada.');
        }
        if ($details['is_expired']) {
            throw new RuntimeException('O link de avaliação expirou.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Selecione uma nota entre 1 e 5 estrelas.');
        }

        $sanitizedNote = null;
        if ($note !== null) {
            $trimmed = trim($note);
            if ($trimmed !== '') {
                $sanitizedNote = mb_substr($trimmed, 0, 1000);
            }
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $statement = $this->connection->prepare(
            'UPDATE ticket_feedback
             SET rating = :rating,
                 note = :note,
                 submitted_at = :submitted_at,
                 updated_at = :submitted_at
             WHERE token = :token'
        );
        $statement->execute([
            'rating' => $rating,
            'note' => $sanitizedNote,
            'submitted_at' => $now,
            'token' => $token,
        ]);

        $this->logger->info('ticket.feedback_submitted', [
            'ticket_id' => $details['ticket_id'],
            'rating' => $rating,
        ]);

        return $this->getByToken($token) ?? $details;
    }

    /**
     * @param DateTimeImmutable|null $from
     * @param DateTimeImmutable|null $to
     * @param array<int> $groupIds
     * @return array<int, array<string, mixed>>
     */
    public function listFeedback(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null, array $groupIds = []): array
    {
        $conditions = ['tf.submitted_at IS NOT NULL'];
        $params = [];

        if ($from !== null) {
            $conditions[] = 'tf.submitted_at >= :from';
            $params['from'] = $from->format('Y-m-d 00:00:00');
        }

        if ($to !== null) {
            $conditions[] = 'tf.submitted_at <= :to';
            $params['to'] = $to->format('Y-m-d 23:59:59');
        }

        if ($groupIds !== []) {
            $placeholders = [];
            foreach ($groupIds as $index => $groupId) {
                $key = 'group_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $groupId;
            }
            $conditions[] = 't.group_id IN (' . implode(',', $placeholders) . ')';
        }

        $sql = 'SELECT tf.*, t.subject, t.closed_at, t.opened_at, c.display_name AS contact_name, c.external_id, '
            . 'sg.name AS group_name '
            . 'FROM ticket_feedback tf '
            . 'INNER JOIN tickets t ON t.id = tf.ticket_id '
            . 'INNER JOIN contacts c ON c.id = t.contact_id '
            . 'LEFT JOIN support_groups sg ON sg.id = t.group_id ';

        if ($conditions !== []) {
            $sql .= 'WHERE ' . implode(' AND ', $conditions) . ' ';
        }

        $sql .= 'ORDER BY tf.submitted_at DESC';

        $statement = $this->connection->prepare($sql);
        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'group_')) {
                $statement->bindValue(':' . $key, (int) $value, PDO::PARAM_INT);
                continue;
            }
            $statement->bindValue(':' . $key, $value);
        }
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            return [
                'ticket_id' => (int) ($row['ticket_id'] ?? 0),
                'rating' => isset($row['rating']) ? (int) $row['rating'] : null,
                'note' => $row['note'] ?? null,
                'submitted_at' => $row['submitted_at'] ?? null,
                'token_expires_at' => $row['token_expires_at'] ?? null,
                'contact_name' => $row['contact_name'] ?? null,
                'contact_external_id' => $row['external_id'] ?? null,
                'subject' => $row['subject'] ?? null,
                'group_name' => $row['group_name'] ?? null,
                'opened_at' => $row['opened_at'] ?? null,
                'closed_at' => $row['closed_at'] ?? null,
                'token' => $row['token'] ?? null,
            ];
        }, $rows);
    }

    private function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable) {
            return bin2hex(
                pack('H*', substr(md5(uniqid((string) mt_rand(), true)), 0, 32))
            );
        }
    }
}
