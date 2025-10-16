<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

class UserService
{
    public function __construct(private PDO $connection, private LoggerService $logger)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(): array
    {
        $stmt = $this->connection->query(
            'SELECT u.id, u.full_name, u.email, u.cpf, u.is_active, u.created_at, u.updated_at, '
            . 'r.name AS role, COUNT(t.id) AS assigned_tickets '
            . 'FROM users u '
            . 'INNER JOIN roles r ON r.id = u.role_id '
            . 'LEFT JOIN tickets t ON t.assigned_user_id = u.id AND t.status IN (\'open\', \'assigned\') '
            . 'GROUP BY u.id, u.full_name, u.email, u.cpf, u.is_active, u.created_at, u.updated_at, r.name '
            . 'ORDER BY u.full_name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRoles(): array
    {
        $stmt = $this->connection->query('SELECT id, name FROM roles ORDER BY name ASC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createUser(
        string $fullName,
        string $email,
        string $cpf,
        string $password,
        int $roleId,
        bool $active,
        int $actorId
    ): array {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->connection->prepare(
            'INSERT INTO users (role_id, full_name, email, cpf, password_hash, is_active) '
            . 'VALUES (:role_id, :full_name, :email, :cpf, :password_hash, :is_active)'
        );

        try {
            $stmt->execute([
                'role_id' => $roleId,
                'full_name' => $fullName,
                'email' => strtolower($email),
                'cpf' => $cpf,
                'password_hash' => $passwordHash,
                'is_active' => $active ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
            throw $exception;
        }

        $userId = (int) $this->connection->lastInsertId();
        $user = $this->find($userId);

        if (!$user) {
            throw new RuntimeException('Não foi possível carregar o usuário recém-criado.');
        }

        $this->logger->info('admin.user_created', [
            'user_id' => $actorId,
            'target_user_id' => $userId,
            'message' => 'Usuário criado pelo administrador.',
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
        int $actorId
    ): bool {
        $fields = [
            'role_id' => $roleId,
            'full_name' => $fullName,
            'email' => strtolower($email),
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

        $this->logger->info('admin.user_updated', [
            'user_id' => $actorId,
            'target_user_id' => $userId,
            'message' => 'Dados do usuário atualizados.',
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

        return $user ?: null;
    }
}
