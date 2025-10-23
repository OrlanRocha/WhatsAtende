<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use Throwable;

final class DatabaseBootstrapper
{
    private static bool $bootstrapped = false;

    public static function ensure(PDO $connection): void
    {
        if (self::$bootstrapped) {
            return;
        }

        try {
            self::ensureRoles($connection);
            self::ensureAdminAccount($connection);

            self::$bootstrapped = true;
        } catch (Throwable $exception) {
            app_logger()->error('database.bootstrap.failed', [
                'message' => $exception->getMessage(),
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
