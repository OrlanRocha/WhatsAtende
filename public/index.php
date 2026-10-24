<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../app/bootstrap.php';

use DI\ContainerBuilder;
use FastRoute\RouteCollector;
use FastRoute\Dispatcher;
use App\Support\DatabaseBootstrapper;
use App\Services\LoggerService;

use Illuminate\Database\Capsule\Manager as Capsule;

/** @var array<string, mixed> $databaseConfig */
$databaseConfig = config('database.php');

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $databaseConfig['driver'] === 'mariadb' ? 'mysql' : $databaseConfig['driver'], // Laravel usa 'mysql' para MariaDB
    'host'      => $databaseConfig['host'],
    'port'      => $databaseConfig['port'],
    'database'  => $databaseConfig['database'],
    'username'  => $databaseConfig['username'],
    'password'  => $databaseConfig['password'] ?? '',
    'charset'   => $databaseConfig['charset'],
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$containerBuilder = new ContainerBuilder();
$containerBuilder->useAutowiring(true);

$containerBuilder->addDefinitions([
    \PDO::class => function (\Psr\Container\ContainerInterface $c) {
        $pdo = Capsule::connection()->getPdo();
        DatabaseBootstrapper::ensure($pdo);
        return $pdo;
    }
]);

$container = $containerBuilder->build();

// Register the container in GLOBALS just in case legacy procedural code relies on it 
// (though we removed the resolve() function, so we should also implement a shim for `resolve()` if used elsewhere).
// Actually, let's add a `resolve()` helper back if it's used elsewhere.
if (!function_exists('resolve')) {
    function resolve(string $abstract) {
        global $container;
        return $container->get($abstract);
    }
}

if (!empty($GLOBALS['whats_missing_env']) && is_array($GLOBALS['whats_missing_env'])) {
    $missingEnv = array_values(array_unique(array_map('strval', $GLOBALS['whats_missing_env'])));
    if ($missingEnv !== []) {
        try {
            $container->get(LoggerService::class)->error('environment.variables.missing', [
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

$dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) use ($routes) {
    foreach ($routes as [$httpVerb, $pattern, $handler]) {
        // Nikic fast route accepts {id} and translates to [^/]+.
        $r->addRoute($httpVerb, $pattern, $handler);
    }
});

$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$basePath = app_base_path();
if ($basePath !== '' && $basePath !== '/' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath));
    $uri = $uri === '' ? '/' : $uri;
}

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo '404 Not Found';
        break;
    case Dispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $routeInfo[1];
        http_response_code(405);
        echo '405 Method Not Allowed';
        break;
    case Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $container->call([$class, $method], $vars);
        } elseif (is_callable($handler)) {
            $container->call($handler, $vars);
        }
        break;
}
