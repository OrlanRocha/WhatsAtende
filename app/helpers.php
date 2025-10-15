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

function auth(): object
{
    $userId = $_SESSION['user_id'] ?? 1;

    return (object) ['id' => (int) $userId];
}
