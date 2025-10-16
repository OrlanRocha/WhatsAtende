<?php

declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function env(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $value = getenv($key);

    return $value === false ? $default : $value;
}

function config(string $file): array
{
    $path = base_path('config/' . trim($file, '/'));

    if (!is_file($path)) {
        throw new \RuntimeException("Configuration file {$file} not found.");
    }

    /** @var array<string, mixed> $config */
    $config = require $path;

    return $config;
}

function view(string $name, array $data = []): void
{
    $viewPath = base_path('app/Views/' . str_replace(['.', '\\'], '/', $name) . '.php');

    if (!is_file($viewPath)) {
        throw new \RuntimeException("View {$name} not found.");
    }

    extract($data, EXTR_SKIP);

    require $viewPath;
}

function redirect(string $path, int $status = 302): void
{
    http_response_code($status);
    header('Location: ' . $path);
    exit;
}

function auth(): ?object
{
    if (!isset($_SESSION['auth_user'])) {
        return null;
    }

    return (object) $_SESSION['auth_user'];
}

function require_auth(): object
{
    $user = auth();

    if ($user === null) {
        set_flash('auth_error', 'Faça login para continuar.');
        redirect('/login');
    }

    return $user;
}

function has_role(string ...$roles): bool
{
    $user = auth();
    if ($user === null) {
        return false;
    }

    if ($roles === []) {
        return true;
    }

    return in_array($user->role ?? null, $roles, true);
}

function require_role(string ...$roles): object
{
    $user = require_auth();

    if ($roles !== [] && !in_array($user->role ?? null, $roles, true)) {
        http_response_code(403);
        echo 'Acesso negado.';
        exit;
    }

    return $user;
}

function login_user(array $user): void
{
    $_SESSION['auth_user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'full_name' => (string) ($user['full_name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => $user['role'] ?? null,
    ];

    session_regenerate_id(true);
}

function logout_user(): void
{
    unset($_SESSION['auth_user']);
    session_regenerate_id(true);
}

function set_flash(string $key, mixed $value): void
{
    $_SESSION['flash'][$key] = $value;
}

function get_flash(string $key, mixed $default = null): mixed
{
    if (!isset($_SESSION['flash'][$key])) {
        return $default;
    }

    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $value;
}
