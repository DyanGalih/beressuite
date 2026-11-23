<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

if (! defined('CRATER_APP_AUTOLOADER_REGISTERED')) {
    define('CRATER_APP_AUTOLOADER_REGISTERED', true);

    spl_autoload_register(function ($class) {
        if (str_starts_with($class, 'Crater\\')) {
            $target = 'App\\'.mb_substr($class, 7);
            if (class_exists($target)) {
                class_alias($target, $class);
            }
        }
    });
}

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            \Illuminate\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:180,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'bouncer' => \App\Http\Middleware\ScopeBouncer::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'customer' => \App\Http\Middleware\CustomerRedirectIfAuthenticated::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'install' => \App\Http\Middleware\InstallationMiddleware::class,
            'redirect-if-installed' => \App\Http\Middleware\RedirectIfInstalled::class,
            'redirect-if-unauthenticated' => \App\Http\Middleware\RedirectIfUnauthorized::class,
            'customer-guest' => \App\Http\Middleware\CustomerGuest::class,
            'company' => \App\Http\Middleware\CompanyMiddleware::class,
            'token-ability' => \App\Http\Middleware\EnforceTokenAbilities::class,
            'pdf-auth' => \App\Http\Middleware\PdfMiddleware::class,
            'cron-job' => \App\Http\Middleware\CronJobMiddleware::class,
            'customer-portal' => \App\Http\Middleware\CustomerPortalMiddleware::class,
        ]);
        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \App\Http\Middleware\Authenticate::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions()
    ->create();
