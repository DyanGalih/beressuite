<?php

namespace App\Providers;

use App\Models\CompanySetting;
use App\Models\PersonalAccessToken;
use App\Models\Setting;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\CustomFieldPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\EstimatePolicy;
use App\Policies\ExchangeRateProviderPolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InvoiceExportPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ItemPolicy;
use App\Policies\ModulesPolicy;
use App\Policies\NotePolicy;
use App\Policies\OwnerPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RecurringInvoicePolicy;
use App\Policies\ReportPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingsPolicy;
use App\Policies\TaxTypePolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        Model::shouldBeStrict(! $this->app->isProduction());

        Model::handleDiscardedAttributeViolationUsing(function ($model, $keys) {
            if (str_starts_with(get_class($model), 'Silber\Bouncer\\')) {
                return;
            }

            $ignored = ['billing', 'shipping', 'taxes', 'companies', 'id'];
            $filteredKeys = array_values(array_filter($keys, function ($key) use ($model, $ignored) {
                return ! in_array($key, $ignored, true) && ! method_exists($model, $key);
            }));

            if (empty($filteredKeys)) {
                return;
            }

            throw new \Illuminate\Database\Eloquent\MassAssignmentException(
                sprintf('Add fillable property [%s] to allow mass assignment on [%s].', implode(', ', $filteredKeys), get_class($model))
            );
        });

        Model::handleMissingAttributeViolationUsing(function ($model, $key) {
            if (in_array($key, ['remember_token', 'user_id', 'facebook_id', 'google_id', 'github_id', 'reference_number'], true)) {
                return null;
            }

            throw new \Illuminate\Database\Eloquent\MissingAttributeException($model, $key);
        });

        \Silber\Bouncer\BouncerFacade::ownedVia('*', function ($model, $authority) {
            $attr = match (true) {
                isset($model->creator_id) => 'creator_id',
                isset($model->user_id) => 'user_id',
                default => null,
            };

            return $attr ? $model->getAttribute($attr) == $authority->getKey() : false;
        });

        $this->registerPoliciesAndGates();
        $this->registerEventListeners();
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

    private function registerPoliciesAndGates(): void
    {
        Gate::policy(\App\Models\Customer::class, CustomerPolicy::class);
        Gate::policy(\App\Models\Invoice::class, InvoicePolicy::class);
        Gate::policy(\App\Models\Estimate::class, EstimatePolicy::class);
        Gate::policy(\App\Models\Payment::class, PaymentPolicy::class);
        Gate::policy(\App\Models\Expense::class, ExpensePolicy::class);
        Gate::policy(\App\Models\ExpenseCategory::class, ExpenseCategoryPolicy::class);
        Gate::policy(\App\Models\PaymentMethod::class, PaymentMethodPolicy::class);
        Gate::policy(\App\Models\TaxType::class, TaxTypePolicy::class);
        Gate::policy(\App\Models\CustomField::class, CustomFieldPolicy::class);
        Gate::policy(\App\Models\User::class, UserPolicy::class);
        Gate::policy(\App\Models\Item::class, ItemPolicy::class);
        Gate::policy(\Silber\Bouncer\Database\Role::class, RolePolicy::class);
        Gate::policy(\App\Models\Unit::class, UnitPolicy::class);
        Gate::policy(\App\Models\RecurringInvoice::class, RecurringInvoicePolicy::class);
        Gate::policy(\App\Models\ExchangeRateProvider::class, ExchangeRateProviderPolicy::class);
        Gate::policy(\App\Models\InvoiceExport::class, InvoiceExportPolicy::class);

        Gate::define('create company', [CompanyPolicy::class, 'create']);
        Gate::define('transfer company ownership', [CompanyPolicy::class, 'transferOwnership']);
        Gate::define('delete company', [CompanyPolicy::class, 'delete']);

        Gate::define('manage modules', [ModulesPolicy::class, 'manageModules']);

        Gate::define('manage settings', [SettingsPolicy::class, 'manageSettings']);
        Gate::define('manage company', [SettingsPolicy::class, 'manageCompany']);
        Gate::define('manage backups', [SettingsPolicy::class, 'manageBackups']);
        Gate::define('manage file disk', [SettingsPolicy::class, 'manageFileDisk']);
        Gate::define('manage email config', [SettingsPolicy::class, 'manageEmailConfig']);
        Gate::define('manage notes', [NotePolicy::class, 'manageNotes']);
        Gate::define('view notes', [NotePolicy::class, 'viewNotes']);

        Gate::define('send invoice', [InvoicePolicy::class, 'send']);
        Gate::define('send estimate', [EstimatePolicy::class, 'send']);
        Gate::define('send payment', [PaymentPolicy::class, 'send']);

        Gate::define('delete multiple items', [ItemPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple customers', [CustomerPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple users', [UserPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple invoices', [InvoicePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple estimates', [EstimatePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple expenses', [ExpensePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple payments', [PaymentPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple recurring invoices', [RecurringInvoicePolicy::class, 'deleteMultiple']);

        Gate::define('view dashboard', [DashboardPolicy::class, 'view']);

        Gate::define('view report', [ReportPolicy::class, 'viewReport']);

        Gate::define('owner only', [OwnerPolicy::class, 'managedByOwner']);
    }

    private function registerEventListeners(): void
    {
        Event::listen(
            Registered::class,
            SendEmailVerificationNotification::class
        );
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
