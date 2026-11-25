<?php

use App\Models\Company;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('company has many customers', function () {
    $company = Company::factory()->hasCustomers()->create();

    $this->assertTrue($company->customers()->exists());
});

test('company has many company settings', function () {
    $company = Company::factory()->hasSettings(5)->create();

    $this->assertCount(5, $company->settings);

    $this->assertTrue($company->settings()->exists());
});

test('a company belongs to many users', function () {
    $company = Company::factory()->hasUsers(5)->create();

    $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $company->users);
});

test('setupRoles provisions super admin admin and accountant roles', function () {
    $company = Company::factory()->create();
    $company->setupRoles();

    $roleNames = \Silber\Bouncer\Database\Role::withoutGlobalScopes()
        ->where('scope', $company->id)
        ->pluck('name')
        ->toArray();

    expect($roleNames)->toContain('super admin', 'admin', 'accountant');

    $accountantRole = \Silber\Bouncer\Database\Role::withoutGlobalScopes()
        ->where('scope', $company->id)
        ->where('name', 'accountant')
        ->first();

    $hasViewCustomField = $accountantRole->abilities()
        ->where('name', 'view-custom-field')
        ->exists();

    expect($hasViewCustomField)->toBeTrue();
});

test('setupRoles does not overwrite abilities on existing roles with custom title', function () {
    $company = Company::factory()->create();

    \Silber\Bouncer\BouncerFacade::scope()->to($company->id);
    $customRole = \Silber\Bouncer\Database\Role::create([
        'name' => 'accountant',
        'title' => 'Custom Accountant Title',
        'scope' => $company->id,
    ]);

    $company->setupRoles();

    $customRole->refresh();
    expect($customRole->title)->toBe('Custom Accountant Title')
        ->and($customRole->abilities()->count())->toBe(0);
});
