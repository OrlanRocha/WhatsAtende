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

        $stmt->execute([
            'user_id' => $context['user_id'] ?? null,
            'level' => $level,
            'action' => $action,
            'message' => $context['message'] ?? '',
            'context' => json_encode($context),
            'ip_address' => $context['ip_address'] ?? null,
        ]);
    }
}
