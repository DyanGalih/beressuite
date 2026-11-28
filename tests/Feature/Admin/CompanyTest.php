<?php

use App\Http\Controllers\V1\Admin\Company\CompaniesController;
use App\Http\Requests\CompaniesRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

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

test('store user using a form request', function () {
    $this->assertActionUsesFormRequest(
        CompaniesController::class,
        'store',
        CompaniesRequest::class
    );
});

test('store company', function () {
    $company = Company::factory()->raw([
        'currency' => 12,
        'address' => [
            'country_id' => 12
        ]
    ]);

    postJson('/api/v1/companies', $company)
        ->assertStatus(201);

    $company = collect($company)
        ->only([
            'name'
        ])
        ->toArray();

    $this->assertDatabaseHas('companies', $company);
});

test('delete company', function () {
    postJson('/api/v1/companies/delete', ["xyz"])
        ->assertStatus(422);
});

test('transfer ownership', function () {
    $company = Company::factory()->create();

    $user = User::factory()->create();

    postJson('/api/v1/transfer/ownership/'.$user->id)
        ->assertOk();
});

test('get companies', function () {
    getJson('/api/v1/companies')
        ->assertOk();
});

test('bootstrap loads company address', function () {
    $company = Company::find(1);
    $company->address()->create([
        'name' => 'Main Office',
        'address_street_1' => '123 Main St',
    ]);

    $response = getJson('/api/v1/bootstrap');

    $response->assertOk()
        ->assertJsonStructure([
            'current_company' => [
                'id',
                'name',
                'address' => [
                    'name',
                    'address_street_1',
                ],
            ],
            'companies' => [
                '*' => [
                    'id',
                    'address',
                ],
            ],
        ]);
});
