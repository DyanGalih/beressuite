<?php

use Crater\Http\Middleware\VerifyCsrfToken;

test('sanctum stateful fallback includes localhost:8000 when env is unset', function () {
    $previous = getenv('SANCTUM_STATEFUL_DOMAINS');
    $previousEnv = $_ENV['SANCTUM_STATEFUL_DOMAINS'] ?? null;
    $previousServer = $_SERVER['SANCTUM_STATEFUL_DOMAINS'] ?? null;

    putenv('SANCTUM_STATEFUL_DOMAINS');
    unset($_ENV['SANCTUM_STATEFUL_DOMAINS'], $_SERVER['SANCTUM_STATEFUL_DOMAINS']);

    try {
        $config = require config_path('sanctum.php');

        expect($config['stateful'])->toContain(
            'localhost',
            'localhost:8000',
            '127.0.0.1',
            '127.0.0.1:8000',
            '::1',
        );
    } finally {
        if ($previous !== false) {
            putenv('SANCTUM_STATEFUL_DOMAINS='.$previous);
        }
        if ($previousEnv !== null) {
            $_ENV['SANCTUM_STATEFUL_DOMAINS'] = $previousEnv;
        }
        if ($previousServer !== null) {
            $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = $previousServer;
        }
    }
});

test('sanctum stateful domains honor explicit env override', function () {
    $previous = getenv('SANCTUM_STATEFUL_DOMAINS');
    $previousEnv = $_ENV['SANCTUM_STATEFUL_DOMAINS'] ?? null;
    $previousServer = $_SERVER['SANCTUM_STATEFUL_DOMAINS'] ?? null;

    putenv('SANCTUM_STATEFUL_DOMAINS=app.example.com');
    $_ENV['SANCTUM_STATEFUL_DOMAINS'] = 'app.example.com';
    $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = 'app.example.com';

    try {
        $config = require config_path('sanctum.php');

        expect($config['stateful'])->toBe(['app.example.com']);
    } finally {
        if ($previous !== false) {
            putenv('SANCTUM_STATEFUL_DOMAINS='.$previous);
        } else {
            putenv('SANCTUM_STATEFUL_DOMAINS');
        }
        if ($previousEnv !== null) {
            $_ENV['SANCTUM_STATEFUL_DOMAINS'] = $previousEnv;
        } else {
            unset($_ENV['SANCTUM_STATEFUL_DOMAINS']);
        }
        if ($previousServer !== null) {
            $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = $previousServer;
        } else {
            unset($_SERVER['SANCTUM_STATEFUL_DOMAINS']);
        }
    }
});

test('logout routes remain protected by VerifyCsrfToken', function () {
    $middleware = app(VerifyCsrfToken::class);
    $except = (new ReflectionProperty($middleware, 'except'))->getValue($middleware);

    expect($except)
        ->not->toContain('auth/logout')
        ->not->toContain('/auth/logout')
        ->not->toContain('*/customer/logout');
});
