<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;

class LoginThrottleService
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 900; // 15 minutes
    private const LOCKOUT_SECONDS = 900; // 15 minutes

    public function __construct(private PDO $connection)
    {
    }

    public function secondsUntilUnlock(string $email, string $ip): ?int
    {
        $email = $this->normalizeEmail($email);
        $ip = $this->normalizeIp($ip);
        $record = $this->fetchRecord($email, $ip);

        if ($record === null) {
            return null;
        }

        $now = new DateTimeImmutable();

        if (!empty($record['locked_until'])) {
            $lockedUntil = new DateTimeImmutable($record['locked_until']);
            if ($lockedUntil > $now) {
                return $lockedUntil->getTimestamp() - $now->getTimestamp();
            }

            $this->clearAttempts($email, $ip);
            return null;
        }

        if ($this->isExpired($record['last_attempt_at'])) {
            $this->clearAttempts($email, $ip);
        }

        return null;
    }

    /**
     * @return array{locked: bool, remaining: int, retry_after: int|null}
     */
    public function registerFailure(string $email, string $ip): array
    {
        $email = $this->normalizeEmail($email);
        $ip = $this->normalizeIp($ip);
        $now = new DateTimeImmutable();

        $record = $this->fetchRecord($email, $ip);

        if ($record !== null && !empty($record['locked_until'])) {
            $lockedUntil = new DateTimeImmutable($record['locked_until']);
            if ($lockedUntil > $now) {
                return [
                    'locked' => true,
                    'remaining' => 0,
                    'retry_after' => $lockedUntil->getTimestamp() - $now->getTimestamp(),
                ];
            }

            $record = null;
            $this->clearAttempts($email, $ip);
        }

        if ($record === null || $this->isExpired($record['last_attempt_at'])) {
            $this->insertRecord($email, $ip, 1, $now);

            return [
                'locked' => false,
                'remaining' => self::MAX_ATTEMPTS - 1,
                'retry_after' => null,
            ];
        }

        $attempts = (int) $record['attempts'] + 1;
        $lockedUntil = null;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockedUntil = $now->modify('+' . self::LOCKOUT_SECONDS . ' seconds');
        }

        $this->updateRecord($email, $ip, $attempts, $now, $lockedUntil);

        return [
            'locked' => $lockedUntil !== null,
            'remaining' => max(0, self::MAX_ATTEMPTS - $attempts),
            'retry_after' => $lockedUntil ? $lockedUntil->getTimestamp() - $now->getTimestamp() : null,
        ];
    }

    public function clearAttempts(string $email, string $ip): void
    {
        $email = $this->normalizeEmail($email);
        $ip = $this->normalizeIp($ip);

        $stmt = $this->connection->prepare('DELETE FROM login_attempts WHERE email = :email AND ip_address = :ip');
        $stmt->execute([
            'email' => $email,
            'ip' => $ip,
        ]);
    }

    private function fetchRecord(string $email, string $ip): ?array
    {
        $stmt = $this->connection->prepare(
            'SELECT email, ip_address, attempts, last_attempt_at, locked_until '
            . 'FROM login_attempts WHERE email = :email AND ip_address = :ip LIMIT 1'
        );
        $stmt->execute([
            'email' => $email,
            'ip' => $ip,
        ]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function insertRecord(string $email, string $ip, int $attempts, DateTimeImmutable $now): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO login_attempts (email, ip_address, attempts, last_attempt_at, locked_until, created_at, updated_at) '
            . 'VALUES (:email, :ip, :attempts, :last_attempt_at, NULL, :created_at, :updated_at) '
            . 'ON DUPLICATE KEY UPDATE attempts = :attempts, last_attempt_at = :last_attempt_at, locked_until = NULL, updated_at = :updated_at'
        );

        $timestamp = $now->format('Y-m-d H:i:s');

        $stmt->execute([
            'email' => $email,
            'ip' => $ip,
            'attempts' => $attempts,
            'last_attempt_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function updateRecord(string $email, string $ip, int $attempts, DateTimeImmutable $now, ?DateTimeImmutable $lockedUntil): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE login_attempts SET attempts = :attempts, last_attempt_at = :last_attempt_at, locked_until = :locked_until, updated_at = :updated_at '
            . 'WHERE email = :email AND ip_address = :ip'
        );

        $stmt->execute([
            'attempts' => $attempts,
            'last_attempt_at' => $now->format('Y-m-d H:i:s'),
            'locked_until' => $lockedUntil?->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
            'email' => $email,
            'ip' => $ip,
        ]);
    }

    private function isExpired(string $lastAttemptAt): bool
    {
        $lastAttempt = new DateTimeImmutable($lastAttemptAt);
        $expiry = $lastAttempt->modify('+' . self::DECAY_SECONDS . ' seconds');
        $now = new DateTimeImmutable();

        return $expiry <= $now;
    }

    private function normalizeEmail(string $email): string
    {
        $normalized = strtolower(trim($email));

        return substr($normalized, 0, 190);
    }

    private function normalizeIp(string $ip): string
    {
        $candidate = trim($ip);

        if ($candidate === '') {
            return '0.0.0.0';
        }

        $parts = explode(',', $candidate);
        $first = trim($parts[0]);

        return substr($first, 0, 45);
    }
}
