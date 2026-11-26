<?php

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    $this->artisan('db:seed');

    $this->user = User::find(1);
    $this->company = $this->user->companies()->first();

    $this->withHeaders([
        'company' => $this->company->id,
    ]);

    Sanctum::actingAs($this->user, ['*']);
});

test('invoices index executes constant queries and prunes heavy columns', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    Invoice::factory()->count(10)->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
    ]);

    // Warm-up query caches
    getJson('/api/v1/invoices?page=1&limit=2');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/invoices?page=1&limit=10');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'status',
                    'paid_status',
                    'total',
                    'due_amount',
                    'customer' => ['id', 'name'],
                ],
            ],
            'meta' => [
                'current_page',
                'invoice_total_count',
            ],
        ]);

    $data = $response->json('data.0');
    expect($data)->not->toHaveKey('items')
        ->and($data)->not->toHaveKey('taxes')
        ->and($data)->not->toHaveKey('creator')
        ->and(count($queries))->toBeLessThanOrEqual(10);
});

test('estimates index executes constant queries and returns TableData schema', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    Estimate::factory()->count(10)->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
    ]);

    getJson('/api/v1/estimates?page=1&limit=2');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/estimates?page=1&limit=10');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'estimate_number',
                    'status',
                    'total',
                    'customer' => ['id', 'name'],
                ],
            ],
            'meta' => [
                'estimate_total_count',
            ],
        ]);

    $data = $response->json('data.0');
    expect($data)->not->toHaveKey('items')
        ->and(count($queries))->toBeLessThanOrEqual(10);
});

test('payments index executes constant queries and returns TableData schema', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $method = PaymentMethod::factory()->create([
        'company_id' => $this->company->id,
    ]);

    Payment::factory()->count(10)->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
        'payment_method_id' => $method->id,
    ]);

    getJson('/api/v1/payments?page=1&limit=2');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/payments?page=1&limit=10');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'payment_number',
                    'amount',
                    'customer' => ['id', 'name'],
                ],
            ],
            'meta' => [
                'payment_total_count',
            ],
        ]);

    expect(count($queries))->toBeLessThanOrEqual(10);
});

test('expenses index executes constant queries and returns TableData schema', function () {
    $category = ExpenseCategory::factory()->create([
        'company_id' => $this->company->id,
    ]);

    Expense::factory()->count(10)->create([
        'company_id' => $this->company->id,
        'expense_category_id' => $category->id,
    ]);

    getJson('/api/v1/expenses?page=1&limit=2');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/expenses?page=1&limit=10');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'amount',
                    'expense_category' => ['id', 'name'],
                ],
            ],
            'meta' => [
                'expense_total_count',
            ],
        ]);

    expect(count($queries))->toBeLessThanOrEqual(10);
});

test('customers index returns TableData schema without password fields and bounds queries', function () {
    Customer::factory()->count(10)->create([
        'company_id' => $this->company->id,
    ]);

    getJson('/api/v1/customers?page=1&limit=2');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/customers?page=1&limit=10');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
            'meta' => [
                'customer_total_count',
            ],
        ]);

    $customer = $response->json('data.0');
    expect($customer)->not->toHaveKey('password')
        ->and($customer)->not->toHaveKey('remember_token')
        ->and(count($queries))->toBeLessThanOrEqual(10);
});
