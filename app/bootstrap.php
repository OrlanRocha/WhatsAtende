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

$requiredEnv = [
    'APP_ENV',
    'APP_DEBUG',
    'APP_URL',
    'TIMEZONE',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'LOG_CHANNEL',
    'LOG_LEVEL',
    'EVO_API_BASE',
    'EVO_INSTANCE',
    'EVO_API_KEY',
    'WEBHOOK_URL',
    'WEBHOOK_TOKEN',
    'WEBHOOK_HMAC_SECRET',
    'REALTIME_DRIVER',
    'QUEUE_DRIVER',
    'FEATURE_FLAGS',
    'ADMIN_USERNAME',
    'ADMIN_PASSWORD',
    'DEV_USER_EMAIL',
];

$missing = [];
foreach ($requiredEnv as $variable) {
    $value = env($variable);
    if ($value === null || $value === '') {
        $missing[] = $variable;
    }
}

if ($missing !== []) {
    app_logger()->error('environment.variables.missing', ['keys' => $missing]);
    $GLOBALS['whats_missing_env'] = $missing;
}
