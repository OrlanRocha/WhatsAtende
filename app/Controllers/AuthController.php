<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;

class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    public function showLoginForm(): void
    {
        if (auth()) {
            redirect('/tickets');
        }

        view('auth/login', [
            'status' => get_flash('auth_status'),
            'error' => get_flash('auth_error'),
        ]);
    }

    public function login(): void
    {
        if (auth()) {
            redirect('/tickets');
        }

        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            set_flash('auth_error', 'Informe e-mail e senha.');
            redirect('/login');
        }

        $user = $this->authService->attempt($email, $password);

        if (!$user) {
            set_flash('auth_error', 'Credenciais inválidas ou usuário inativo.');
            redirect('/login');
        }

        login_user($user);
        set_flash('auth_status', 'Bem-vindo de volta, ' . $user['full_name'] . '!');
        redirect('/tickets');
    }

    public function logout(): void
    {
        logout_user();
        set_flash('auth_status', 'Sessão encerrada com sucesso.');
        redirect('/login');
    }

    public function showRegisterForm(): void
    {
        if (auth()) {
            redirect('/tickets');
        }

        view('auth/register', [
            'errors' => get_flash('register_errors') ?? [],
            'old' => get_flash('register_old') ?? [],
        ]);
    }

    public function register(): void
    {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $cpf = preg_replace('/\D+/', '', $_POST['cpf'] ?? '') ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        $errors = [];

        if ($fullName === '') {
            $errors[] = 'Nome completo é obrigatório.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }

        if (strlen($cpf) !== 11) {
            $errors[] = 'CPF deve conter 11 dígitos.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'A senha deve possuir pelo menos 8 caracteres.';
        }

        if ($password !== $passwordConfirmation) {
            $errors[] = 'As senhas não conferem.';
        }

        if ($errors !== []) {
            set_flash('register_errors', $errors);
            set_flash('register_old', [
                'full_name' => $fullName,
                'email' => $email,
                'cpf' => $cpf,
            ]);
            redirect('/register');
        }

        try {
            $user = $this->authService->register($fullName, $email, $cpf, $password);
        } catch (\Throwable $exception) {
            set_flash('register_errors', ['Não foi possível concluir o cadastro. Verifique se o e-mail ou CPF já estão em uso.']);
            set_flash('register_old', [
                'full_name' => $fullName,
                'email' => $email,
                'cpf' => $cpf,
            ]);
            redirect('/register');
        }

        login_user($user);
        set_flash('auth_status', 'Cadastro realizado com sucesso.');
        redirect('/tickets');
    }

    public function showForgotPasswordForm(): void
    {
        if (auth()) {
            redirect('/tickets');
        }

        view('auth/forgot', [
            'status' => get_flash('auth_status'),
        ]);
    }

    public function sendResetLink(): void
    {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';

        if ($email === '') {
            set_flash('auth_status', 'Se o e-mail existir em nossa base, você receberá instruções em instantes.');
            redirect('/forgot-password');
        }

        $token = $this->authService->createPasswordReset($email);

        if ($token) {
            set_flash('auth_status', 'Link de redefinição gerado. Utilize o token abaixo para continuar: ' . $token);
        } else {
            set_flash('auth_status', 'Se o e-mail existir em nossa base, você receberá instruções em instantes.');
        }

        redirect('/forgot-password');
    }

    public function showResetPasswordForm(string $token): void
    {
        $record = $this->authService->validateResetToken($token);
        if (!$record) {
            set_flash('auth_status', 'Token inválido ou expirado. Solicite uma nova redefinição.');
            redirect('/forgot-password');
        }

        view('auth/reset', [
            'token' => $token,
            'email' => $record['email'] ?? '',
        ]);
    }

    public function resetPassword(): void
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        if ($token === '' || $password === '' || $passwordConfirmation === '') {
            set_flash('auth_status', 'Preencha todos os campos.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/forgot-password');
        }

        if ($password !== $passwordConfirmation) {
            set_flash('auth_status', 'As senhas não conferem.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/forgot-password');
        }

        if (strlen($password) < 8) {
            set_flash('auth_status', 'A nova senha deve possuir ao menos 8 caracteres.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/forgot-password');
        }

        $updated = $this->authService->resetPassword($token, $password);

        if (!$updated) {
            set_flash('auth_status', 'Token inválido ou expirado. Solicite uma nova redefinição.');
            redirect('/forgot-password');
        }

        set_flash('auth_status', 'Senha redefinida com sucesso. Faça login novamente.');
        redirect('/login');
    }
}
