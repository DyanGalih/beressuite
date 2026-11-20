<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \Crater\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
            \Illuminate\Http\Middleware\TrustProxies::class,
            \Crater\Http\Middleware\SecurityHeaders::class,
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        $middleware->group('web', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Crater\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->group('api', [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:180,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->alias([
            'auth' => \Crater\Http\Middleware\Authenticate::class,
            'bouncer' => \Crater\Http\Middleware\ScopeBouncer::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'guest' => \Crater\Http\Middleware\RedirectIfAuthenticated::class,
            'customer' => \Crater\Http\Middleware\CustomerRedirectIfAuthenticated::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'install' => \Crater\Http\Middleware\InstallationMiddleware::class,
            'redirect-if-installed' => \Crater\Http\Middleware\RedirectIfInstalled::class,
            'redirect-if-unauthenticated' => \Crater\Http\Middleware\RedirectIfUnauthorized::class,
            'customer-guest' => \Crater\Http\Middleware\CustomerGuest::class,
            'company' => \Crater\Http\Middleware\CompanyMiddleware::class,
            'token-ability' => \Crater\Http\Middleware\EnforceTokenAbilities::class,
            'pdf-auth' => \Crater\Http\Middleware\PdfMiddleware::class,
            'cron-job' => \Crater\Http\Middleware\CronJobMiddleware::class,
            'customer-portal' => \Crater\Http\Middleware\CustomerPortalMiddleware::class,
        ]);
        $middleware->priority([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Crater\Http\Middleware\Authenticate::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);
    })
    ->withExceptions()
    ->create();
