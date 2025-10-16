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
    public function integrationSettings(): array
    {
        return [
            'integration_mode' => $this->get('integration_mode', 'webhook'),
            'webhook_url' => $this->get('webhook_url'),
            'webhook_token' => $this->get('webhook_token'),
            'evolution_api_url' => $this->get('evolution_api_url'),
            'evolution_instance' => $this->get('evolution_instance'),
            'evolution_api_key' => $this->get('evolution_api_key'),
            'evolution_token' => $this->get('evolution_token'),
            'evolution_default_template' => $this->get('evolution_default_template'),
        ];
    }

    public function updateIntegrationSettings(int $userId, array $data): void
    {
        $mode = $data['integration_mode'] ?? 'webhook';
        $this->set('integration_mode', $mode, $userId, 'Modo de integração atual (webhook ou native).');

        if ($mode === 'webhook') {
            $this->set('webhook_url', $data['webhook_url'] ?? null, $userId, 'URL pública utilizada para integrar Evolution API.');
            $this->set('webhook_token', $data['webhook_token'] ?? null, $userId, 'Token compartilhado para validar requisições.');
        } else {
            $this->set('evolution_api_url', $data['evolution_api_url'] ?? null, $userId, 'Endpoint base da Evolution API.');
            $this->set('evolution_instance', $data['evolution_instance'] ?? null, $userId, 'Instância utilizada para envio de mensagens.');
            $this->set('evolution_api_key', $data['evolution_api_key'] ?? null, $userId, 'Chave API utilizada em chamadas nativas.');
            $this->set('evolution_token', $data['evolution_token'] ?? null, $userId, 'Token Bearer opcional para cenários legados.');
            $this->set('evolution_default_template', $data['evolution_default_template'] ?? null, $userId, 'Mensagem automática inicial para atendimentos.');
        }

        $this->logger->info('admin.integration_settings_updated', [
            'user_id' => $userId,
            'mode' => $mode,
            'message' => 'Configurações de integração atualizadas.',
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
