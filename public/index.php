<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\TicketController;
use App\Controllers\WebhookController;
use App\Services\AuthService;
use App\Services\LoggerService;
use App\Services\TicketService;
use App\Services\WebhookService;

session_start();

require __DIR__ . '/../app/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = base_path('app/' . str_replace('\\', '/', $relative) . '.php');
        if (is_file($path)) {
            require $path;
        }
    }
});

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = config('database.php');

$container = [];

$container[\PDO::class] = static function () use ($databaseConfig): \PDO {
    $dsn = sprintf(
        '%s:host=%s;port=%d;dbname=%s;charset=%s',
        $databaseConfig['driver'],
        $databaseConfig['host'],
        $databaseConfig['port'],
        $databaseConfig['database'],
        $databaseConfig['charset']
    );

    $pdo = new \PDO($dsn, $databaseConfig['username'], $databaseConfig['password'] ?? '');
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

    return $pdo;
};

$container[LoggerService::class] = static fn () => new LoggerService(resolve(\PDO::class));
$container[AuthService::class] = static fn () => new AuthService(resolve(\PDO::class), resolve(LoggerService::class));
$container[TicketService::class] = static fn () => new TicketService(resolve(\PDO::class), resolve(LoggerService::class));
$container[WebhookService::class] = static fn () => new WebhookService(resolve(\PDO::class), resolve(LoggerService::class));
$container[AuthController::class] = static fn () => new AuthController(resolve(AuthService::class));
$container[TicketController::class] = static fn () => new TicketController(resolve(TicketService::class), resolve(LoggerService::class));
$container[WebhookController::class] = static fn () => new WebhookController(resolve(WebhookService::class));

function resolve(string $abstract): mixed
{
    global $container;

    if (!array_key_exists($abstract, $container)) {
        throw new \InvalidArgumentException("Service {$abstract} is not bound in the container.");
    }

    $entry = $container[$abstract];

    if (is_callable($entry)) {
        $entry = $entry();
        $container[$abstract] = $entry;
    }

    return $entry;
}

$routes = require base_path('routes/web.php');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$matched = false;

foreach ($routes as [$httpVerb, $pattern, $handler]) {
    if (strtoupper($httpVerb) !== strtoupper($method)) {
        continue;
    }

    $regex = '#^' . preg_replace('#\{([^}/]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';

    if (!preg_match($regex, $uri, $matches)) {
        continue;
    }

    $params = array_filter(
        $matches,
        static fn ($key) => !is_int($key),
        ARRAY_FILTER_USE_KEY
    );

    $params = array_map(static fn ($value) => is_numeric($value) ? (int) $value : $value, $params);

    if (is_array($handler)) {
        [$class, $action] = $handler;
        $controller = resolve($class);
        $controller->{$action}(...array_values($params));
    } elseif (is_callable($handler)) {
        $handler(...array_values($params));
    }

    $matched = true;
    break;
}

if (!$matched) {
    http_response_code(404);
    echo '404 Not Found';
}
