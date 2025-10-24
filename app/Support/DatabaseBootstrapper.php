<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use Throwable;

final class DatabaseBootstrapper
{
    private const DEFAULT_PERMISSIONS = [
        'users.manage' => [
            'label' => 'Gerenciar usuários',
            'description' => 'Permite criar, editar e remover usuários.'
        ],
        'permissions.manage' => [
            'label' => 'Gerenciar permissões',
            'description' => 'Permite ajustar permissões individuais por usuário.'
        ],
        'tickets.manage' => [
            'label' => 'Gerenciar tickets',
            'description' => 'Autoriza atualizar tickets, SLA e interações.'
        ],
        'tickets.assign' => [
            'label' => 'Atribuir tickets',
            'description' => 'Permite atribuir, transferir e priorizar tickets.'
        ],
        'templates.manage' => [
            'label' => 'Gerenciar templates',
            'description' => 'Permite criar e atualizar templates compartilhados.'
        ],
        'logs.view' => [
            'label' => 'Visualizar logs',
            'description' => 'Permite acessar o monitor de logs e live tail.'
        ],
        'webhook.manage' => [
            'label' => 'Configurar webhook',
            'description' => 'Permite ajustar integrações do Evolution e webhooks.'
        ],
        'reports.view' => [
            'label' => 'Visualizar relatórios',
            'description' => 'Permite acessar dashboards, relatórios e insights.'
        ],
    ];

    private const ROLE_DEFAULT_PERMISSIONS = [
        'admin' => [
            'users.manage',
            'permissions.manage',
            'tickets.manage',
            'tickets.assign',
            'templates.manage',
            'reports.view',
        ],
        'dev' => ['*'],
    ];

    private static bool $bootstrapped = false;

    public static function ensure(PDO $connection): void
    {
        if (self::$bootstrapped) {
            return;
        }

        try {
            self::ensureLogsIndexes($connection);
            self::ensureRoles($connection);
            self::ensurePermissions($connection);
            self::ensureAdminAccount($connection);
            self::ensureRolePermissions($connection);

            self::$bootstrapped = true;
        } catch (Throwable $exception) {
            app_logger()->error('database.bootstrap.failed', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function ensureLogsIndexes(PDO $connection): void
    {
        try {
            $statement = $connection->query("SHOW INDEX FROM logs WHERE Key_name = 'idx_logs_corr'");
        } catch (PDOException) {
            return;
        }

        $indexes = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        if ($indexes === []) {
            try {
                $connection->exec('CREATE INDEX idx_logs_corr ON logs(corr_id)');
            } catch (PDOException) {
                // ignore when the index cannot be created (older MySQL versions or permissions)
            }

            return;
        }

        $isUnique = false;

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'idx_logs_corr') {
                $isUnique = ((int) ($index['Non_unique'] ?? 1)) === 0;
                break;
            }
        }

        if (!$isUnique) {
            return;
        }

        try {
            $connection->exec('ALTER TABLE logs DROP INDEX idx_logs_corr');
            $connection->exec('CREATE INDEX idx_logs_corr ON logs(corr_id)');
        } catch (PDOException) {
            // If we cannot alter the index we silently ignore so the app can keep running.
        }
    }

    private static function ensurePermissions(PDO $connection): void
    {
        $statement = $connection->prepare(
            'INSERT INTO permissions (name, label, description)
             VALUES (:name, :label, :description)
             ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description)'
        );

        foreach (self::DEFAULT_PERMISSIONS as $name => $meta) {
            $statement->execute([
                'name' => $name,
                'label' => $meta['label'],
                'description' => $meta['description'],
            ]);
        }
    }

    private static function ensureRoles(PDO $connection): void
    {
        $roles = [
            1 => 'admin',
            2 => 'agent',
            3 => 'supervisor',
            4 => 'dev',
        ];

        $statement = $connection->prepare(
            'INSERT INTO roles (id, name) VALUES (:id, :name) '
            . 'ON DUPLICATE KEY UPDATE name = VALUES(name)'
        );

        foreach ($roles as $id => $role) {
            $statement->execute([
                'id' => $id,
                'name' => $role,
            ]);
        }

        $maxId = $connection->query('SELECT MAX(id) FROM roles')->fetchColumn();
        $nextId = max((int) $maxId + 1, count($roles) + 1);

        try {
            $connection->exec('ALTER TABLE roles AUTO_INCREMENT = ' . $nextId);
        } catch (PDOException) {
            // Ignored: the table might not support altering auto-increment in this context.
        }
    }

