<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('user belongs to currency', function () {
    $user = User::factory()->create();

    $this->assertTrue($user->currency()->exists());
});

test('user belongs to many companies', function () {
    $user = User::factory()->hasCompanies(5)->create();

    $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->companies);
});

test('user serializes formattedCreatedAt cleanly without company header', function () {
    $user = User::factory()->create();

    $array = $user->toArray();

    expect($array)->toHaveKey('formattedCreatedAt')
        ->and($array['formattedCreatedAt'])->toBeString()
        ->and($array['formattedCreatedAt'])->not->toBeEmpty();
});
