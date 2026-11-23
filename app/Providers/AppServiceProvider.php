<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Models\PersonalAccessToken;
use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public const HOME = '/admin/dashboard';
    public const CUSTOMER_HOME = '/customer/dashboard';

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();
        Paginator::useBootstrapThree();
        $this->loadJsonTranslationsFrom(resource_path('scripts/locales'));
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Queue::before(function (): void {
            Setting::flushCache();
            CompanySetting::flushCache();
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    private function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60);
        });

        RateLimiter::for('api-token', function (Request $request) {
            $bearer = $request->bearerToken();

            if (is_string($bearer) && $bearer !== '') {
                $token = $request->user()?->currentAccessToken();

                if ($token instanceof PersonalAccessToken && is_numeric($token->id)) {
                    $perMinute = max(1, (int) config('api_tokens.rate_limit_per_minute', 120));

                    return Limit::perMinute($perMinute)->by('api-token:'.$token->id);
                }

                $resolved = PersonalAccessToken::findToken($bearer);

                if (
                    $resolved instanceof PersonalAccessToken &&
                    is_numeric($resolved->id) &&
                    (! $resolved->expires_at || ! $resolved->expires_at->isPast())
                ) {
                    $perMinute = max(1, (int) config('api_tokens.rate_limit_per_minute', 120));

                    return Limit::perMinute($perMinute)->by('api-token:'.$resolved->id);
                }
            }

            $user = $request->user() ?: auth('sanctum')->user() ?: auth()->user();

            if ($user && (! is_string($bearer) || $bearer === '')) {
                return Limit::none();
            }

            $identifier = $user?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(180)->by('api-session:'.$identifier);
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $limits = [
                Limit::perMinute(30)->by('auth-ip:'.$request->ip()),
            ];

            foreach (['username', 'email'] as $field) {
                $account = strtolower((string) $request->input($field));

                if ($account !== '') {
                    $limits[] = Limit::perMinute(5)->by('auth-account:'.$field.':'.hash('sha256', $account));
                }
            }

            return $limits;
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $account = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('reset-account:'.hash('sha256', $account)),
                Limit::perMinute(20)->by('reset-ip:'.$request->ip()),
            ];
        });
    }
}
