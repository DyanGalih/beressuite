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

test('auth reset-admin command fails when password is empty', function () {
    $exitCode = Artisan::call('auth:reset-admin', [
        '--password' => '',
    ]);

    expect($exitCode)->toBe(\Illuminate\Console\Command::FAILURE);
});

test('auth reset-admin command resets super admin when email is omitted', function () {
    $superAdmin = User::where('role', 'super admin')->first();
    $newPassword = 'super-secret-password-456';

    $exitCode = Artisan::call('auth:reset-admin', [
        '--password' => $newPassword,
    ]);

    expect($exitCode)->toBe(\Illuminate\Console\Command::SUCCESS);

    $superAdmin->refresh();
    expect(Hash::check($newPassword, $superAdmin->password))->toBeTrue();
});
