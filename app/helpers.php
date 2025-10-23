<?php

declare(strict_types=1);

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__);
    return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function app_base_origin(): string
{
    return $GLOBALS['app_base_origin'] ?? '';
}

function app_base_path(): string
{
    return $GLOBALS['app_base_path'] ?? '';
}

function route_path(string $path = ''): string
{
    $basePath = app_base_path();
    $basePath = $basePath === '/' ? '' : $basePath;

    if ($path === '' || $path === '/') {
        return $basePath === '' ? '/' : rtrim($basePath, '/') . '/';
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
        return $path;
    }

    if ($basePath !== '' && (str_starts_with($path, $basePath) || str_starts_with('/' . ltrim($path, '/'), $basePath . '/'))) {
        $normalizedExisting = str_starts_with($path, '/') ? $path : '/' . ltrim($path, '/');
        return $normalizedExisting;
    }

    $normalized = '/' . ltrim($path, '/');

    if ($basePath === '' || $basePath === '/') {
        return $normalized;
    }

    return rtrim($basePath, '/') . $normalized;
}

function url(string $path = ''): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
        return $path;
    }

    $origin = rtrim(app_base_origin(), '/');
    $route = route_path($path);

    if ($origin === '') {
        return $route;
    }

    return $route === '' ? $origin : $origin . $route;
}

function asset(string $path): string
{
    return url('/' . ltrim($path, '/'));
}

function load_env_file(?string $path = null, bool $overwrite = false): void
{
    $path ??= base_path('.env');

    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if ($name === '') {
            continue;
        }

        $value = trim($value);
        if ($value !== '' && $value[0] === $value[strlen($value) - 1] && ($value[0] === '"' || $value[0] === '\'')) {
            $value = substr($value, 1, -1);
        }

        $value = str_replace(['\\n', '\\r'], ["\n", "\r"], $value);

        if (!$overwrite && array_key_exists($name, $_ENV)) {
            continue;
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function env(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $value = getenv($key);

    return $value === false ? $default : $value;
}

function app_logger(): \App\Support\FileLogger
{
    static $logger = null;

    if ($logger === null) {
        $logger = new \App\Support\FileLogger(base_path('storage/logs/app.log'));
    }

    return $logger;
}

function request_correlation_id(): string
{
    if (!isset($GLOBALS['whats_corr_id']) || !is_string($GLOBALS['whats_corr_id'])) {
        try {
            $GLOBALS['whats_corr_id'] = bin2hex(random_bytes(12));
        } catch (\Throwable) {
            $GLOBALS['whats_corr_id'] = uniqid('req_', true);
        }
    }

    return $GLOBALS['whats_corr_id'];
}

function current_route_path(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
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
    $location = url($path);
    header('Location: ' . $location);
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

function has_permission(string ...$permissions): bool
{
    $user = auth();
    if ($user === null) {
        return false;
    }

    if (($user->role ?? null) === 'dev') {
        return true;
    }

    $userPermissions = [];
    if (isset($user->permissions) && is_array($user->permissions)) {
        $userPermissions = $user->permissions;
    }

    if ($permissions === []) {
        return true;
    }

    foreach ($permissions as $permission) {
        if (in_array($permission, $userPermissions, true)) {
            return true;
        }
    }

    return false;
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

function require_permission(string ...$permissions): object
{
    $user = require_auth();

    if (($user->role ?? null) === 'dev') {
        return $user;
    }

    $userPermissions = [];
    if (isset($user->permissions) && is_array($user->permissions)) {
        $userPermissions = $user->permissions;
    }

    if ($permissions === []) {
        return $user;
    }

    foreach ($permissions as $permission) {
        if (in_array($permission, $userPermissions, true)) {
            return $user;
        }
    }

    http_response_code(403);
    echo 'Acesso negado.';
    exit;
}

function login_user(array $user): void
{
    $_SESSION['auth_user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'full_name' => (string) ($user['full_name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => $user['role'] ?? null,
        'permissions' => array_values(
            array_map(
                static fn ($permission): string => (string) $permission,
                is_array($user['permissions'] ?? null) ? $user['permissions'] : []
            )
        ),
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

function is_ajax(): bool
{
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strtolower($requestedWith) === 'xmlhttprequest') {
        return true;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains(strtolower($accept), 'application/json');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
