<?php

use DI\ContainerBuilder;
use App\Support\DatabaseBootstrapper;
use FastRoute\RouteCollector;
use FastRoute\Dispatcher;

test('container builds successfully and provides PDO', function () {
    // Requires composer autoload so everything is available
    require_once __DIR__ . '/../../vendor/autoload.php';

    $databaseConfig = [
        'driver'    => 'sqlite', // use sqlite for testing
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'database'  => ':memory:',
        'username'  => 'root',
        'password'  => '',
        'charset'   => 'utf8mb4',
    ];

    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection([
        'driver'    => 'sqlite',
        'database'  => ':memory:',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    $containerBuilder = new ContainerBuilder();
    $containerBuilder->useAutowiring(true);
    
    $containerBuilder->addDefinitions([
        \PDO::class => function (\Psr\Container\ContainerInterface $c) {
            $pdo = \Illuminate\Database\Capsule\Manager::connection()->getPdo();
            return $pdo;
        }
    ]);

    $container = $containerBuilder->build();

    $pdo = $container->get(\PDO::class);

    expect($pdo)->toBeInstanceOf(\PDO::class);
});

test('fastroute dispatcher builds successfully', function () {
    $routes = [
        ['GET', '/health', function () { return 'OK'; }]
    ];

    $dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) use ($routes) {
        foreach ($routes as [$httpVerb, $pattern, $handler]) {
            $r->addRoute($httpVerb, $pattern, $handler);
        }
    });

    $routeInfo = $dispatcher->dispatch('GET', '/health');

    expect($routeInfo[0])->toBe(Dispatcher::FOUND);
});
