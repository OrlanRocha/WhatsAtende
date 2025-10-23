<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\UserService;
use PDOException;

class UserController
{
    public function __construct(private UserService $userService)
    {
    }

    public function index(): void
    {
        require_role('admin', 'dev');

        $users = $this->userService->listUsers();

        if (is_ajax()) {
            json_response(['users' => $users]);
        }

        view('admin/users/index', [
            'users' => $users,
            'roles' => $this->userService->listRoles(),
            'permissions' => $this->userService->listPermissions(),
            'status' => get_flash('admin_status'),
            'error' => get_flash('admin_error'),
        ]);
    }

    public function create(): void
    {
        require_role('admin', 'dev');

        view('admin/users/form', [
            'roles' => $this->userService->listRoles(),
            'permissions' => $this->userService->listPermissions(),
            'errors' => get_flash('user_form_errors') ?? [],
            'old' => get_flash('user_form_old') ?? [],
            'action' => 'create',
        ]);
    }

    public function store(): void
    {
        $admin = require_role('admin', 'dev');
        $isAjax = is_ajax();

        $fullName = trim($_POST['full_name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $cpf = preg_replace('/\D+/', '', $_POST['cpf'] ?? '') ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $active = isset($_POST['is_active']) ? (bool) (int) $_POST['is_active'] : true;
        $permissions = $this->collectPermissionsFromRequest();

        $errors = $this->validateUserForm($fullName, $email, $cpf, $password, $passwordConfirmation, true);

        if ($roleId <= 0) {
            $errors[] = 'Selecione um perfil válido.';
        }

        if ($errors !== []) {
            $this->handleUserFormError($errors, [
                'full_name' => $fullName,
                'email' => $email,
                'cpf' => $cpf,
                'role_id' => $roleId,
                'is_active' => $active ? 1 : 0,
                'permissions' => $permissions,
                'custom_permissions' => $_POST['custom_permissions'] ?? '',
            ], '/admin/users/create', $isAjax);
        }

        try {
            $user = $this->userService->createUser(
                $fullName,
                $email,
                $cpf,
                $password,
                $roleId,
                $active,
                $permissions,
                (int) $admin->id
            );
        } catch (PDOException $exception) {
            $errors[] = 'Não foi possível criar o usuário. Verifique se e-mail ou CPF já estão cadastrados.';
            $this->handleUserFormError($errors, [
                'full_name' => $fullName,
                'email' => $email,
                'cpf' => $cpf,
                'role_id' => $roleId,
                'is_active' => $active ? 1 : 0,
                'permissions' => $permissions,
                'custom_permissions' => $_POST['custom_permissions'] ?? '',
            ], '/admin/users/create', $isAjax);
        }

        if ($isAjax) {
            json_response([
                'message' => 'Usuário criado com sucesso.',
                'user' => $user,
            ]);
        }

        set_flash('admin_status', 'Usuário criado com sucesso.');
        redirect('/admin/users');
    }

    public function edit(int $userId): void
    {
        require_role('admin', 'dev');

        $user = $this->userService->find($userId);
        if (!$user) {
            http_response_code(404);
            echo 'Usuário não encontrado.';
            return;
        }

        view('admin/users/form', [
            'roles' => $this->userService->listRoles(),
            'permissions' => $this->userService->listPermissions(),
            'errors' => get_flash('user_form_errors') ?? [],
            'old' => get_flash('user_form_old') ?? $user,
            'action' => 'edit',
            'userId' => $userId,
        ]);
    }

    public function update(int $userId): void
    {
        $admin = require_role('admin', 'dev');
        $isAjax = is_ajax();

        $fullName = trim($_POST['full_name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $cpf = preg_replace('/\D+/', '', $_POST['cpf'] ?? '') ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $active = isset($_POST['is_active']) ? ((int) $_POST['is_active'] === 1) : true;
        $permissions = $this->collectPermissionsFromRequest();

        $errors = $this->validateUserForm($fullName, $email, $cpf, $password, $passwordConfirmation, false);

        if ($roleId <= 0) {
            $errors[] = 'Selecione um perfil válido.';
        }

        if ($errors !== []) {
            $this->handleUserFormError($errors, [
                'full_name' => $fullName,
                'email' => $email,
                'cpf' => $cpf,
                'role_id' => $roleId,
                'is_active' => $active,
                'permissions' => $permissions,
                'custom_permissions' => $_POST['custom_permissions'] ?? '',
            ], '/admin/users/' . $userId . '/edit', $isAjax);
        }

        try {
            $this->userService->updateUser(
                $userId,
                $fullName,
                $email,
                $cpf,
                $password === '' ? null : $password,
                $roleId,
                $active,
                $permissions,
                (int) $admin->id
            );
        } catch (PDOException $exception) {
            $errors[] = 'Não foi possível atualizar o usuário. Verifique se e-mail ou CPF já estão cadastrados.';
            $this->handleUserFormError($errors, [
                'full_name' => $fullName,
                'email' => $email,
                'cpf' => $cpf,
                'role_id' => $roleId,
                'is_active' => $active,
                'permissions' => $permissions,
                'custom_permissions' => $_POST['custom_permissions'] ?? '',
            ], '/admin/users/' . $userId . '/edit', $isAjax);
        }

        if ($isAjax) {
            $user = $this->userService->find($userId);
            json_response([
                'message' => 'Usuário atualizado com sucesso.',
                'user' => $user,
            ]);
        }

        set_flash('admin_status', 'Usuário atualizado com sucesso.');
        redirect('/admin/users');
    }

    public function destroy(int $userId): void
    {
        $admin = require_role('admin', 'dev');
        $isAjax = is_ajax();

        if ((int) $admin->id === $userId) {
            if ($isAjax) {
                json_response(['message' => 'Você não pode remover sua própria conta.'], 422);
            }

            set_flash('admin_error', 'Você não pode remover sua própria conta.');
            redirect('/admin/users');
        }

        $this->userService->deleteUser($userId, (int) $admin->id);

        if ($isAjax) {
            json_response(['message' => 'Usuário removido com sucesso.']);
        }

        set_flash('admin_status', 'Usuário removido com sucesso.');
        redirect('/admin/users');
    }

    /**
     * @return array<int, string>
     */
    private function collectPermissionsFromRequest(): array
    {
        $permissions = [];

        $selected = $_POST['permissions'] ?? [];
        if (is_array($selected)) {
            foreach ($selected as $permission) {
                if (is_string($permission) && $permission !== '') {
                    $permissions[] = $permission;
                }
            }
        }

        $custom = $_POST['custom_permissions'] ?? '';
        if (is_string($custom) && $custom !== '') {
            $parts = preg_split('/[\s,;\n]+/', $custom) ?: [];
            foreach ($parts as $part) {
                $slug = trim((string) $part);
                if ($slug !== '') {
                    $permissions[] = $slug;
                }
            }
        }

        return $permissions;
    }

    private function validateUserForm(
        string $fullName,
        string $email,
        string $cpf,
        string $password,
        string $passwordConfirmation,
        bool $isCreation
    ): array {
        $errors = [];

        if ($fullName === '') {
            $errors[] = 'Informe o nome completo.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if (strlen($cpf) !== 11) {
            $errors[] = 'CPF deve conter 11 dígitos.';
        }

        if ($isCreation || $password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
            }

            if ($password !== $passwordConfirmation) {
                $errors[] = 'A confirmação de senha não confere.';
            }
        }

        return $errors;
    }

    private function handleUserFormError(array $errors, array $old, string $redirectTo, bool $ajax): void
    {
        if ($ajax) {
            json_response(['errors' => $errors], 422);
        }

        set_flash('user_form_errors', $errors);
        set_flash('user_form_old', $old);
        redirect($redirectTo);
    }
}
