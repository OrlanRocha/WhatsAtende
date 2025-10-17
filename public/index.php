<?php

declare(strict_types=1);

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\LogController as AdminLogController;
use App\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Controllers\Admin\TicketController as AdminTicketController;
use App\Controllers\Admin\UserController as AdminUserController;
use App\Controllers\Admin\WebhookController as AdminWebhookController;
use App\Controllers\Api\EvolutionController as ApiEvolutionController;
use App\Controllers\AuthController;
use App\Controllers\TicketController;
use App\Controllers\WebhookController;
use App\Services\AuthService;
use App\Services\DashboardService;
use App\Services\EvolutionService;
use App\Services\LoggerService;
use App\Services\LoginThrottleService;
use App\Services\SettingService;
use App\Services\TemplateService;
use App\Services\TicketService;
use App\Services\UserService;
use App\Services\WebhookService;

require __DIR__ . '/../app/bootstrap.php';

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

$GLOBALS['container'] = [];

if (!function_exists('container_bind')) {
    function container_bind(string $abstract, callable|object $concrete): void
    {
        $GLOBALS['container'][$abstract] = $concrete;
    }
}

if (!function_exists('resolve')) {
    function resolve(string $abstract): mixed
    {
        if (!array_key_exists($abstract, $GLOBALS['container'])) {
            throw new \InvalidArgumentException("Service {$abstract} is not bound in the container.");
        }

        $entry = $GLOBALS['container'][$abstract];

        if (is_callable($entry)) {
            $entry = $entry();
            $GLOBALS['container'][$abstract] = $entry;
        }

        return $entry;
    }
}

container_bind(\PDO::class, static function () use ($databaseConfig): \PDO {
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
});

container_bind(LoggerService::class, static fn () => new LoggerService(resolve(\PDO::class)));
container_bind(LoginThrottleService::class, static fn () => new LoginThrottleService(resolve(\PDO::class)));
container_bind(AuthService::class, static fn () => new AuthService(
    resolve(\PDO::class),
    resolve(LoggerService::class),
    resolve(LoginThrottleService::class)
));
container_bind(DashboardService::class, static fn () => new DashboardService(resolve(\PDO::class)));
container_bind(UserService::class, static fn () => new UserService(resolve(\PDO::class), resolve(LoggerService::class)));
container_bind(TemplateService::class, static fn () => new TemplateService(resolve(\PDO::class), resolve(LoggerService::class)));
container_bind(SettingService::class, static fn () => new SettingService(resolve(\PDO::class), resolve(LoggerService::class)));
container_bind(EvolutionService::class, static fn () => new EvolutionService(resolve(SettingService::class), resolve(LoggerService::class)));
container_bind(TicketService::class, static fn () => new TicketService(
    resolve(\PDO::class),
    resolve(LoggerService::class),
    resolve(SettingService::class),
    resolve(EvolutionService::class)
));
container_bind(WebhookService::class, static fn () => new WebhookService(resolve(\PDO::class), resolve(LoggerService::class)));
container_bind(AuthController::class, static fn () => new AuthController(resolve(AuthService::class)));
container_bind(TicketController::class, static fn () => new TicketController(resolve(TicketService::class), resolve(LoggerService::class)));
container_bind(WebhookController::class, static fn () => new WebhookController(resolve(WebhookService::class), resolve(SettingService::class)));
container_bind(AdminDashboardController::class, static fn () => new AdminDashboardController(resolve(DashboardService::class), resolve(TicketService::class)));
container_bind(AdminTicketController::class, static fn () => new AdminTicketController(resolve(TicketService::class)));
container_bind(AdminUserController::class, static fn () => new AdminUserController(resolve(UserService::class)));
container_bind(AdminTemplateController::class, static fn () => new AdminTemplateController(resolve(TemplateService::class)));
container_bind(AdminLogController::class, static fn () => new AdminLogController(resolve(LoggerService::class)));
container_bind(AdminWebhookController::class, static fn () => new AdminWebhookController(resolve(SettingService::class), resolve(EvolutionService::class)));
container_bind(ApiEvolutionController::class, static fn () => new ApiEvolutionController(resolve(EvolutionService::class)));

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
