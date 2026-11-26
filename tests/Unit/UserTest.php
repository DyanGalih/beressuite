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
    config(['app.date_format' => 'd/m/Y']);
    $user = User::factory()->create([
        'created_at' => '2026-11-25 12:00:00',
    ]);

    $array = $user->toArray();

    expect($array)->toHaveKey('formattedCreatedAt')
        ->and($array['formattedCreatedAt'])->toBe('25/11/2026');
});
test('user resolves scoped assigned roles across companies via roles accessor', function () {
    $user = User::factory()->create();
    $company1 = \App\Models\Company::factory()->create();
    $company2 = \App\Models\Company::factory()->create();
    $company1->setupRoles();
    $company2->setupRoles();

    \Silber\Bouncer\BouncerFacade::scope()->to($company1->id);
    \Silber\Bouncer\BouncerFacade::assign('admin')->to($user);

    \Silber\Bouncer\BouncerFacade::scope()->to($company2->id);
    \Silber\Bouncer\BouncerFacade::assign('accountant')->to($user);

    $roles = $user->roles;

    expect($roles)->toHaveCount(2);

    $company1Role = $roles->firstWhere('scope', $company1->id);
    $company2Role = $roles->firstWhere('scope', $company2->id);

    expect($company1Role)->not->toBeNull()
        ->and($company1Role->name)->toBe('admin');

    expect($company2Role)->not->toBeNull()
        ->and($company2Role->name)->toBe('accountant');
});
