<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use RuntimeException;

class ProfileController
{
    public function __construct(private UserService $userService)
    {
    }

    public function index(): void
    {
        $userSession = require_auth();
        $user = $this->userService->find((int) $userSession->id);

        if (!$user) {
            set_flash('auth_error', 'Usuário não encontrado.');
            redirect('/login');
        }

        view('profile/index', [
            'user' => $user,
            'status' => get_flash('profile_status'),
            'error' => get_flash('profile_error'),
        ]);
    }

    public function update(): void
    {
        $userSession = require_auth();
        
        $fullName = trim($_POST['full_name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $password = $_POST['password'] ?? null;
        $passwordConfirmation = $_POST['password_confirmation'] ?? null;

        $errors = [];

        if ($fullName === '') {
            $errors[] = 'Nome completo é obrigatório.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if ($password !== null && $password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'A nova senha deve possuir pelo menos 8 caracteres.';
            }
            if ($password !== $passwordConfirmation) {
                $errors[] = 'As senhas não conferem.';
            }
        }

        if ($errors !== []) {
            if (is_ajax()) {
                json_response(['errors' => $errors], 422);
            }
            set_flash('profile_error', implode(' ', $errors));
            redirect('/profile');
        }

        try {
            $this->userService->updateProfile(
                (int) $userSession->id,
                $fullName,
                $email,
                $password
            );

            // Update session data
            $_SESSION['auth_user']['full_name'] = $fullName;
            $_SESSION['auth_user']['email'] = $email;

            $message = 'Perfil atualizado com sucesso!';
            if (is_ajax()) {
                json_response(['message' => $message]);
            }

            set_flash('profile_status', $message);
            redirect('/profile');
        } catch (\Throwable $exception) {
            $message = 'Erro ao atualizar perfil: ' . $exception->getMessage();
            if (is_ajax()) {
                json_response(['error' => $message], 500);
            }
            set_flash('profile_error', $message);
            redirect('/profile');
        }
    }
}
