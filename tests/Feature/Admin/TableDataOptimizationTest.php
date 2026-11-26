<?php

use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RecurringInvoice;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\Unit;
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

    // Warm-up query caches and verify limit=2 count
    $res2 = getJson('/api/v1/invoices?page=1&limit=2');
    $res2->assertOk()->assertJsonCount(2, 'data');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/invoices?page=1&limit=10');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'status',
                    'paid_status',
                    'total',
                    'due_amount',
                    'customer' => ['id', 'name', 'email'],
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

test('table list endpoints handle limit=all collection mapping without exception', function () {
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    Invoice::factory()->count(2)->create(['company_id' => $this->company->id, 'customer_id' => $customer->id]);
    Estimate::factory()->count(2)->create(['company_id' => $this->company->id, 'customer_id' => $customer->id]);
    Payment::factory()->count(2)->create(['company_id' => $this->company->id, 'customer_id' => $customer->id]);
    Expense::factory()->count(2)->create(['company_id' => $this->company->id]);
    RecurringInvoice::factory()->count(2)->create(['company_id' => $this->company->id, 'customer_id' => $customer->id]);
    Item::factory()->count(2)->create(['company_id' => $this->company->id]);

    $expectedCustomers = Customer::where('company_id', $this->company->id)->count();
    $expectedInvoices = Invoice::where('company_id', $this->company->id)->count();
    $expectedEstimates = Estimate::where('company_id', $this->company->id)->count();
    $expectedPayments = Payment::where('company_id', $this->company->id)->count();
    $expectedExpenses = Expense::where('company_id', $this->company->id)->count();
    $expectedRecurringInvoices = RecurringInvoice::where('company_id', $this->company->id)->count();
    $expectedItems = Item::where('company_id', $this->company->id)->count();

    getJson('/api/v1/customers?limit=all')->assertOk()->assertJsonCount($expectedCustomers, 'data')->assertJsonStructure(['data' => ['*' => ['id', 'name']]]);
    getJson('/api/v1/invoices?limit=all')->assertOk()->assertJsonCount($expectedInvoices, 'data')->assertJsonStructure(['data' => ['*' => ['id', 'invoice_number']]]);
    getJson('/api/v1/estimates?limit=all')->assertOk()->assertJsonCount($expectedEstimates, 'data')->assertJsonStructure(['data' => ['*' => ['id', 'estimate_number']]]);
    getJson('/api/v1/payments?limit=all')->assertOk()->assertJsonCount($expectedPayments, 'data')->assertJsonStructure(['data' => ['*' => ['id', 'payment_number']]]);
    getJson('/api/v1/expenses?limit=all')->assertOk()->assertJsonCount($expectedExpenses, 'data')->assertJsonStructure(['data' => ['*' => ['id', 'amount', 'notes']]]);
    getJson('/api/v1/recurring-invoices?limit=all')->assertOk()->assertJsonCount($expectedRecurringInvoices, 'data')->assertJsonStructure(['data' => ['*' => ['id', 'status']]]);
    getJson('/api/v1/items?limit=all')->assertOk()->assertJsonCount($expectedItems, 'data')->assertJsonStructure(['data' => ['*' => ['id', 'name']]]);
});

test('expenses index returns attachment_receipt_url when receipt media is attached', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $expense = Expense::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $file = \Illuminate\Http\UploadedFile::fake()->create('receipt.pdf', 100);
    $expense->addMedia($file)->toMediaCollection('receipts');

    $response = getJson('/api/v1/expenses');
    $response->assertOk();

    $item = collect($response->json('data'))->firstWhere('id', $expense->id);
    expect($item)->not->toBeNull()
        ->and($item['attachment_receipt_url'])->not->toBeNull()
        ->and($item['attachment_receipt_url'])->toBeArray()
        ->and($item['attachment_receipt_url'])->toHaveKey('url')
        ->and($item['attachment_receipt_url'])->toHaveKey('type');
});

test('recurring invoices index executes constant queries and returns TableData schema', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    RecurringInvoice::factory()->count(10)->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
    ]);

    $res2 = getJson('/api/v1/recurring-invoices?page=1&limit=2');
    $res2->assertOk()->assertJsonCount(2, 'data');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/recurring-invoices?page=1&limit=10');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'starts_at',
                    'customer' => ['id', 'name'],
                    'status',
                    'frequency',
                    'total',
                ],
            ],
            'meta' => [
                'recurring_invoice_total_count',
            ],
        ]);

    $data = $response->json('data.0');
    expect($data)->not->toHaveKey('items')
        ->and($data)->not->toHaveKey('taxes')
        ->and($data)->not->toHaveKey('invoices')
        ->and(count($queries))->toBeLessThanOrEqual(10);
});

test('items index executes constant queries and returns TableData schema', function () {
    $unit = Unit::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $taxType = TaxType::factory()->create([
        'company_id' => $this->company->id,
    ]);

    $items = Item::factory()->count(10)->create([
        'company_id' => $this->company->id,
        'unit_id' => $unit->id,
    ]);

    foreach ($items as $item) {
        Tax::factory()->create([
            'company_id' => $this->company->id,
            'item_id' => $item->id,
            'tax_type_id' => $taxType->id,
        ]);
    }

    $res2 = getJson('/api/v1/items?page=1&limit=2');
    $res2->assertOk()->assertJsonCount(2, 'data');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/items?page=1&limit=10');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'price',
                    'unit' => ['id', 'name'],
                ],
            ],
            'meta' => [
                'item_total_count',
                'tax_types',
            ],
        ]);

    $data = $response->json('data.0');
    expect($data)->toHaveKey('taxes')
        ->and($data['taxes'])->toBeArray()
        ->and(count($data['taxes']))->toBe(1)
        ->and($data)->not->toHaveKey('company')
        ->and(count($queries))->toBeLessThanOrEqual(10);
});

test('items index supports sorting by unit_name without sql error and bounds queries', function () {
    $unitA = Unit::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Box',
    ]);
    $unitB = Unit::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Piece',
    ]);

    Item::factory()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unitB->id,
        'name' => 'Item 1',
    ]);
    Item::factory()->create([
        'company_id' => $this->company->id,
        'unit_id' => $unitA->id,
        'name' => 'Item 2',
    ]);

    getJson('/api/v1/items?page=1&limit=2');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = getJson('/api/v1/items?orderByField=unit_name&orderBy=asc');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'price',
                    'unit' => ['id', 'name'],
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data[0]['unit']['name'])->toBe('Box')
        ->and(count($queries))->toBeLessThanOrEqual(10);
});
