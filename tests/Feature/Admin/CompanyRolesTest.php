<?php

use Crater\Models\Company;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

use Silber\Bouncer\Database\Role;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('company model roles accessor resolves scoped roles without global scope suppression', function () {
    $company = Company::find(1);

    expect($company->roles)->not->toBeEmpty();
    expect($company->roles->pluck('name')->toArray())->toContain('super admin');
});

test('get user companies endpoint includes populated roles array for each company', function () {
    $response = getJson('/api/v1/companies')
        ->assertOk();

    $companies = $response->json('data');
    expect($companies)->not->toBeEmpty();

    $firstCompany = $companies[0];
    expect($firstCompany)->toHaveKey('roles');
    expect($firstCompany['roles'])->not->toBeEmpty();

    $roleNames = collect($firstCompany['roles'])->pluck('name')->toArray();
    expect($roleNames)->toContain('super admin');
});

test('roles listing endpoint filtered by company_id returns company scoped roles', function () {
    $response = getJson('/api/v1/roles?company_id=1')
        ->assertOk();

    $roles = $response->json('data');
    expect($roles)->not->toBeEmpty();

    $roleNames = collect($roles)->pluck('name')->toArray();
    expect($roleNames)->toContain('super admin');
});

test('company roles strictly isolates roles across different company tenants', function () {
    $company2 = Company::factory()->create();

    // Use Bouncer scoped to company 2 to create a tenant-specific role
    \Silber\Bouncer\BouncerFacade::scope()->to($company2->id);
    $company2Role = Role::create([
        'name' => 'custom-company-2-role',
        'title' => 'Custom Company 2 Role',
    ]);

    // Restore Bouncer scope to company 1
    \Silber\Bouncer\BouncerFacade::scope()->to(1);

    $company1Roles = Company::find(1)->roles->pluck('name')->toArray();
    $company2Roles = $company2->fresh()->roles->pluck('name')->toArray();

    expect($company1Roles)->not->toContain('custom-company-2-role');
    expect($company2Roles)->toContain('custom-company-2-role');
});
