<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('auth reset-admin command resets admin password successfully', function () {
    $user = User::first();
    $newPassword = 'my-new-secret-password-123';

    $exitCode = Artisan::call('auth:reset-admin', [
        '--email' => $user->email,
        '--password' => $newPassword,
    ]);

    expect($exitCode)->toBe(0);

    $user->refresh();
    expect(Hash::check($newPassword, $user->password))->toBeTrue();
});
