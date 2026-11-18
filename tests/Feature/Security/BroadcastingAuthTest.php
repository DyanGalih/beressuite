<?php

use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('unauthenticated request to broadcasting auth is rejected with 401', function () {
    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-user.1',
        'socket_id' => '1234.5678',
    ]);

    $response->assertStatus(401);
});

test('authenticated request to broadcasting auth allows authorized channel', function () {
    $user = User::find(1);
    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-conversation.1',
        'socket_id' => '1234.5678',
    ]);

    $response->assertSuccessful();
});
