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
            if (is_ajax()) {
                json_response(['redirect' => '/tickets']);
            }
            redirect('/tickets');
        }

        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $password = $_POST['password'] ?? '';
        $errors = [];

        if ($email === '' || $password === '') {
            $errors[] = 'Informe e-mail e senha.';
        }

        if ($errors !== []) {
            $this->handleAuthError($errors, '/login');
        }

        $user = $this->authService->attempt($email, $password);

        if (!$user) {
            $message = $this->authService->getLastError() ?? 'Credenciais inválidas ou usuário inativo.';
            $this->handleAuthError([$message], '/login');
        }

        login_user($user);

        if (is_ajax()) {
            json_response([
                'message' => 'Bem-vindo de volta, ' . $user['full_name'] . '!',
                'redirect' => '/tickets',
            ]);
        }

        set_flash('auth_status', 'Bem-vindo de volta, ' . $user['full_name'] . '!');
        redirect('/tickets');
    }

    public function logout(): void
    {
        logout_user();

        if (is_ajax()) {
            json_response([
                'message' => 'Sessão encerrada com sucesso.',
                'redirect' => '/login',
            ]);
        }

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
            $this->handleRegisterError($errors, $fullName, $email, $cpf);
        }

        try {
            $user = $this->authService->register($fullName, $email, $cpf, $password);
        } catch (\Throwable $exception) {
            $this->handleRegisterError([
                'Não foi possível concluir o cadastro. Verifique se o e-mail ou CPF já estão em uso.',
            ], $fullName, $email, $cpf);
        }

        login_user($user);

        if (is_ajax()) {
            json_response([
                'message' => 'Cadastro realizado com sucesso.',
                'redirect' => '/tickets',
            ]);
        }

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

        if ($email !== '') {
            $this->authService->createPasswordReset($email);
        }

        $message = 'Se o e-mail existir em nossa base, você receberá instruções em instantes.';

        if (is_ajax()) {
            json_response(['message' => $message]);
        }

        set_flash('auth_status', $message);
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
            'status' => get_flash('auth_status'),
            'error' => get_flash('auth_error'),
        ]);
    }

    public function resetPassword(): void
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        $errors = [];
        if ($token === '' || $password === '' || $passwordConfirmation === '') {
            $errors[] = 'Preencha todos os campos.';
        }

        if ($password !== $passwordConfirmation) {
            $errors[] = 'As senhas não conferem.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'A nova senha deve possuir ao menos 8 caracteres.';
        }

        if ($errors !== []) {
            $this->handleAuthError($errors, $this->safeReferer('/forgot-password'));
        }

        $updated = $this->authService->resetPassword($token, $password);

        if (!$updated) {
            $this->handleAuthError(['Token inválido ou expirado. Solicite uma nova redefinição.'], '/forgot-password');
        }

        if (is_ajax()) {
            json_response([
                'message' => 'Senha redefinida com sucesso. Faça login novamente.',
                'redirect' => '/login',
            ]);
        }

        set_flash('auth_status', 'Senha redefinida com sucesso. Faça login novamente.');
        redirect('/login');
    }

    private function safeReferer(string $fallback): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer === '') {
            return $fallback;
        }

        $parts = parse_url($referer);
        if ($parts === false) {
            return $fallback;
        }

        $hostMatches = !isset($parts['host']) || (isset($_SERVER['HTTP_HOST']) && strcasecmp($parts['host'], $_SERVER['HTTP_HOST']) === 0);
        if (!$hostMatches) {
            return $fallback;
        }

        $path = $parts['path'] ?? '';
        if ($path === '' || strpos($path, '/') !== 0 || strpos($path, '//') === 0) {
            return $fallback;
        }

        $redirect = $path;

        if (isset($parts['query']) && $parts['query'] !== '') {
            $redirect .= '?' . $parts['query'];
        }

        return $redirect;
    }

    private function handleAuthError(array $errors, string $redirect): void
    {
        if (is_ajax()) {
            json_response(['errors' => $errors], 422);
        }

        set_flash('auth_error', implode(' ', $errors));
        redirect($redirect);
    }

    private function handleRegisterError(array $errors, string $fullName, string $email, string $cpf): void
    {
        if (is_ajax()) {
            json_response(['errors' => $errors], 422);
        }

        set_flash('register_errors', $errors);
        set_flash('register_old', [
            'full_name' => $fullName,
            'email' => $email,
            'cpf' => $cpf,
        ]);
        redirect('/register');
    }
}
