<?php

namespace Tests\Feature\Admin;

use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('user can log in via mobile auth endpoint', function () {
    $user = User::factory()->create([
        'email' => 'mobile-user@example.com',
        'password' => 'secret1234',
    ]);

    $response = postJson('/api/v1/auth/login', [
        'username' => 'mobile-user@example.com',
        'password' => 'secret1234',
        'device_name' => 'TestDevice',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'type',
            'token',
        ]);
});

test('mobile login fails with invalid credentials', function () {
    User::factory()->create([
        'email' => 'mobile-user@example.com',
        'password' => 'secret1234',
    ]);

    $response = postJson('/api/v1/auth/login', [
        'username' => 'mobile-user@example.com',
        'password' => 'wrong-password',
        'device_name' => 'TestDevice',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('authenticated mobile user can logout and revoke token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('TestDevice');

    $response = postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    expect($user->tokens()->count())->toBe(0);
});

test('mobile auth check endpoint reports authentication status', function () {
    $user = User::where('role', 'super admin')->first();
    $company = $user->companies()->first();

    Sanctum::actingAs($user, ['*']);

    $response = $this->withHeaders(['company' => $company->id])
        ->getJson('/api/v1/auth/check');
    $response->assertOk();
});
