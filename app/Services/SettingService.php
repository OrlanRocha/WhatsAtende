<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class SettingService
{
    public function __construct(private PDO $connection, private LoggerService $logger)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->connection->prepare('SELECT value FROM settings WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (string) $value : $default;
    }

    /**
     * @return array<string, string|null>
     */
    public function webhookSettings(): array
    {
        return [
            'webhook_url' => $this->get('webhook_url'),
            'webhook_token' => $this->get('webhook_token'),
        ];
    }

    public function updateWebhookSettings(int $userId, string $url, string $token): void
    {
        $this->set('webhook_url', $url, $userId, 'URL pública utilizada para integrar Evolution API.');
        $this->set('webhook_token', $token, $userId, 'Token compartilhado para validar requisições.');

        $this->logger->info('admin.webhook_settings_updated', [
            'user_id' => $userId,
            'message' => 'Configurações do webhook atualizadas.',
        ]);
    }

    public function set(string $key, ?string $value, int $userId, ?string $description = null): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO settings (`key`, value, description, updated_by) '
            . 'VALUES (:key, :value, :description, :updated_by) '
            . 'ON DUPLICATE KEY UPDATE value = VALUES(value), description = VALUES(description), updated_by = VALUES(updated_by)'
        );

        $stmt->execute([
            'key' => $key,
            'value' => $value,
            'description' => $description,
            'updated_by' => $userId,
        ]);
    }
}
