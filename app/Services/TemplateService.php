<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class TemplateService
{
    public function __construct(private PDO $connection, private LoggerService $logger)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $stmt = $this->connection->query(
            'SELECT t.*, u.full_name AS author '
            . 'FROM templates t '
            . 'LEFT JOIN users u ON u.id = t.created_by '
            . 'ORDER BY t.title ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $title, string $body, ?string $category, int $userId): array
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO templates (title, body, category, created_by, updated_by) '
            . 'VALUES (:title, :body, :category, :created_by, :updated_by)'
        );
        $stmt->execute([
            'title' => $title,
            'body' => $body,
            'category' => $category ?: null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->logger->info('admin.template_created', [
            'user_id' => $userId,
            'message' => 'Template criado.',
        ]);

        $templateId = (int) $this->connection->lastInsertId();

        return $this->find($templateId) ?? [];
    }

    public function update(int $templateId, string $title, string $body, ?string $category, int $userId): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE templates SET title = :title, body = :body, category = :category, updated_by = :updated_by '
            . 'WHERE id = :id'
        );
        $stmt->execute([
            'id' => $templateId,
            'title' => $title,
            'body' => $body,
            'category' => $category ?: null,
            'updated_by' => $userId,
        ]);

        $this->logger->info('admin.template_updated', [
            'user_id' => $userId,
            'template_id' => $templateId,
            'message' => 'Template atualizado.',
        ]);
    }

    public function delete(int $templateId, int $userId): void
    {
        $stmt = $this->connection->prepare('DELETE FROM templates WHERE id = :id');
        $stmt->execute(['id' => $templateId]);

        $this->logger->info('admin.template_deleted', [
            'user_id' => $userId,
            'template_id' => $templateId,
            'message' => 'Template removido.',
        ]);
    }

    public function find(int $templateId): ?array
    {
        $stmt = $this->connection->prepare('SELECT * FROM templates WHERE id = :id');
        $stmt->execute(['id' => $templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        return $template ?: null;
    }
}
