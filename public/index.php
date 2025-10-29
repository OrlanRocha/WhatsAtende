<?php

declare(strict_types=1);

use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\EvolutionController as AdminEvolutionController;
use App\Controllers\Admin\LogController as AdminLogController;
use App\Controllers\Admin\TemplateController as AdminTemplateController;
use App\Controllers\Admin\TicketController as AdminTicketController;
use App\Controllers\Admin\UserController as AdminUserController;
use App\Controllers\Admin\WebhookController as AdminWebhookController;
use App\Controllers\Admin\GroupController as AdminGroupController;
use App\Controllers\Admin\ReportController as AdminReportController;
use App\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Controllers\Api\EvolutionController as ApiEvolutionController;
use App\Controllers\Api\LogStreamController;
use App\Controllers\AuthController;
use App\Controllers\TicketController;
use App\Controllers\HealthController;
use App\Controllers\WebhookController;
use App\Controllers\FeedbackController;
use App\Services\AuthService;
use App\Services\DashboardService;
use App\Services\EvolutionService;
use App\Services\GroupService;
use App\Services\FeedbackService;
use App\Services\LoggerService;
use App\Services\LoginThrottleService;
use App\Services\SettingService;
use App\Services\TemplateService;
use App\Services\TicketService;
use App\Services\HealthService;
use App\Services\UserService;
use App\Services\WebhookService;
use App\Services\ReportService;
use App\Support\DatabaseBootstrapper;

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

    DatabaseBootstrapper::ensure($pdo);

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
container_bind(GroupService::class, static fn () => new GroupService(resolve(\PDO::class), resolve(LoggerService::class)));
container_bind(UserService::class, static fn () => new UserService(resolve(\PDO::class), resolve(LoggerService::class), resolve(GroupService::class)));
container_bind(TemplateService::class, static fn () => new TemplateService(resolve(\PDO::class), resolve(LoggerService::class)));
container_bind(SettingService::class, static fn () => new SettingService(resolve(\PDO::class), resolve(LoggerService::class)));
container_bind(EvolutionService::class, static fn () => new EvolutionService(resolve(SettingService::class), resolve(LoggerService::class)));
container_bind(ReportService::class, static fn () => new ReportService(resolve(\PDO::class)));
container_bind(HealthService::class, static fn () => new HealthService(resolve(EvolutionService::class), resolve(\PDO::class)));
container_bind(FeedbackService::class, static fn () => new FeedbackService(resolve(\PDO::class), resolve(LoggerService::class)));
container_bind(TicketService::class, static fn () => new TicketService(
    resolve(\PDO::class),
    resolve(LoggerService::class),
    resolve(SettingService::class),
    resolve(EvolutionService::class),
    resolve(GroupService::class),
    resolve(FeedbackService::class)
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
container_bind(AdminEvolutionController::class, static fn () => new AdminEvolutionController(resolve(EvolutionService::class)));
container_bind(AdminGroupController::class, static fn () => new AdminGroupController(resolve(GroupService::class)));
container_bind(AdminReportController::class, static fn () => new AdminReportController(resolve(ReportService::class), resolve(GroupService::class)));
container_bind(AdminFeedbackController::class, static fn () => new AdminFeedbackController(resolve(FeedbackService::class), resolve(GroupService::class)));
container_bind(ApiEvolutionController::class, static fn () => new ApiEvolutionController(resolve(EvolutionService::class)));
container_bind(LogStreamController::class, static fn () => new LogStreamController(resolve(LoggerService::class)));
container_bind(HealthController::class, static fn () => new HealthController(resolve(HealthService::class)));
container_bind(FeedbackController::class, static fn () => new FeedbackController(resolve(FeedbackService::class), resolve(LoggerService::class)));

if (!empty($GLOBALS['whats_missing_env']) && is_array($GLOBALS['whats_missing_env'])) {
    $missingEnv = array_values(array_unique(array_map('strval', $GLOBALS['whats_missing_env'])));
    if ($missingEnv !== []) {
        try {
            resolve(LoggerService::class)->error('environment.variables.missing', [
                'message' => 'Variáveis obrigatórias ausentes no ambiente.',
                'keys' => $missingEnv,
            ]);
        } catch (\Throwable $exception) {
            app_logger()->error('environment.variables.persist_failed', [
                'exception' => $exception->getMessage(),
                'keys' => $missingEnv,
            ]);
        }
    }
}

$routes = require base_path('routes/web.php');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$basePath = app_base_path();
if ($basePath !== '' && $basePath !== '/' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
    $uri = $uri === '' ? '/' : $uri;
}

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
