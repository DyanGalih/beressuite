<?php

namespace Tests\Feature\Http;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

test('the effective HTTP pipeline preserves the project middleware order', function () {
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $names = fn ($stack) => collect($stack)->map(fn ($entry) => class_basename($entry))
        ->map(fn ($entry) => $entry === 'CorsMiddleware' ? 'HandleCors' : $entry)->values()->all();

    expect($names($kernel->getGlobalMiddleware()))->toBe([
        'PreventRequestsDuringMaintenance', 'ValidatePostSize', 'TrimStrings', 'ConvertEmptyStringsToNull',
        'TrustProxies', 'SecurityHeaders', 'HandleCors',
    ])->and($names($kernel->getMiddlewareGroups()['web']))->toBe([
        'EncryptCookies', 'AddQueuedCookiesToResponse', 'StartSession', 'ShareErrorsFromSession', 'VerifyCsrfToken', 'SubstituteBindings',
    ])->and($names($kernel->getMiddlewareGroups()['api']))->toBe([
        'EnsureFrontendRequestsAreStateful', 'throttle:180,1', 'SubstituteBindings',
    ])->and($names($kernel->getMiddlewarePriority()))->toBe([
        'EnsureFrontendRequestsAreStateful', 'StartSession', 'ShareErrorsFromSession', 'Authenticate',
        'AuthenticateSession', 'SubstituteBindings', 'Authorize',
    ]);
    $this->getJson('/api/cron')->assertUnauthorized();
    $this->postJson('/broadcasting/auth')->assertUnauthorized();
});

test('validation keeps the API errors envelope and never flashes password fields', function () {
    \Illuminate\Support\Facades\Route::post('/test-validation', function () {
        request()->validate(['email' => 'required|email']);
    })->middleware('web');
    $input = ['email' => 'invalid', 'password' => 'sample', 'password_confirmation' => 'sample'];
    $this->postJson('/test-validation', $input)->assertUnprocessable()->assertJsonStructure(['message', 'errors' => ['email']]);
    $this->post('/test-validation', $input)->assertRedirect();
    expect(session()->getOldInput())->toHaveKey('email')->not->toHaveKeys(['password', 'password_confirmation']);
});

test('an unauthenticated disk selector cannot query disks or change installation storage', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::disk('local')->put('database_created', '1');
    $before = config('filesystems');
    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->getJson('/api/v1/backups?file_disk_id=23')->assertUnauthorized();
    expect(collect(\Illuminate\Support\Facades\DB::getQueryLog())->pluck('query')->implode(' '))->not->toContain('file_disks');
    expect(config('filesystems'))->toBe($before);
});

test('every route middleware alias is registered', function () {
    $router = app('router');
    $aliases = $router->getMiddleware();
    $groups = $router->getMiddlewareGroups();

    $referencedAliases = collect($router->getRoutes())
        ->flatMap(function ($route) use ($groups) {
            return collect($route->middleware())
                ->flatMap(fn ($middleware) => $groups[$middleware] ?? [$middleware]);
        })
        ->map(fn ($middleware) => Str::before($middleware, ':'))
        ->filter(fn ($middleware) => ! class_exists($middleware))
        ->unique();

    expect($referencedAliases->diff(array_keys($aliases))->values()->all())->toBe([]);
});

test('CORS preflight respects the configured allowed origins', function () {
    config(['cors.allowed_origins' => ['https://customer.example']]);

    $response = $this->call('OPTIONS', '/api/v1/cors-check', [], [], [], [
        'HTTP_ORIGIN' => 'https://customer.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
    ]);

    $response->assertNoContent();
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://customer.example');
});

test('forwarded headers are ignored when no proxy is trusted', function () {
    config(['trustedproxy.proxies' => null]);

    $response = app(TrustProxies::class)->handle(
        Request::create('http://app.test/path', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_HOST' => 'evil.example',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
        ]),
        fn ($request) => response()->json([
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'client_ip' => $request->getClientIp(),
        ])
    );

    expect($response->getData(true))->toMatchArray([
        'host' => 'app.test',
        'scheme' => 'http',
        'client_ip' => '203.0.113.10',
    ]);
});

test('forwarded headers are ignored from a peer outside the trusted proxy set', function () {
    config(['trustedproxy.proxies' => '192.0.2.10']);

    $response = app(TrustProxies::class)->handle(
        Request::create('http://app.test/path', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.10',
            'HTTP_X_FORWARDED_HOST' => 'evil.example',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
        ]),
        fn ($request) => response()->json([
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'client_ip' => $request->getClientIp(),
        ])
    );

    expect($response->getData(true))->toMatchArray([
        'host' => 'app.test',
        'scheme' => 'http',
        'client_ip' => '203.0.113.10',
    ]);
});
