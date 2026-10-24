<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Capsule\Manager as DB;
use PDO;
use RuntimeException;
use Throwable;

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
        $users = User::select(
            'users.id', 'users.role_id', 'users.full_name', 'users.email', 'users.cpf', 
            'users.is_active', 'users.created_at', 'users.updated_at', 'roles.name as role'
        )
        ->join('roles', 'roles.id', '=', 'users.role_id')
        ->addSelect(DB::raw('(SELECT COUNT(id) FROM tickets WHERE assigned_user_id = users.id AND status IN (\'open\', \'assigned\')) as assigned_tickets'))
        ->orderBy('users.full_name', 'asc')
        ->get()
        ->toArray();

        return $this->hydrateUserPermissions($users);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRoles(): array
    {
        $roles = Role::orderBy('name', 'asc')->get()->toArray();

        return array_map(
            static function (array $row): array {
                $name = (string) ($row['name'] ?? '');

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => $name,
                    'display_name' => self::formatRoleLabel($name),
                ];
            },
            $roles
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
        $permissions = DB::table('permissions')
            ->select('id', 'name', 'label', 'description')
            ->orderBy('label', 'asc')
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->toArray();

        return array_map(
            static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'label' => $row['label'] ?? $row['name'] ?? '',
                    'description' => $row['description'] ?? null,
                ];
            },
            $permissions
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

        try {
            $userModel = User::create([
                'role_id' => $roleId,
                'full_name' => $fullName,
                'email' => $email,
                'cpf' => $cpf,
                'password_hash' => $passwordHash,
                'is_active' => $active ? 1 : 0,
            ]);
        } catch (Throwable $exception) {
            throw $exception;
        }

        $userId = $userModel->id;
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

        if ($password !== null && $password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        try {
            User::where('id', $userId)->update($fields);
        } catch (Throwable $exception) {
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

    public function updateProfile(
        int $userId,
        string $fullName,
        string $email,
        ?string $password
    ): bool {
        $fullName = $this->sanitizeFullName($fullName);
        $email = $this->normalizeEmail($email);

        if ($fullName === '' || $email === '') {
            throw new RuntimeException('Dados inválidos fornecidos para atualização de perfil.');
        }

        $fields = [
            'full_name' => $fullName,
            'email' => $email,
        ];

        if ($password !== null && $password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        try {
            User::where('id', $userId)->update($fields);
        } catch (Throwable $exception) {
            throw $exception;
        }

        $this->logger->info('user.profile_updated', [
            'user_id' => $userId,
            'message' => 'Perfil do usuário atualizado pelo próprio.',
        ]);

        return true;
    }

    public function deleteUser(int $userId, int $actorId): bool
    {
        $deleted = User::where('id', $userId)->delete();

        if ($deleted > 0) {
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
        $user = User::select('users.id', 'users.full_name', 'users.email', 'users.cpf', 'users.role_id', 'users.is_active', 'roles.name as role')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.id', $userId)
            ->first();

        if (!$user) {
            return null;
        }

        $hydrated = $this->hydrateUserPermissions([$user->toArray()]);

        return $hydrated[0] ?? $user->toArray();
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

        $permissions = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->whereIn('user_permissions.user_id', array_values($userIds))
            ->select('user_permissions.user_id', 'permissions.name', 'permissions.label')
            ->orderBy('permissions.label', 'asc')
            ->get();

        $grouped = [];
        foreach ($permissions as $row) {
            $targetId = (int) ($row->user_id ?? 0);
            if ($targetId <= 0) {
                continue;
            }
            $grouped[$targetId][] = [
                'name' => (string) ($row->name ?? ''),
                'label' => $row->label ?? $row->name ?? '',
            ];
        }

        $groupMap = $this->groups->mapUsersToGroups(array_values($userIds));

        foreach ($users as &$user) {
            $userId = (int) ($user['id'] ?? 0);
            $userPerms = array_values($grouped[$userId] ?? []);
            $user['permissions'] = $userPerms;
            $user['permission_names'] = array_map(
                static fn (array $permission): string => (string) ($permission['name'] ?? ''),
                $userPerms
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

        DB::transaction(function () use ($userId, $permissionIds, $actorId) {
            if ($permissionIds === []) {
                DB::table('user_permissions')->where('user_id', $userId)->delete();
            } else {
                DB::table('user_permissions')
                    ->where('user_id', $userId)
                    ->whereNotIn('permission_id', $permissionIds)
                    ->delete();

                foreach ($permissionIds as $permissionId) {
                    DB::table('user_permissions')->updateOrInsert(
                        ['user_id' => $userId, 'permission_id' => $permissionId],
                        ['granted_by' => $actorId ?: null, 'granted_at' => DB::raw('CURRENT_TIMESTAMP')]
                    );
                }
            }
        });

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

            $id = DB::table('permissions')->insertGetId([
                'name' => $name,
                'label' => $label,
                'description' => 'Permissão configurada manualmente.',
            ]);

            $map[$name] = $id;
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

        $permissions = DB::table('permissions')
            ->whereIn('name', $names)
            ->select('id', 'name')
            ->get();

        $map = [];
        foreach ($permissions as $row) {
            if (!isset($row->name, $row->id)) {
                continue;
            }
            $map[(string) $row->name] = (int) $row->id;
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
