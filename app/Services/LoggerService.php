<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class LoggerService
{
    public function __construct(private PDO $connection)
    {
    }

    public function info(string $action, array $context = []): void
    {
        $this->write('info', $action, $context);
    }

    public function warning(string $action, array $context = []): void
    {
        $this->write('warning', $action, $context);
    }

    public function error(string $action, array $context = []): void
    {
        $this->write('error', $action, $context);
    }

    private function write(string $level, string $action, array $context): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO logs (user_id, level, action, message, context, ip_address)
             VALUES (:user_id, :level, :action, :message, :context, :ip_address)'
        );

        $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $encodedContext = json_encode($context, $options);

        if ($encodedContext === false) {
            $encodedContext = json_encode([
                'encoding_error' => true,
                'original' => array_keys($context),
            ], $options);
        }

        $stmt->execute([
            'user_id' => $context['user_id'] ?? null,
            'level' => $level,
            'action' => $action,
            'message' => $context['message'] ?? '',
            'context' => $encodedContext,
            'ip_address' => $context['ip_address'] ?? null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit = 50, ?string $level = null): array
    {
        $sql = 'SELECT l.*, u.full_name AS user_name FROM logs l '
            . 'LEFT JOIN users u ON u.id = l.user_id';

        $params = [];
        if ($level !== null && $level !== '') {
            $sql .= ' WHERE l.level = :level';
            $params['level'] = $level;
        }

        $sql .= ' ORDER BY l.created_at DESC LIMIT :limit';

        $stmt = $this->connection->prepare($sql);
        if (isset($params['level'])) {
            $stmt->bindValue(':level', $params['level']);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
