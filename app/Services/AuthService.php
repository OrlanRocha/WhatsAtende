<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

class AuthService
{
    public function __construct(
        private PDO $connection,
        private LoggerService $logger
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $this->connection->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        $this->logger->info('auth.login', [
            'user_id' => $user['id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        return $this->formatUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function register(string $fullName, string $email, string $cpf, string $password, ?int $roleId = null): array
    {
        $roleId ??= $this->resolveRoleId('agent');
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->prepare(
                'INSERT INTO users (role_id, full_name, email, cpf, password_hash)
                 VALUES (:role_id, :full_name, :email, :cpf, :password_hash)'
            );
            $stmt->execute([
                'role_id' => $roleId,
                'full_name' => $fullName,
                'email' => strtolower($email),
                'cpf' => $cpf,
                'password_hash' => $passwordHash,
            ]);

            $userId = (int) $this->connection->lastInsertId();
            $this->connection->commit();
        } catch (PDOException $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $user = $this->getUserById($userId);
        if (!$user) {
            throw new RuntimeException('Usuário recém-cadastrado não encontrado.');
        }

        $this->logger->info('auth.register', [
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        return $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUserById(int $userId): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ? $this->formatUser($user) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateResetToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = $this->connection->prepare(
            'SELECT pr.*, u.full_name, u.email FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = :hash AND pr.used_at IS NULL AND pr.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return null;
        }

        return $record;
    }

    public function createPasswordReset(string $email): ?string
    {
        $stmt = $this->connection->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower($email)]);
        $userId = $stmt->fetchColumn();

        if (!$userId) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

        $insert = $this->connection->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip)
             VALUES (:user_id, :token_hash, :expires_at, :requested_ip)'
        );
        $insert->execute([
            'user_id' => $userId,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'requested_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $this->logger->info('auth.password_reset_requested', [
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        return $token;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $record = $this->validateResetToken($token);
        if (!$record) {
            return false;
        }

        $this->connection->beginTransaction();

        try {
            $this->connection->prepare(
                'UPDATE users SET password_hash = :password WHERE id = :user_id'
            )->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'user_id' => $record['user_id'],
            ]);

            $this->connection->prepare(
                'UPDATE password_resets SET used_at = NOW() WHERE id = :id'
            )->execute(['id' => $record['id']]);

            $this->connection->commit();
        } catch (PDOException $exception) {
            $this->connection->rollBack();
            throw $exception;
        }

        $this->logger->info('auth.password_reset', [
            'user_id' => $record['user_id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        return true;
    }

    private function resolveRoleId(string $roleName): int
    {
        $stmt = $this->connection->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $roleName]);
        $roleId = $stmt->fetchColumn();

        if (!$roleId) {
            throw new \RuntimeException('Role not configured: ' . $roleName);
        }

        return (int) $roleId;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function formatUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'cpf' => $user['cpf'],
            'role_id' => (int) $user['role_id'],
            'role' => $user['role_name'] ?? null,
        ];
    }
}
