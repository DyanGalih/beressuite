<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->withHeaders([
        'company' => $this->user->companies()->first()->id,
    ]);
    Sanctum::actingAs($this->user, ['*']);
    $this->companyId = $this->user->companies()->first()->id;
});

test('dashboard consolidates 12-month metrics into 3 grouped queries with exact parity and tenant isolation', function () {
    CompanySetting::setSettings(['fiscal_year' => '1-12'], $this->companyId);

    $year = now()->year;
    $customer = Customer::factory()->create(['company_id' => $this->companyId]);
    $category = ExpenseCategory::factory()->create(['company_id' => $this->companyId]);

    Invoice::factory()->create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'invoice_date' => "{$year}-01-15",
        'base_total' => 10000,
        'total' => 10000,
    ]);
    Invoice::factory()->create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'invoice_date' => "{$year}-01-20",
        'base_total' => 5000,
        'total' => 5000,
    ]);
    Invoice::factory()->create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'invoice_date' => "{$year}-03-10",
        'base_total' => 8000,
        'total' => 8000,
    ]);

    Payment::create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'payment_number' => 'PAY-DASH-001',
        'payment_date' => "{$year}-01-25",
        'amount' => 12000,
        'base_amount' => 12000,
    ]);

    Expense::create([
        'company_id' => $this->companyId,
        'expense_category_id' => $category->id,
        'expense_date' => "{$year}-01-18",
        'amount' => 3000,
        'base_amount' => 3000,
    ]);

    // Previous year record must be excluded from current year totals
    Invoice::factory()->create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'invoice_date' => ($year - 1).'-02-10',
        'base_total' => 4000,
        'total' => 4000,
    ]);

    // Foreign company record must never leak
    $otherCompany = \App\Models\Company::create(['name' => 'Foreign Co', 'slug' => 'foreign-co']);
    $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);
    Invoice::factory()->create([
        'company_id' => $otherCompany->id,
        'customer_id' => $otherCustomer->id,
        'invoice_date' => "{$year}-01-15",
        'base_total' => 999999,
        'total' => 999999,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/dashboard');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk();

    // Legacy loop executed 39 chart/sum queries alone; consolidated endpoint executes far fewer total queries
    $sumQueries = collect($queries)->filter(
        fn (array $q): bool => str_contains(strtolower($q['query']), 'sum(')
    );
    expect($sumQueries->count())->toBeLessThanOrEqual(4); // 3 grouped chart sums + 1 total_amount_due

    $chart = $response->json('chart_data');
    expect($chart['months'])->toHaveCount(12);
    expect($chart['months'][0])->toBe('Jan');
    expect($chart['invoice_totals'][0])->toBe(15000);
    expect($chart['invoice_totals'][1])->toBe(0);
    expect($chart['invoice_totals'][2])->toBe(8000);
    expect($chart['receipt_totals'][0])->toBe(12000);
    expect($chart['expense_totals'][0])->toBe(3000);
    expect($chart['net_income_totals'][0])->toBe(9000);

    expect($response->json('total_sales'))->toBe(23000);
    expect($response->json('total_receipts'))->toBe(12000);
    expect($response->json('total_expenses'))->toBe(3000);
    expect($response->json('total_net_income'))->toBe(9000);

    // Previous year flag returns previous year's figures
    $prevResponse = getJson('/api/v1/dashboard?previous_year=1');
    $prevResponse->assertOk();
    expect($prevResponse->json('chart_data.invoice_totals.1'))->toBe(4000);
    expect($prevResponse->json('total_sales'))->toBe(4000);
});
