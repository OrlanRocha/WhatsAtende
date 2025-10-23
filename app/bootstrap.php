<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Support/FileLogger.php';

load_env_file();

$host = $_SERVER['HTTP_HOST'] ?? '';
$secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

$scheme = $secure ? 'https' : 'http';
$origin = $host !== '' ? $scheme . '://' . $host : '';

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = '';
if ($scriptName !== '') {
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir !== '/' && $dir !== '.' && $dir !== '\\') {
        $scriptDir = '/' . ltrim($dir, '/');
    }
}

$appUrl = (string) (env('APP_URL') ?? '');
if ($appUrl !== '') {
    $parsed = parse_url($appUrl);
    if (is_array($parsed)) {
        $appScheme = $parsed['scheme'] ?? null;
        $appHost = $parsed['host'] ?? null;
        if ($appScheme && $appHost) {
            $appPort = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $origin = sprintf('%s://%s%s', $appScheme, $appHost, $appPort);
        }
        if (!empty($parsed['path'])) {
            $scriptDir = '/' . ltrim($parsed['path'], '/');
        }
    }
}

$basePath = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
$GLOBALS['app_base_origin'] = rtrim($origin, '/');
$GLOBALS['app_base_path'] = $basePath;

$cookieDomain = '';
if ($host !== '') {
    $hostWithoutPort = explode(':', $host)[0] ?? '';

    if (
        $hostWithoutPort !== ''
        && strpos($hostWithoutPort, '.') !== false
        && filter_var($hostWithoutPort, FILTER_VALIDATE_IP) === false
    ) {
        $cookieDomain = $hostWithoutPort;
    }
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $cookieDomain,
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
