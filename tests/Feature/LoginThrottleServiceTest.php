<?php

use App\Services\LoginThrottleService;

test('login failures are persisted and lock out after five attempts with native MySQL prepares', function (bool $expired) {
    $dsn = getenv('LOGIN_THROTTLE_TEST_DSN');
    if (!$dsn) {
        $this->markTestSkipped('Set LOGIN_THROTTLE_TEST_DSN to run the MySQL integration test.');
    }

    $pdo = new PDO($dsn, getenv('LOGIN_THROTTLE_TEST_USER') ?: 'root', getenv('LOGIN_THROTTLE_TEST_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // A session-local table shadows the application table without changing its data.
    $pdo->exec('CREATE TEMPORARY TABLE login_attempts (
        email VARCHAR(190) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempts INT NOT NULL,
        last_attempt_at DATETIME NOT NULL,
        locked_until DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY (email, ip_address)
    )');

    if ($expired) {
        $pdo->exec("INSERT INTO login_attempts VALUES ('throttle@example.test', '127.0.0.1', 4,
            '2000-01-01 00:00:00', NULL, '2000-01-01 00:00:00', '2000-01-01 00:00:00')");
    }

    $service = new LoginThrottleService($pdo);
    expect($service->registerFailure('throttle@example.test', '127.0.0.1'))->toBe([
        'locked' => false,
        'remaining' => 4,
        'retry_after' => null,
    ]);
    expect((int) $pdo->query('SELECT attempts FROM login_attempts')->fetchColumn())->toBe(1);

    for ($attempt = 2; $attempt <= 5; $attempt++) {
        $result = $service->registerFailure('throttle@example.test', '127.0.0.1');
    }

    expect($result['locked'])->toBeTrue()
        ->and($result['remaining'])->toBe(0)
        ->and($service->secondsUntilUnlock('throttle@example.test', '127.0.0.1'))->toBeGreaterThan(0);

    $service->clearAttempts('throttle@example.test', '127.0.0.1');
    expect($service->secondsUntilUnlock('throttle@example.test', '127.0.0.1'))->toBeNull();
})->with(['first failure' => false, 'expired attempts' => true]);
