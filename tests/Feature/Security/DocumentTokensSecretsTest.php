<?php

use Crater\Http\Requests\ExchangeRateProviderRequest;
use Crater\Http\Resources\ExchangeRateProviderResource;
use Crater\Http\Resources\FileDiskResource;
use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Customer;
use Crater\Models\EmailLog;
use Crater\Models\ExchangeRateProvider;
use Crater\Models\FileDisk;
use Crater\Models\Invoice;
use Crater\Models\User;
use Crater\Traits\ExchangeRateProvidersTrait;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\get;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('exchange-rate providers are company scoped and do not serialize raw API keys', function () {
    $company = User::find(1)->companies()->first();
    $foreignCompany = Company::create([
        'name' => 'Foreign company',
        'owner_id' => User::find(1)->id,
        'slug' => 'foreign-provider-company',
        'unique_hash' => 'foreign-provider-company-hash',
    ]);
    $provider = ExchangeRateProvider::factory()->create([
        'company_id' => $company->id,
        'key' => 'company-secret',
        'currencies' => ['EUR'],
    ]);
    ExchangeRateProvider::factory()->create([
        'company_id' => $foreignCompany->id,
        'key' => 'foreign-secret',
        'currencies' => ['EUR'],
    ]);
    request()->headers->set('company', $company->id);

    expect(ExchangeRateProvider::whereCompany()->pluck('id')->all())->toBe([$provider->id]);

    $payload = (new ExchangeRateProviderResource($provider))->toArray(request());
    expect($payload)->not->toHaveKey('key')
        ->and($payload['key_configured'])->toBeTrue();
});

test('dedicated exchange-rate endpoints must use HTTPS', function () {
    $request = ExchangeRateProviderRequest::create('/api/v1/exchange-rate-providers', 'POST', [
        'driver' => 'currency_converter',
        'key' => 'provider-key',
        'driver_config' => [
            'type' => 'DEDICATED',
            'url' => 'http://provider.test',
        ],
    ]);
    $request->headers->set('company', User::find(1)->companies()->first()->id);

    expect(Validator::make($request->all(), $request->rules())->fails())->toBeTrue();

    $provider = new class () {
        use ExchangeRateProvidersTrait;

        public function endpoint($config)
        {
            return $this->getCurrencyConverterUrl($config);
        }
    };

    expect(fn () => $provider->endpoint(['type' => 'DEDICATED', 'url' => 'http://provider.test']))
        ->toThrow(RuntimeException::class);
});

test('open exchange rates use the provider token header instead of a query string', function () {
    Http::fake([
        'https://openexchangerates.org/*' => Http::response([
            'rates' => ['USD' => 1.25],
        ], 200),
    ]);

    $provider = new class () {
        use ExchangeRateProvidersTrait;
    };

    $response = $provider->getExchangeRate([
        'driver' => 'open_exchange_rate',
        'key' => 'provider-secret',
    ], 'INR', 'USD');

    expect($response->status())->toBe(200);
    Http::assertSent(function ($request) {
        return ! str_contains($request->url(), 'provider-secret')
            && $request->hasHeader('Authorization')
            && in_array('Token provider-secret', $request->header('Authorization'), true);
    });
});

test('revoked or expired email links cannot resolve a document', function () {
    $company = User::find(1)->companies()->first();
    CompanySetting::setSettings([
        'automatically_expire_public_links' => 'YES',
        'link_expiry_days' => 7,
    ], $company->id);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);
    $log = EmailLog::create([
        'from' => 'sender@example.test',
        'to' => $customer->email,
        'subject' => 'Invoice',
        'body' => 'Invoice link',
        'mailable_type' => Invoice::class,
        'mailable_id' => $invoice->id,
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ]);

    expect($log->validMailable(Invoice::class))->toBeNull();

    $log->update(['created_at' => now()]);
    $log->refresh();
    expect($log->validMailable(Invoice::class))->not->toBeNull();

    $log->revoke();
    expect($log->validMailable(Invoice::class))->toBeNull();
});

test('email link validates mailable type compatibility', function () {
    $company = User::find(1)->companies()->first();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);
    $log = EmailLog::create([
        'from' => 'sender@example.test',
        'to' => $customer->email,
        'subject' => 'Invoice',
        'body' => 'Invoice link',
        'mailable_type' => Invoice::class,
        'mailable_id' => $invoice->id,
    ]);

    expect($log->validMailable(\Crater\Models\Estimate::class))->toBeNull();
});

test('generic PDF route rejects customer authentication tokens', function () {
    $company = User::find(1)->companies()->first();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
    ]);

    Sanctum::actingAs($customer, ['*'], 'customer');

    get("/invoices/pdf/{$invoice->unique_hash}")->assertRedirect('/login');
});

test('file disk resource masks sensitive credentials', function () {
    $disk = new FileDisk([
        'name' => 'S3 Disk',
        'driver' => 's3',
        'credentials' => [
            'key' => 'aws-key-123',
            'secret' => 'super-secret-aws-key',
            'bucket' => 'my-bucket',
        ],
    ]);

    $payload = (new FileDiskResource($disk))->toArray(request());

    expect($payload['credentials']['secret'])->toBe('********')
        ->and($payload['credentials']['key'])->toBe('********')
        ->and($payload['credentials']['bucket'])->toBe('my-bucket');
});

test('mail settings endpoint masks passwords and secrets', function () {
    $user = User::find(1);
    Sanctum::actingAs($user, ['*']);

    config([
        'mail.password' => 'secret-smtp-password',
        'services.mailgun.secret' => 'secret-mailgun-key',
    ]);

    $response = $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ])->getJson('/api/v1/mail/config');

    $response->assertOk()
        ->assertJson([
            'mail_password' => '********',
            'mail_mailgun_secret' => '********',
        ]);
});

test('generic PDF route forbids access to documents of unauthorized company', function () {
    $user = User::find(1);
    Sanctum::actingAs($user, ['*']);

    $otherUser = User::factory()->create();
    $otherCompany = Company::create([
        'name' => 'Foreign PDF Company',
        'owner_id' => $otherUser->id,
        'slug' => 'foreign-pdf-company',
        'unique_hash' => 'foreign-pdf-company-hash',
    ]);

    $customer = Customer::factory()->create(['company_id' => $otherCompany->id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $otherCompany->id,
        'customer_id' => $customer->id,
    ]);

    get("/invoices/pdf/{$invoice->unique_hash}")->assertForbidden();
});

test('company logo path attribute handles absence of default file disk without crashing', function () {
    FileDisk::query()->update(['set_as_default' => false]);

    $company = Company::first();
    expect($company->logo_path)->toBeNull();
});
