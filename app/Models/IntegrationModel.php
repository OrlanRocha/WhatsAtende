<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class IntegrationModel
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM integration_instances ORDER BY is_default DESC, name ASC');
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->connection->prepare('SELECT * FROM integration_instances WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDefault(): ?array
    {
        $stmt = $this->connection->query('SELECT * FROM integration_instances WHERE is_default = 1 AND is_active = 1 LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): bool
    {
        $sql = 'INSERT INTO integration_instances (name, api_url, instance_name, api_key, token, default_template, is_default, is_active, updated_by) '
            . 'VALUES (:name, :api_url, :instance_name, :api_key, :token, :default_template, :is_default, :is_active, :updated_by)';
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];
        foreach ($data as $key => $value) {
            $fields[] = "`{$key}` = :{$key}";
            $params[$key] = $value;
        }
        
        $sql = 'UPDATE integration_instances SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->connection->prepare('DELETE FROM integration_instances WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function setDefault(int $id): void
    {
        $this->connection->beginTransaction();
        try {
            $this->connection->exec('UPDATE integration_instances SET is_default = 0');
            $stmt = $this->connection->prepare('UPDATE integration_instances SET is_default = 1 WHERE id = :id');
            $stmt->execute(['id' => $id]);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }
}
