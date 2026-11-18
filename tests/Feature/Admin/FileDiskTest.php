<?php

use Crater\Models\FileDisk;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get file disks', function () {
    $response = getJson('/api/v1/disks');

    $response->assertOk();
});

test('create file disk', function () {
    $disk = FileDisk::factory()->raw();

    $response = postJson('/api/v1/disks', $disk);

    $disk['credentials'] = json_encode($disk['credentials']);
    $this->assertDatabaseHas('file_disks', $disk);
});


test('update file disk', function () {
    $disk = FileDisk::factory()->create(['company_id' => User::find(1)->companies()->first()->id]);

    $disk2 = FileDisk::factory()->raw();

    $response = putJson("/api/v1/disks/{$disk->id}", $disk2)->assertStatus(200);

    $disk2['credentials'] = json_encode($disk2['credentials']);

    $this->assertDatabaseHas('file_disks', $disk2);
});


test('get disk', function () {
    $disk = FileDisk::factory()->create(['company_id' => User::find(1)->companies()->first()->id]);

    $response = getJson("/api/v1/disks/{$disk->driver}");

    $response->assertStatus(200);
});

test('get drivers', function () {
    $response = getJson("/api/v1/disk/drivers");

    $response->assertStatus(200);
});

test('disk administration cannot see or modify another company disk', function () {
    $company = User::find(1)->companies()->first();
    $other = \Crater\Models\Company::factory()->create();
    $own = FileDisk::factory()->create(['company_id' => $company->id]);
    $foreign = FileDisk::factory()->create(['company_id' => $other->id, 'set_as_default' => true]);

    $ids = collect(getJson('/api/v1/disks?search=local&limit=all')->assertOk()->json('data'))->pluck('id');
    expect($ids)->toContain($own->id)->not->toContain($foreign->id);
    foreach ([$foreign->id, $foreign->id + 10000] as $id) {
        putJson("/api/v1/disks/{$id}", ['set_as_default' => true])->assertNotFound();
        $this->deleteJson("/api/v1/disks/{$id}")->assertNotFound();
    }
    putJson("/api/v1/disks/{$own->id}", ['set_as_default' => true])->assertOk();
    expect($own->fresh()->set_as_default)->toBeTrue()->and($foreign->fresh()->set_as_default)->toBeTrue();
});

test('consecutive company storage operations restore installation configuration', function () {
    $before = config('filesystems');
    foreach (['first', 'second'] as $root) {
        $disk = FileDisk::factory()->create([
            'company_id' => \Crater\Models\Company::factory()->create()->id,
            'credentials' => ['driver' => 'local', 'root' => storage_path($root)],
        ]);
        $disk->withStorage(function ($name) use ($root) {
            expect(config("filesystems.disks.{$name}.root"))->toBe(storage_path($root));
        });
        expect(config('filesystems'))->toBe($before);
    }
});

test('validate credentials handles unsupported drivers and does not inherit server credentials', function () {
    // Unsupported driver returns false safely without TypeError
    expect(FileDisk::validateCredentials(['some' => 'val'], 'nonexistent_driver'))->toBeFalse();

    // S3 driver does not inherit server credentials when omitted
    config(['filesystems.disks.s3.key' => 'server-secret-key']);
    config(['filesystems.disks.s3.secret' => 'server-secret-value']);

    expect(FileDisk::validateCredentials(['bucket' => 'my-bucket', 'region' => 'us-east-1'], 's3'))->toBeFalse();
});
