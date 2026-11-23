<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
});

test('runtime autoloader resolves legacy Crater namespace classes to App equivalents', function () {
    expect(class_exists('Crater\Models\User'))->toBeTrue();
    expect(class_exists('Crater\Models\Company'))->toBeTrue();
    expect(class_exists('Crater\Models\Invoice'))->toBeTrue();

    $legacyUser = new \Crater\Models\User();
    expect($legacyUser)->toBeInstanceOf(\App\Models\User::class);
});

test('polymorphic migration converts legacy Crater class discriminators to App', function () {
    $user = User::factory()->create();

    // Insert legacy Crater polymorphic record
    $tokenId = DB::table('personal_access_tokens')->insertGetId([
        'tokenable_type' => 'Crater\Models\User',
        'tokenable_id' => $user->id,
        'name' => 'Legacy Migration Token',
        'token' => hash('sha256', 'legacy-plain-token'),
        'abilities' => '["*"]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verify token can resolve its tokenable via the compatibility alias
    $tokenModel = \App\Models\PersonalAccessToken::find($tokenId);
    expect($tokenModel)->not->toBeNull();
    expect($tokenModel->tokenable)->toBeInstanceOf(\App\Models\User::class);
    expect($tokenModel->tokenable->id)->toBe($user->id);

    // Run the migration's up logic
    $migration = require database_path('migrations/2026_11_22_235900_migrate_polymorphic_types_from_crater_to_app.php');
    $migration->up();

    // Verify database value is permanently updated to App\Models\User
    $persistedType = DB::table('personal_access_tokens')->where('id', $tokenId)->value('tokenable_type');
    expect($persistedType)->toBe('App\Models\User');

    // Token still resolves cleanly with native App namespace
    $tokenModel = \App\Models\PersonalAccessToken::find($tokenId);
    expect($tokenModel->tokenable)->toBeInstanceOf(\App\Models\User::class);
    expect($tokenModel->tokenable->id)->toBe($user->id);
});
