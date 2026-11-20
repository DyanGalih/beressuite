<?php

use Crater\Models\Company;
use Crater\Models\CompanySetting;
use Crater\Models\Setting;
use Illuminate\Support\Facades\DB;

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

    // Updating via setSettings updates cache immediately
    CompanySetting::setSettings(['fiscal_year' => '7-6'], $companyA->id);

    DB::flushQueryLog();
    expect(CompanySetting::getSetting('fiscal_year', $companyA->id))->toBe('7-6');
    expect(count(DB::getQueryLog()))->toBe(0);

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
    expect(count(DB::getQueryLog()))->toBe(0);

    Setting::flushCache();
    expect(Setting::getSetting('profile_complete'))->toBe('0');
    expect(count(DB::getQueryLog()))->toBe(1);

    DB::disableQueryLog();
});
