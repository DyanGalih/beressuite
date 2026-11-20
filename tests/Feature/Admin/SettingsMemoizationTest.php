<?php

use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Setting;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

test('CompanySetting memoizes reads per company and invalidates on write and flush', function () {
    $companyA = Company::create(['name' => 'Company A', 'slug' => 'company-a']);
    $companyB = Company::create(['name' => 'Company B', 'slug' => 'company-b']);

    CompanySetting::setSettings(['fiscal_year' => '1-12'], $companyA->id);
    CompanySetting::setSettings(['fiscal_year' => '4-3'], $companyB->id);
    CompanySetting::flushCache();

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(CompanySetting::getSetting('fiscal_year', $companyA->id))->toBe('1-12');
    expect(CompanySetting::getSetting('fiscal_year', $companyA->id))->toBe('1-12');
    expect(CompanySetting::getSetting('fiscal_year', $companyB->id))->toBe('4-3');
    expect(CompanySetting::getSetting('fiscal_year', $companyB->id))->toBe('4-3');
    expect(CompanySetting::getSetting('missing_option', $companyA->id))->toBeNull();
    expect(CompanySetting::getSetting('missing_option', $companyA->id))->toBeNull();

    // Only 3 DB queries total (Company A hit, Company B hit, Company A null miss)
    expect(count(DB::getQueryLog()))->toBe(3);

    // Updating via setSettings invalidates cache; next read reloads and memoizes
    CompanySetting::setSettings(['fiscal_year' => '7-6'], $companyA->id);

    DB::flushQueryLog();
    expect(CompanySetting::getSetting('fiscal_year', $companyA->id))->toBe('7-6');
    expect(CompanySetting::getSetting('fiscal_year', $companyA->id))->toBe('7-6');
    expect(count(DB::getQueryLog()))->toBe(1);

    // Direct model deletion invalidates cache
    CompanySetting::whereCompany($companyA->id)->whereOption('fiscal_year')->first()->delete();
    expect(CompanySetting::getSetting('fiscal_year', $companyA->id))->toBeNull();

    DB::disableQueryLog();
});

test('Setting memoizes reads including null misses and invalidates on write and flush', function () {
    Setting::setSetting('profile_complete', '1');
    Setting::flushCache();

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(Setting::getSetting('profile_complete'))->toBe('1');
    expect(Setting::getSetting('profile_complete'))->toBe('1');
    expect(Setting::getSetting('non_existent_global_key'))->toBeNull();
    expect(Setting::getSetting('non_existent_global_key'))->toBeNull();

    expect(count(DB::getQueryLog()))->toBe(2);

    Setting::setSetting('profile_complete', '0');

    DB::flushQueryLog();
    expect(Setting::getSetting('profile_complete'))->toBe('0');
    expect(Setting::getSetting('profile_complete'))->toBe('0');
    expect(count(DB::getQueryLog()))->toBe(1);

    Setting::flushCache();
    DB::flushQueryLog();
    expect(Setting::getSetting('profile_complete'))->toBe('0');
    expect(count(DB::getQueryLog()))->toBe(1);

    DB::disableQueryLog();
});

test('CompanySetting and Setting invalidate original cache keys when identity fields change', function () {
    $companyA = Company::create(['name' => 'Company Move A', 'slug' => 'company-move-a']);
    $companyB = Company::create(['name' => 'Company Move B', 'slug' => 'company-move-b']);

    CompanySetting::setSettings(['move_key' => 'alpha'], $companyA->id);
    expect(CompanySetting::getSetting('move_key', $companyA->id))->toBe('alpha');

    $row = CompanySetting::whereCompany($companyA->id)->whereOption('move_key')->firstOrFail();
    $row->company_id = $companyB->id;
    $row->option = 'moved_key';
    $row->save();

    expect(CompanySetting::getSetting('move_key', $companyA->id))->toBeNull();
    expect(CompanySetting::getSetting('moved_key', $companyB->id))->toBe('alpha');

    Setting::setSetting('old_global_opt', 'v1');
    expect(Setting::getSetting('old_global_opt'))->toBe('v1');

    $globalRow = Setting::whereOption('old_global_opt')->firstOrFail();
    $globalRow->option = 'new_global_opt';
    $globalRow->save();

    expect(Setting::getSetting('old_global_opt'))->toBeNull();
    expect(Setting::getSetting('new_global_opt'))->toBe('v1');
});

