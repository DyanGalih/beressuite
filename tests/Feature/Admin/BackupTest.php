<?php

use App\Jobs\CreateBackupJob;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;

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

test('a company owner cannot operate on installation backups', function () {
    Queue::fake();
    $this->getJson('/api/v1/backups')->assertForbidden();
    $this->postJson('/api/v1/backups', ['file_disk_id' => 1])->assertForbidden();
    $this->deleteJson('/api/v1/backups/local')->assertForbidden();
    $this->getJson('/api/v1/download-backup')->assertForbidden();
    Queue::assertNothingPushed();
});

test('legacy queued backup requests leave storage configuration alone', function () {
    $before = config('filesystems');
    (new CreateBackupJob(['file_disk_id' => -1]))->handle();
    expect(config('filesystems'))->toBe($before);
});

test('backup configuration builds a backup v10 job', function () {
    $job = BackupJobFactory::createFromConfig(app(Config::class));

    expect($job)->toBeInstanceOf(BackupJob::class);
});
