<?php

use App\Models\User;
use Database\Seeders\UsersTableSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
});

test('users table seeder leaves existing user password unchanged', function () {
    $customPassword = 'CustomSecurePassword!456';

    $user = User::where('email', 'dyan.galih@gmail.com')->first();
    expect($user)->not->toBeNull();

    $user->update([
        'password' => $customPassword,
    ]);

    expect(Hash::check($customPassword, $user->fresh()->password))->toBeTrue();

    // Run the seeder
    $seeder = new UsersTableSeeder();
    $seeder->run();

    // Verify password was NOT overwritten with default beressuite@123
    $user->refresh();
    expect(Hash::check($customPassword, $user->password))->toBeTrue();
    expect(Hash::check('beressuite@123', $user->password))->toBeFalse();
});
