<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Support/FileLogger.php';

load_env_file();

$host = $_SERVER['HTTP_HOST'] ?? '';
$secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $host !== '' ? $host : '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

app_logger();

$requiredEnv = ['EVO_API_BASE', 'EVO_INSTANCE', 'EVO_API_KEY', 'ADMIN_USERNAME', 'ADMIN_PASSWORD'];
$missing = [];
foreach ($requiredEnv as $variable) {
    $value = env($variable);
    if ($value === null || $value === '') {
        $missing[] = $variable;
    }
}

if ($missing !== []) {
    app_logger()->warning('environment.variables.missing', ['keys' => $missing]);
}
