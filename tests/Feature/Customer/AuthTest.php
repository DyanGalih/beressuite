<?php

namespace Tests\Feature\Customer;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('customer can authenticate via customer login endpoint', function () {
    $company = Company::factory()->create();
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'email' => 'portal-customer@example.com',
        'password' => 'secret1234',
        'enable_portal' => true,
    ]);

    $response = postJson("/{$company->slug}/customer/login", [
        'email' => 'portal-customer@example.com',
        'password' => 'secret1234',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $this->assertAuthenticatedAs($customer, 'customer');
});

test('customer login fails with incorrect credentials', function () {
    $company = Company::factory()->create();
    Customer::factory()->create([
        'company_id' => $company->id,
        'email' => 'portal-customer@example.com',
        'password' => 'secret1234',
        'enable_portal' => true,
    ]);

    $response = postJson("/{$company->slug}/customer/login", [
        'email' => 'portal-customer@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('customer login rejected when portal access is disabled', function () {
    $company = Company::factory()->create();
    Customer::factory()->create([
        'company_id' => $company->id,
        'email' => 'portal-disabled@example.com',
        'password' => 'secret1234',
        'enable_portal' => false,
    ]);

    $response = postJson("/{$company->slug}/customer/login", [
        'email' => 'portal-disabled@example.com',
        'password' => 'secret1234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'Customer portal not available for this user.');
});
