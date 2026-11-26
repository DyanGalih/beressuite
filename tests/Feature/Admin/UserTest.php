<?php

use App\Http\Controllers\V1\Admin\Users\UsersController;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();

    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);

    Sanctum::actingAs(
        $user,
        ['*']
    );
});

getJson('/api/v1/users')->assertOk();

test('store user using a form request', function () {
    $this->assertActionUsesFormRequest(
        UsersController::class,
        'store',
        UserRequest::class
    );
});

test('store user', function () {
    $data = [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'phone' => '1234567890',
        'password' => 'password123',
        'companies' => [
            [
                'id' => 1,
                'role' => 'admin',
            ],
        ],
    ];

    postJson('/api/v1/users', $data)->assertStatus(201);

    $this->assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'testuser@example.com',
        'phone' => '1234567890',
    ]);
});

test('get user', function () {
    $user = User::factory()->create();

    getJson("/api/v1/users/{$user->id}")->assertOk();
});

test('update user using a form request', function () {
    $this->assertActionUsesFormRequest(
        UsersController::class,
        'update',
        UserRequest::class
    );
});

test('update user', function () {
    $user = User::factory()->create();
    $user->companies()->attach(1);

    $data = [
        'name' => 'Updated User Name',
        'email' => $user->email,
        'phone' => '9876543210',
        'companies' => [
            [
                'id' => 1,
                'role' => 'admin',
            ],
        ],
    ];

    putJson("/api/v1/users/{$user->id}", $data)->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated User Name',
        'phone' => '9876543210',
    ]);
});

test('delete users', function () {
    $user = User::factory()->create();
    $user->companies()->attach(1);

    postJson('/api/v1/users/delete', [
        'users' => [$user->id],
    ])->assertOk();

    $this->assertModelMissing($user);
});

test('unauthorized user cannot list users', function () {
    $nonAdmin = User::factory()->create();
    $nonAdmin->companies()->attach(1);
    $this->withHeaders(['company' => 1]);
    Sanctum::actingAs($nonAdmin, ['*']);

    getJson('/api/v1/users')->assertStatus(403);
});

test('show user endpoint includes scoped roles matching user companies', function () {
    $targetUser = User::factory()->create();
    $company1 = \App\Models\Company::find(1);
    $company2 = \App\Models\Company::factory()->create();
    $company2->setupRoles();

    $targetUser->companies()->attach([$company1->id, $company2->id]);

    \Silber\Bouncer\BouncerFacade::scope()->to($company1->id);
    \Silber\Bouncer\BouncerFacade::assign('admin')->to($targetUser);

    \Silber\Bouncer\BouncerFacade::scope()->to($company2->id);
    \Silber\Bouncer\BouncerFacade::assign('accountant')->to($targetUser);

    $response = getJson("/api/v1/users/{$targetUser->id}")
        ->assertOk();

    $userData = $response->json('data');
    expect($userData)->toHaveKey('roles')
        ->and($userData['roles'])->not->toBeEmpty();

    $roles = collect($userData['roles']);
    $c1Role = $roles->firstWhere('scope', $company1->id);
    $c2Role = $roles->firstWhere('scope', $company2->id);

    expect($c1Role)->not->toBeNull()
        ->and($c1Role['name'])->toBe('admin');

    expect($c2Role)->not->toBeNull()
        ->and($c2Role['name'])->toBe('accountant');
});
