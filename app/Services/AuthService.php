<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

class AuthService
{
    private const LOCKOUT_SECONDS_DEFAULT = 900;

    private ?string $lastError = null;

    public function __construct(
        private PDO $connection,
        private LoggerService $logger,
        private LoginThrottleService $throttle
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $this->lastError = null;
        $normalizedEmail = $this->normalizeEmail($email);
        $ipAddress = $this->getClientIp();

        if ($normalizedEmail === '') {
            $this->lastError = 'Informe um e-mail válido para tentar o acesso novamente.';

            return null;
        }

        $lockSeconds = $this->throttle->secondsUntilUnlock($normalizedEmail, $ipAddress);
        if ($lockSeconds !== null) {
            $this->lastError = $this->formatLockoutMessage($lockSeconds);
            $this->logger->warning('auth.locked', [
                'ip_address' => $ipAddress,
                'email' => $normalizedEmail,
                'message' => 'Tentativa de login bloqueada por excesso de falhas.',
            ]);

            return null;
        }

        $stmt = $this->connection->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['email' => $normalizedEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $result = $this->throttle->registerFailure($normalizedEmail, $ipAddress);
            $this->lastError = $result['locked']
                ? $this->formatLockoutMessage($result['retry_after'] ?? self::LOCKOUT_SECONDS_DEFAULT)
                : 'Credenciais inválidas. Verifique seus dados e tente novamente.';

            $this->logger->warning('auth.failed', [
                'ip_address' => $ipAddress,
                'email' => $normalizedEmail,
                'message' => $this->lastError,
                'remaining_attempts' => $result['remaining'],
                'locked' => $result['locked'],
            ]);

            return null;
        }

        $this->throttle->clearAttempts($normalizedEmail, $ipAddress);

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
        $fullName = $this->sanitizeFullName($fullName);
        $normalizedEmail = $this->normalizeEmail($email);
        $cpf = preg_replace('/\D+/', '', $cpf) ?? '';

        if ($fullName === '' || $normalizedEmail === '' || strlen($cpf) !== 11) {
            throw new RuntimeException('Dados inválidos para cadastro de usuário.');
        }

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
                'email' => $normalizedEmail,
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

    private function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $value = $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $value = explode(',', $value)[0] ?? '';
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return substr($value, 0, 45);
            }
        }

        return '0.0.0.0';
    }

    private function formatLockoutMessage(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return sprintf('Muitas tentativas de login. Tente novamente em %d minuto(s).', $minutes);
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
        $normalizedEmail = $this->normalizeEmail($email);

        if ($normalizedEmail === '') {
            return null;
        }

        $stmt = $this->connection->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $normalizedEmail]);
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
