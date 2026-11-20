<?php

namespace Crater\Providers;

use Crater\Models\CompanySetting;
use Crater\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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

        Queue::before(function (): void {
            Setting::flushCache();
            CompanySetting::flushCache();
        });

        if (\Storage::disk('local')->has('database_created') && Schema::hasTable('abilities')) {
            $this->addMenus();
        }
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

    public function addMenus()
    {
        //main menu
        \Menu::make('main_menu', function ($menu) {
            foreach (config('crater.main_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        //setting menu
        \Menu::make('setting_menu', function ($menu) {
            foreach (config('crater.setting_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        \Menu::make('customer_portal_menu', function ($menu) {
            foreach (config('crater.customer_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
    }

    public function generateMenu($menu, $data)
    {
        $menu->add($data['title'], $data['link'])
            ->data('icon', $data['icon'])
            ->data('name', $data['name'])
            ->data('owner_only', $data['owner_only'])
            ->data('ability', $data['ability'])
            ->data('model', $data['model'])
            ->data('group', $data['group']);
    }

    private function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60);
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
