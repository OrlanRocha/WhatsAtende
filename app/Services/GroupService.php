<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use Throwable;

class GroupService
{
    public function __construct(
        private PDO $connection,
        private LoggerService $logger
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listGroups(): array
    {
        $statement = $this->connection->query(
            'SELECT sg.id, sg.name, sg.slug, sg.description, '
            . 't.target_tma, t.target_tme, t.updated_at AS targets_updated_at '
            . 'FROM support_groups sg '
            . 'LEFT JOIN support_group_targets t ON t.group_id = sg.id '
            . 'ORDER BY sg.name ASC'
        );

        $rows = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'description' => $row['description'] ?? null,
                'target_tma' => $row['target_tma'] !== null ? (int) $row['target_tma'] : null,
                'target_tme' => $row['target_tme'] !== null ? (int) $row['target_tme'] : null,
                'targets_updated_at' => $row['targets_updated_at'] ?? null,
            ];
        }, $rows ?: []);
    }

    /**
     * @return array<int, int>
     */
    public function getUserGroupIds(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT group_id FROM user_groups WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        $ids = [];
        while ($value = $statement->fetchColumn()) {
            $ids[] = (int) $value;
        }

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserGroups(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $statement = $this->connection->prepare(
            'SELECT sg.id, sg.name, sg.slug '
            . 'FROM user_groups ug '
            . 'INNER JOIN support_groups sg ON sg.id = ug.group_id '
            . 'WHERE ug.user_id = :user_id '
            . 'ORDER BY sg.name ASC'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function mapUsersToGroups(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $normalized = [];
        foreach ($userIds as $userId) {
            $id = (int) $userId;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        if ($normalized === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $sql = sprintf(
            'SELECT ug.user_id, sg.id, sg.name, sg.slug FROM user_groups ug '
            . 'INNER JOIN support_groups sg ON sg.id = ug.group_id '
            . 'WHERE ug.user_id IN (%s) ORDER BY sg.name ASC',
            $placeholders
        );
        $statement = $this->connection->prepare($sql);
        $statement->execute(array_values($normalized));

        $map = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $map[$userId] ??= [];
            $map[$userId][] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @param array<int, int|string> $groupIds
     *
     * @return array<int, int>
     */
    public function syncUserGroups(int $userId, array $groupIds, int $actorId): array
    {
        $normalized = [];
        foreach ($groupIds as $groupId) {
            $value = (int) $groupId;
            if ($value > 0) {
                $normalized[$value] = $value;
            }
        }
        $normalized = array_values($normalized);

        $this->connection->beginTransaction();

        try {
            if ($normalized === []) {
                $delete = $this->connection->prepare('DELETE FROM user_groups WHERE user_id = :user_id');
                $delete->execute(['user_id' => $userId]);
            } else {
                $placeholder = implode(',', array_fill(0, count($normalized), '?'));
                $delete = $this->connection->prepare(
                    "DELETE FROM user_groups WHERE user_id = ? AND group_id NOT IN ({$placeholder})"
                );
                $delete->execute(array_merge([$userId], $normalized));

                $insert = $this->connection->prepare(
                    'INSERT INTO user_groups (user_id, group_id, assigned_by) '
                    . 'VALUES (:user_id, :group_id, :assigned_by) '
                    . 'ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP'
                );

                foreach ($normalized as $groupId) {
                    $insert->execute([
                        'user_id' => $userId,
                        'group_id' => $groupId,
                        'assigned_by' => $actorId ?: null,
                    ]);
                }
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $this->logger->info('groups.sync_user_groups', [
            'user_id' => $userId,
            'actor_id' => $actorId,
            'groups' => $normalized,
        ]);

        return $normalized;
    }

    public function updateTargets(int $groupId, ?int $tma, ?int $tme, int $userId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO support_group_targets (group_id, target_tma, target_tme, updated_by) '
            . 'VALUES (:group_id, :target_tma, :target_tme, :updated_by) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'target_tma = VALUES(target_tma), '
            . 'target_tme = VALUES(target_tme), '
            . 'updated_by = VALUES(updated_by), '
            . 'updated_at = CURRENT_TIMESTAMP'
        );

        $statement->execute([
            'group_id' => $groupId,
            'target_tma' => $tma,
            'target_tme' => $tme,
            'updated_by' => $userId ?: null,
        ]);

        $this->logger->info('groups.update_targets', [
            'group_id' => $groupId,
            'target_tma' => $tma,
            'target_tme' => $tme,
            'actor_id' => $userId,
        ]);
    }

    public function defaultGroupId(): ?int
    {
        $statement = $this->connection->prepare('SELECT id FROM support_groups WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => 'atendimento-inicial']);
        $value = $statement->fetchColumn();

        return $value !== false ? (int) $value : null;
    }

    public function ensureGroup(string $name, string $slug): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO support_groups (name, slug) VALUES (:name, :slug) '
            . 'ON DUPLICATE KEY UPDATE name = VALUES(name)'
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
        ]);

        try {
            $id = (int) $this->connection->lastInsertId();
            if ($id > 0) {
                return $id;
            }
        } catch (PDOException) {
            // ignore when driver does not support lastInsertId for this statement
        }

        $lookup = $this->connection->prepare('SELECT id FROM support_groups WHERE slug = :slug LIMIT 1');
        $lookup->execute(['slug' => $slug]);
        $value = $lookup->fetchColumn();

        return $value !== false ? (int) $value : 0;
    }

    public function groupExists(int $groupId): bool
    {
        if ($groupId <= 0) {
            return false;
        }

        $statement = $this->connection->prepare('SELECT 1 FROM support_groups WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $groupId]);

        return $statement->fetchColumn() !== false;
    }
}