test('CompanySetting and Setting normalize boolean values consistently with database storage', function () {
    $company = Company::create(['name' => 'Bool Co', 'slug' => 'bool-co']);

    CompanySetting::setSettings(['flag_false' => false, 'flag_true' => true], $company->id);
    expect(CompanySetting::getSetting('flag_false', $company->id))->toBe('0');
    expect(CompanySetting::getSetting('flag_true', $company->id))->toBe('1');

    CompanySetting::flushCache();
    expect(CompanySetting::getSetting('flag_false', $company->id))->toBe('0');
    expect(CompanySetting::getSetting('flag_true', $company->id))->toBe('1');

    Setting::setSetting('global_flag_false', false);
    Setting::setSetting('global_flag_true', true);
    expect(Setting::getSetting('global_flag_false'))->toBe('0');
    expect(Setting::getSetting('global_flag_true'))->toBe('1');

    Setting::flushCache();
    expect(Setting::getSetting('global_flag_false'))->toBe('0');
    expect(Setting::getSetting('global_flag_true'))->toBe('1');
});

test('queue job boundary flushes both Setting and CompanySetting caches', function () {
    $company = Company::create(['name' => 'Queue Co', 'slug' => 'queue-co']);

    Setting::setSetting('queue_global_opt', 'initial');
    CompanySetting::setSettings(['queue_company_opt' => 'initial'], $company->id);

    // Prime caches
    expect(Setting::getSetting('queue_global_opt'))->toBe('initial');
    expect(CompanySetting::getSetting('queue_company_opt', $company->id))->toBe('initial');

    // Simulate out-of-band DB update from another process (bypassing Eloquent events)
    DB::table('settings')->where('option', 'queue_global_opt')->update(['value' => 'updated']);
    DB::table('company_settings')
        ->where('company_id', $company->id)
        ->where('option', 'queue_company_opt')
        ->update(['value' => 'updated']);

    // Still cached before job boundary
    expect(Setting::getSetting('queue_global_opt'))->toBe('initial');
    expect(CompanySetting::getSetting('queue_company_opt', $company->id))->toBe('initial');

    // Queue::before listener clears both caches on JobProcessing
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([]);
    Event::dispatch(new JobProcessing('sync', $job));

    expect(Setting::getSetting('queue_global_opt'))->toBe('updated');
    expect(CompanySetting::getSetting('queue_company_opt', $company->id))->toBe('updated');
});

test('rolled-back transactions do not leave uncommitted values in Setting or CompanySetting cache', function () {
    $company = Company::create(['name' => 'Rollback Co', 'slug' => 'rollback-co']);

    Setting::setSetting('tx_global_opt', 'committed');
    CompanySetting::setSettings(['tx_company_opt' => 'committed'], $company->id);

    expect(Setting::getSetting('tx_global_opt'))->toBe('committed');
    expect(CompanySetting::getSetting('tx_company_opt', $company->id))->toBe('committed');

    try {
        DB::transaction(function () use ($company): void {
            Setting::setSetting('tx_global_opt', 'uncommitted');
            CompanySetting::setSettings(['tx_company_opt' => 'uncommitted'], $company->id);

            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
        // expected rollback
    }

    expect(Setting::getSetting('tx_global_opt'))->toBe('committed');
    expect(CompanySetting::getSetting('tx_company_opt', $company->id))->toBe('committed');
});
