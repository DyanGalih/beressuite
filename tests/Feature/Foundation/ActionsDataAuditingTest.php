<?php

use App\Actions\Company\UpdateCompanySetting;
use App\Data\Company\CompanySettingData;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use OwenIt\Auditing\Models\Audit;
use Spatie\LaravelData\Exceptions\CannotCreateData;

beforeEach(function () {
    config(['audit.console' => true]);
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
});

test('spatie data dto validates and strongly types company setting properties', function () {
    $dto = new CompanySettingData(
        company_id: 1,
        option: 'carbon_date_format',
        value: 'Y-m-d',
    );

    expect($dto->company_id)->toBe(1)
        ->and($dto->option)->toBe('carbon_date_format')
        ->and($dto->value)->toBe('Y-m-d')
        ->and($dto->toArray())->toBe([
            'company_id' => 1,
            'option' => 'carbon_date_format',
            'value' => 'Y-m-d',
        ]);
});

test('spatie data dto can be created from array payload', function () {
    $dto = CompanySettingData::from([
        'company_id' => 2,
        'option' => 'currency',
        'value' => 'USD',
    ]);

    expect($dto->company_id)->toBe(2)
        ->and($dto->option)->toBe('currency')
        ->and($dto->value)->toBe('USD');
});

test('spatie data dto throws exception on missing required properties', function () {
    expect(fn () => CompanySettingData::from([]))->toThrow(CannotCreateData::class);
});

test('single-purpose action executes orchestration with typed dto and without http request dependency', function () {
    $company = Company::create(['name' => 'Acme Test Co', 'slug' => 'acme-test-co']);
    $action = new UpdateCompanySetting();

    $dto = new CompanySettingData(
        company_id: $company->id,
        option: 'test_action_setting',
        value: 'configured_by_action',
    );

    // Invoking action directly as single-purpose invokable object
    $setting = $action($dto);

    expect($setting)->toBeInstanceOf(CompanySetting::class)
        ->and($setting->company_id)->toBe($company->id)
        ->and($setting->option)->toBe('test_action_setting')
        ->and($setting->value)->toBe('configured_by_action');

    // Verify persisted in database
    expect(CompanySetting::getSetting('test_action_setting', $company->id))->toBe('configured_by_action');
});

test('owen-it laravel auditing automatically tracks model mutations with user attribution and state diffs', function () {
    $company = Company::create(['name' => 'Audited Co', 'slug' => 'audited-co']);

    $user = User::create([
        'name' => 'Audit Admin',
        'email' => 'audit-admin-'.uniqid().'@example.test',
        'password' => bcrypt('secretpassword'),
        'role' => 'super admin',
    ]);

    $this->actingAs($user, 'web');

    $action = new UpdateCompanySetting();

    // 1. Initial creation via Action
    $setting = $action(new CompanySettingData(
        company_id: $company->id,
        option: 'invoice_prefix',
        value: 'INV-',
    ));

    $creationAudit = Audit::where('auditable_type', CompanySetting::class)
        ->where('auditable_id', $setting->id)
        ->where('event', 'created')
        ->first();

    expect($creationAudit)->not->toBeNull()
        ->and($creationAudit->user_id)->toBe($user->id)
        ->and($creationAudit->new_values['option'])->toBe('invoice_prefix')
        ->and($creationAudit->new_values['value'])->toBe('INV-')
        ->and($creationAudit->new_values['company_id'])->toBe($company->id);

    // 2. Subsequent update via Action
    $action(new CompanySettingData(
        company_id: $company->id,
        option: 'invoice_prefix',
        value: 'BILL-',
    ));

    $updateAudit = Audit::where('auditable_type', CompanySetting::class)
        ->where('auditable_id', $setting->id)
        ->where('event', 'updated')
        ->first();

    expect($updateAudit)->not->toBeNull()
        ->and($updateAudit->user_id)->toBe($user->id)
        ->and($updateAudit->old_values['value'])->toBe('INV-')
        ->and($updateAudit->new_values['value'])->toBe('BILL-');
});

test('owen-it laravel auditing tracks model deletion', function () {
    $company = Company::create(['name' => 'Delete Co', 'slug' => 'delete-co']);
    $setting = CompanySetting::create([
        'company_id' => $company->id,
        'option' => 'delete_me',
        'value' => 'temporary',
    ]);

    $setting->delete();

    $deleteAudit = Audit::where('auditable_type', CompanySetting::class)
        ->where('auditable_id', $setting->id)
        ->where('event', 'deleted')
        ->first();

    expect($deleteAudit)->not->toBeNull()
        ->and($deleteAudit->old_values['option'])->toBe('delete_me');
});

test('auditing configuration masks sensitive attributes defined in exclude list', function () {
    $excludeList = config('audit.exclude', []);

    expect($excludeList)->toContain('password')
        ->and($excludeList)->toContain('remember_token')
        ->and($excludeList)->toContain('api_token')
        ->and($excludeList)->toContain('secret')
        ->and($excludeList)->toContain('access_token');
});
