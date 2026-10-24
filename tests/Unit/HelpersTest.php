<?php

require_once __DIR__ . '/../../app/helpers.php';

test('env function returns default when key is missing', function () {
    expect(env('MISSING_KEY_123', 'default_value'))->toBe('default_value');
});

test('env function returns environment value when present', function () {
    $_ENV['TEST_KEY_456'] = 'test_value';
    expect(env('TEST_KEY_456'))->toBe('test_value');
});
