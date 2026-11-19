<?php

use Crater\Http\Controllers\V1\Admin\Users\UsersController;
use Crater\Http\Requests\UserRequest;
use Crater\Models\User;
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
