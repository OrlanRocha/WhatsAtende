<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

class UserService
{
    public function __construct(
        private PDO $connection,
        private LoggerService $logger,
        private GroupService $groups
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(): array
    {
        $stmt = $this->connection->query(
            'SELECT u.id, u.role_id, u.full_name, u.email, u.cpf, u.is_active, u.created_at, u.updated_at, '
            . 'r.name AS role, COUNT(t.id) AS assigned_tickets '
            . 'FROM users u '
            . 'INNER JOIN roles r ON r.id = u.role_id '
            . "LEFT JOIN tickets t ON t.assigned_user_id = u.id AND t.status IN ('open', 'assigned') "
            . 'GROUP BY u.id, u.role_id, u.full_name, u.email, u.cpf, u.is_active, u.created_at, u.updated_at, r.name '
            . 'ORDER BY u.full_name ASC'
        );

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->hydrateUserPermissions($users);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRoles(): array
    {
        $stmt = $this->connection->query('SELECT id, name FROM roles ORDER BY name ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            static function (array $row): array {
                $name = (string) ($row['name'] ?? '');

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => $name,
                    'display_name' => self::formatRoleLabel($name),
                ];
            },
            $rows
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listGroups(): array
    {
        return $this->groups->listGroups();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPermissions(): array
    {
        $stmt = $this->connection->query(
            'SELECT id, name, label, description FROM permissions ORDER BY label ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'label' => $row['label'] ?? $row['name'] ?? '',
                    'description' => $row['description'] ?? null,
                ];
            },
            $rows
        );
    }

    public function createUser(
        string $fullName,
        string $email,
        string $cpf,
        string $password,
        int $roleId,
        bool $active,
        array $permissions,
        array $groups,
        int $actorId
    ): array {
        $fullName = $this->sanitizeFullName($fullName);
        $email = $this->normalizeEmail($email);
        $cpf = $this->normalizeCpf($cpf);

        if ($fullName === '' || $email === '' || strlen($cpf) !== 11) {
            throw new RuntimeException('Dados inválidos fornecidos para criação de usuário.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, email, cpf, password_hash, is_active) '
            . 'VALUES (:role_id, :full_name, :email, :cpf, :password_hash, :is_active)'
        );

        try {
            $stmt->execute([
                'role_id' => $roleId,
                'full_name' => $fullName,
                'email' => $email,
                'cpf' => $cpf,
                'password_hash' => $passwordHash,
                'is_active' => $active ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
            throw $exception;
        }

        $userId = (int) $this->connection->lastInsertId();
        $synced = $this->syncUserPermissions($userId, $permissions, $actorId);
        $syncedGroups = $this->groups->syncUserGroups($userId, $groups, $actorId);

        $user = $this->find($userId);

        if (!$user) {
            throw new RuntimeException('Não foi possível carregar o usuário recém-criado.');
        }

        $this->logger->info('admin.user_created', [
            'user_id' => $actorId,
            'target_user_id' => $userId,
            'message' => 'Usuário criado pelo administrador.',
            'permissions' => $synced,
            'groups' => $syncedGroups,
        ]);

        return $user;
    }

    public function updateUser(
        int $userId,
        string $fullName,
        string $email,
        string $cpf,
        ?string $password,
        int $roleId,
        bool $active,
        array $permissions,
        array $groups,
        int $actorId
    ): bool {
        $fullName = $this->sanitizeFullName($fullName);
        $email = $this->normalizeEmail($email);
        $cpf = $this->normalizeCpf($cpf);

        if ($fullName === '' || $email === '' || strlen($cpf) !== 11) {
            throw new RuntimeException('Dados inválidos fornecidos para atualização de usuário.');
        }

        $fields = [
            'role_id' => $roleId,
            'full_name' => $fullName,
            'email' => $email,
            'cpf' => $cpf,
            'is_active' => $active ? 1 : 0,
        ];

        $set = 'role_id = :role_id, full_name = :full_name, email = :email, cpf = :cpf, is_active = :is_active';

        if ($password !== null && $password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $set .= ', password_hash = :password_hash';
        }

        $stmt = $this->connection->prepare("UPDATE users SET {$set} WHERE id = :id");
        $fields['id'] = $userId;

        try {
            $stmt->execute($fields);
        } catch (PDOException $exception) {
            throw $exception;
        }

        $synced = $this->syncUserPermissions($userId, $permissions, $actorId);
        $syncedGroups = $this->groups->syncUserGroups($userId, $groups, $actorId);

        $this->logger->info('admin.user_updated', [
            'user_id' => $actorId,
            'target_user_id' => $userId,
            'message' => 'Dados do usuário atualizados.',
            'permissions' => $synced,
            'groups' => $syncedGroups,
        ]);

        return true;
    }

    public function deleteUser(int $userId, int $actorId): bool
    {
        $stmt = $this->connection->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);

        if ($stmt->rowCount() > 0) {
            $this->logger->info('admin.user_deleted', [
                'user_id' => $actorId,
                'target_user_id' => $userId,
                'message' => 'Usuário removido pelo administrador.',
            ]);
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $userId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT u.id, u.full_name, u.email, u.cpf, u.role_id, u.is_active, r.name AS role '
            . 'FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $hydrated = $this->hydrateUserPermissions([$user]);

        return $hydrated[0] ?? $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hydrateUserPermissions(array $users): array
    {
        if ($users === []) {
            return [];
        }

        $userIds = [];
        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
        }

        if ($userIds === []) {
            foreach ($users as &$user) {
                $user['permissions'] = [];
                $user['permission_names'] = [];
                $user['groups'] = [];
                $user['group_ids'] = [];
            }
            unset($user);

            return $users;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->connection->prepare(
            "SELECT up.user_id, p.name, p.label
             FROM user_permissions up
             INNER JOIN permissions p ON p.id = up.permission_id
             WHERE up.user_id IN ({$placeholders})
             ORDER BY p.label ASC"
        );
        $stmt->execute(array_values($userIds));

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $targetId = (int) ($row['user_id'] ?? 0);
            if ($targetId <= 0) {
                continue;
            }
            $grouped[$targetId][] = [
                'name' => (string) ($row['name'] ?? ''),
                'label' => $row['label'] ?? $row['name'] ?? '',
            ];
        }

        $groupMap = $this->groups->mapUsersToGroups(array_values($userIds));

        foreach ($users as &$user) {
            $userId = (int) ($user['id'] ?? 0);
            $permissions = array_values($grouped[$userId] ?? []);
            $user['permissions'] = $permissions;
            $user['permission_names'] = array_map(
                static fn (array $permission): string => (string) ($permission['name'] ?? ''),
                $permissions
            );
            $userGroups = array_values($groupMap[$userId] ?? []);
            $user['groups'] = $userGroups;
            $user['group_ids'] = array_map(
                static fn (array $group): int => (int) ($group['id'] ?? 0),
                $userGroups
            );
        }
        unset($user);

        return $users;
    }

    /**
     * @return array<int, string>
     */
    private function syncUserPermissions(int $userId, array $permissions, int $actorId): array
    {
        $normalized = $this->normalizePermissionNames($permissions);
        $map = $this->ensurePermissionRecords($normalized);

        $permissionIds = [];
        foreach ($normalized as $name) {
            if (isset($map[$name])) {
                $permissionIds[] = $map[$name];
            }
        }

        $this->connection->beginTransaction();

        try {
            if ($permissionIds === []) {
                $delete = $this->connection->prepare('DELETE FROM user_permissions WHERE user_id = :user_id');
                $delete->execute(['user_id' => $userId]);
            } else {
                $placeholder = implode(',', array_fill(0, count($permissionIds), '?'));
                $delete = $this->connection->prepare(
                    "DELETE FROM user_permissions WHERE user_id = ? AND permission_id NOT IN ({$placeholder})"
                );
                $delete->execute(array_merge([$userId], $permissionIds));

                $insert = $this->connection->prepare(
                    'INSERT INTO user_permissions (user_id, permission_id, granted_by)
                     VALUES (:user_id, :permission_id, :granted_by)
                     ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), granted_at = CURRENT_TIMESTAMP'
                );

                foreach ($permissionIds as $permissionId) {
                    $insert->execute([
                        'user_id' => $userId,
                        'permission_id' => $permissionId,
                        'granted_by' => $actorId ?: null,
                    ]);
                }
            }

            $this->connection->commit();
        } catch (PDOException $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        return $normalized;
    }

    /**
     * @return array<string, int>
     */
    private function ensurePermissionRecords(array $permissionNames): array
    {
        if ($permissionNames === []) {
            return [];
        }

        $map = $this->fetchPermissionMap($permissionNames);

        foreach ($permissionNames as $name) {
            if (isset($map[$name])) {
                continue;
            }

            $label = $this->derivePermissionLabel($name);

            $stmt = $this->connection->prepare(
                'INSERT INTO permissions (name, label, description) VALUES (:name, :label, :description)'
            );
            $stmt->execute([
                'name' => $name,
                'label' => $label,
                'description' => 'Permissão configurada manualmente.',
            ]);

            $map[$name] = (int) $this->connection->lastInsertId();
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function fetchPermissionMap(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $stmt = $this->connection->prepare(
            "SELECT id, name FROM permissions WHERE name IN ({$placeholders})"
        );
        $stmt->execute($names);

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['name'], $row['id'])) {
                continue;
            }
            $map[(string) $row['name']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function normalizePermissionNames(array $permissions): array
    {
        $normalized = [];

        foreach ($permissions as $permission) {
            $slug = $this->normalizePermissionName((string) $permission);
            if ($slug === null) {
                continue;
            }
            $normalized[$slug] = $slug;
        }

        return array_values($normalized);
    }

    private function normalizePermissionName(string $permission): ?string
    {
        $trimmed = trim($permission);
        if ($trimmed === '') {
            return null;
        }

        $lower = strtolower($trimmed);
        $slug = preg_replace('/[^a-z0-9\.\-_]+/', '.', $lower) ?? '';
        $slug = trim($slug, '.-_');

        return $slug === '' ? null : $slug;
    }

    private function derivePermissionLabel(string $permission): string
    {
        $parts = preg_split('/[\.\-_]+/', $permission) ?: [];
        $parts = array_filter($parts, static fn (string $part): bool => $part !== '');

        if ($parts === []) {
            return $permission;
        }

        $formatted = array_map(
            static fn (string $part): string => mb_convert_case($part, MB_CASE_TITLE, 'UTF-8'),
            $parts
        );

        return implode(' · ', $formatted);
    }

    private static function formatRoleLabel(string $name): string
    {
        return match ($name) {
            'admin' => 'Administrador',
            'agent' => 'Atendente',
            'dev' => 'Desenvolvedor',
            'supervisor' => 'Supervisor',
            default => ucfirst($name),
        };
    }

    private function sanitizeFullName(string $name): string
    {
        $clean = trim(strip_tags($name));
        $clean = preg_replace('/\s+/u', ' ', $clean ?? '') ?? '';

        return mb_substr($clean, 0, 150);
    }

    private function normalizeEmail(string $email): string
    {
        $sanitized = filter_var(strtolower(trim($email)), FILTER_SANITIZE_EMAIL);

        return $sanitized ? substr($sanitized, 0, 190) : '';
    }

    private function normalizeCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';

        return substr($digits, 0, 11);
    }
}