    private static function ensureAdminAccount(PDO $connection): void
    {
        $adminEmail = strtolower(trim((string) env('ADMIN_USERNAME', '')));
        $adminPassword = (string) env('ADMIN_PASSWORD', '');

        if ($adminEmail === '' || $adminPassword === '') {
            return;
        }

        $roleId = self::lookupRoleId($connection, 'admin');
        if ($roleId === null) {
            return;
        }

        $existing = self::findUserByEmail($connection, $adminEmail);
        if ($existing !== null) {
            return;
        }

        $cpf = self::nextAvailableCpf($connection, '00000000000');
        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);

        $statement = $connection->prepare(
            'INSERT INTO users (role_id, full_name, email, cpf, password_hash, is_active) '
            . 'VALUES (:role_id, :full_name, :email, :cpf, :password_hash, 1)'
        );

        $statement->execute([
            'role_id' => $roleId,
            'full_name' => 'Administrador Master',
            'email' => $adminEmail,
            'cpf' => $cpf,
            'password_hash' => $passwordHash,
        ]);
    }

    private static function ensureRolePermissions(PDO $connection): void
    {
        foreach (self::ROLE_DEFAULT_PERMISSIONS as $roleName => $permissionList) {
            $roleId = self::lookupRoleId($connection, $roleName);
            if ($roleId === null) {
                continue;
            }

            $userStatement = $connection->prepare('SELECT id FROM users WHERE role_id = :role_id');
            $userStatement->execute(['role_id' => $roleId]);
            $userIds = array_map('intval', $userStatement->fetchAll(PDO::FETCH_COLUMN));

            if ($userIds === []) {
                continue;
            }

            $permissionNames = $permissionList === ['*']
                ? array_keys(self::DEFAULT_PERMISSIONS)
                : $permissionList;

            $permissionIds = self::lookupPermissionIds($connection, $permissionNames);
            if ($permissionIds === []) {
                continue;
            }

            $insert = $connection->prepare(
                'INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by)
                 VALUES (:user_id, :permission_id, NULL)'
            );

            foreach ($userIds as $userId) {
                foreach ($permissionIds as $permissionId) {
                    $insert->execute([
                        'user_id' => $userId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    /**
     * @param array<int, string> $names
     * @return array<int, int>
     */
    private static function lookupPermissionIds(PDO $connection, array $names): array
    {
        if ($names === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $statement = $connection->prepare(
            "SELECT id, name FROM permissions WHERE name IN ({$placeholders})"
        );
        $statement->execute($names);
        $map = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($row['name'], $row['id'])) {
                continue;
            }
            $map[$row['name']] = (int) $row['id'];
        }

        return array_values($map);
    }

    private static function lookupRoleId(PDO $connection, string $role): ?int
    {
        $statement = $connection->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $role]);
        $roleId = $statement->fetchColumn();

        return $roleId !== false ? (int) $roleId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findUserByEmail(PDO $connection, string $email): ?array
    {
        $statement = $connection->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    private static function nextAvailableCpf(PDO $connection, string $preferred): string
    {
        if (!self::cpfExists($connection, $preferred)) {
            return $preferred;
        }

        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $candidate = self::randomDigits(11);
            if (!self::cpfExists($connection, $candidate)) {
                return $candidate;
            }
        }

        throw new PDOException('Não foi possível gerar um CPF exclusivo para o administrador padrão.');
    }

    private static function cpfExists(PDO $connection, string $cpf): bool
    {
        $statement = $connection->prepare('SELECT 1 FROM users WHERE cpf = :cpf LIMIT 1');
        $statement->execute(['cpf' => $cpf]);

        return $statement->fetchColumn() !== false;
    }

    private static function randomDigits(int $length): string
    {
        $digits = '';

        for ($index = 0; $index < $length; $index++) {
            $digits .= (string) random_int(0, 9);
        }

        return $digits;
    }
}
