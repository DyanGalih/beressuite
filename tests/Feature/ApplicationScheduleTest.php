<?php

use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\RecurringInvoice;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

test('application schedule follows installation state and each active invoice timezone', function () {
    Storage::fake('local');
    $events = function () {
        app()->forgetInstance(Schedule::class);

        return collect(app(Schedule::class)->events());
    };
    expect($events()->filter(fn ($e) => str_contains($e->command ?? '', 'check:'))->count())->toBe(0);

    $this->seed(\Database\Seeders\DatabaseSeeder::class);
    $this->seed(\Database\Seeders\DemoSeeder::class);
    RecurringInvoice::query()->where('status', 'ACTIVE')->update(['status' => 'ON_HOLD']);
    $zones = ['Europe/Paris', 'Asia/Jakarta'];
    foreach ($zones as $zone) {
        $company = Company::factory()->create();
        CompanySetting::setSettings(['time_zone' => $zone], $company->id);
        RecurringInvoice::factory()->create(['company_id' => $company->id, 'status' => 'ACTIVE', 'frequency' => '5 3 * * *']);
    }
    RecurringInvoice::factory()->create(['status' => 'ON_HOLD', 'frequency' => '5 3 * * *']);
    Storage::disk('local')->put('database_created', '1');
    $installed = $events();
    foreach (['check:invoices:status', 'check:estimates:status'] as $command) {
        expect($installed->filter(fn ($e) => str_contains($e->command ?? '', $command))->pluck('expression')->values()->all())->toBe(['0 0 * * *']);
    }
    expect($installed->whereInstanceOf(CallbackEvent::class)->where('expression', '5 3 * * *')->pluck('timezone')->all())->toEqualCanonicalizing($zones);
    Storage::disk('local')->delete('database_created');
});
