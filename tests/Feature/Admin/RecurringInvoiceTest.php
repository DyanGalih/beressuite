<?php

use App\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceController;
use App\Http\Requests\RecurringInvoiceRequest;
use App\Models\InvoiceItem;
use App\Models\RecurringInvoice;
use App\Models\User;
use Carbon\Carbon;
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

test('get recurring invoices', function () {
    RecurringInvoice::factory()->create();

    getJson('api/v1/recurring-invoices?page=1')
        ->assertOk();
});

test('store user using a form request', function () {
    $this->assertActionUsesFormRequest(
        RecurringInvoiceController::class,
        'store',
        RecurringInvoiceRequest::class
    );
});

test('store recurring invoice', function () {
    $recurringInvoice = RecurringInvoice::factory()->raw();
    $recurringInvoice['items'] = [
        InvoiceItem::factory()->raw()
    ];

    postJson('api/v1/recurring-invoices', $recurringInvoice)
        ->assertStatus(201);

    $recurringInvoice = collect($recurringInvoice)
        ->only([
            'frequency',
        ])
        ->toArray();

    $this->assertDatabaseHas('recurring_invoices', $recurringInvoice);
});

test('get recurring invoice', function () {
    $taxType = \App\Models\TaxType::factory()->create(['type' => 'MODULE']);
    $customer = \App\Models\Customer::factory()->create();
    $customer->addresses()->create([
        'name' => 'Billing Address',
        'type' => 'billing',
    ]);
    $recurringInvoice = RecurringInvoice::factory()->create([
        'customer_id' => $customer->id,
    ]);
    $item = \App\Models\InvoiceItem::factory()->create([
        'recurring_invoice_id' => $recurringInvoice->id,
    ]);
    \App\Models\Tax::factory()->create([
        'recurring_invoice_id' => $recurringInvoice->id,
        'invoice_item_id' => $item->id,
        'tax_type_id' => $taxType->id,
    ]);

    getJson("api/v1/recurring-invoices/{$recurringInvoice->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.taxes.0.type', 'MODULE')
        ->assertJsonStructure([
            'data' => [
                'customer' => [
                    'billing',
                    'currency',
                    'company',
                ],
                'items' => [
                    '*' => [
                        'taxes',
                        'fields',
                    ],
                ],
            ],
        ]);
});

test('update user using a form request', function () {
    $this->assertActionUsesFormRequest(
        RecurringInvoiceController::class,
        'update',
        RecurringInvoiceRequest::class
    );
});

test('update recurring invoice', function () {
    $recurringInvoice = RecurringInvoice::factory()->create();
    $recurringInvoice['items'] = [
        InvoiceItem::factory()->raw()
    ];

    $new_recurringInvoice = RecurringInvoice::factory()->raw();
    $new_recurringInvoice['items'] = [
        InvoiceItem::factory()->raw()
    ];

    putJson("api/v1/recurring-invoices/{$recurringInvoice->id}", $new_recurringInvoice)
        ->assertOk();

    $new_recurringInvoice = collect($new_recurringInvoice)
        ->only([
            'frequency',
        ])
        ->toArray();

    $this->assertDatabaseHas('recurring_invoices', $new_recurringInvoice);
});

test('delete multiple recurring invoice', function () {
    $recurringInvoices = RecurringInvoice::factory()->count(3)->create();

    $data = [
        'ids' => $recurringInvoices->pluck('id'),
    ];

    postJson('api/v1/recurring-invoices/delete', $data)
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    foreach ($recurringInvoices as $recurringInvoice) {
        $this->assertModelMissing($recurringInvoice);
    }
});

test('delete recurring invoices uses a form request', function () {
    $this->assertActionUsesFormRequest(
        \App\Http\Controllers\V1\Admin\RecurringInvoice\RecurringInvoiceController::class,
        'delete',
        \App\Http\Requests\DeleteRecurringInvoicesRequest::class
    );
});

test('delete multiple recurring invoices rejects invalid payload', function () {
    postJson('api/v1/recurring-invoices/delete', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids']);

    postJson('api/v1/recurring-invoices/delete', ['ids' => [99999]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids.0']);

    $recurringInvoice = RecurringInvoice::factory()->create();
    postJson('api/v1/recurring-invoices/delete', ['ids' => [$recurringInvoice->id, $recurringInvoice->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids.0', 'ids.1']);

    postJson('api/v1/recurring-invoices/delete', ['ids' => [[$recurringInvoice->id]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ids.0']);
});

test('calculate frequency for recurring invoice', function () {
    $data = [
        'frequency' => '* * 2 * *',
        'starts_at' => Carbon::now()->format('Y-m-d')
    ];

    $queryString = http_build_query($data, '', '&');

    getJson("api/v1/recurring-invoice-frequency?".$queryString)
        ->assertOk();
});
