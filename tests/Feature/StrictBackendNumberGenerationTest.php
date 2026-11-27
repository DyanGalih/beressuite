<?php

use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->user = $user;
    $this->companyId = $user->companies()->first()->id;

    $this->withHeaders([
        'company' => $this->companyId,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('it creates an invoice without invoice_number and assigns next sequential number from backend', function () {
    $invoiceData = Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]);
    unset($invoiceData['invoice_number']);

    $response = postJson('api/v1/invoices', $invoiceData);

    $response->assertOk();
    $assignedNumber = $response->json('data.invoice_number');
    expect($assignedNumber)->not->toBeEmpty();

    $this->assertDatabaseHas('invoices', [
        'id' => $response->json('data.id'),
        'invoice_number' => $assignedNumber,
        'company_id' => $this->companyId,
    ]);
});

test('it ignores client-supplied invoice_number on create and overrides it with true backend sequence', function () {
    $invoiceData = Invoice::factory()->raw([
        'invoice_number' => 'CLIENT-FORGED-INV-9999',
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]);

    $response = postJson('api/v1/invoices', $invoiceData);

    $response->assertOk();
    $assignedNumber = $response->json('data.invoice_number');
    expect($assignedNumber)->not->toBe('CLIENT-FORGED-INV-9999');

    $this->assertDatabaseMissing('invoices', [
        'invoice_number' => 'CLIENT-FORGED-INV-9999',
    ]);
    $this->assertDatabaseHas('invoices', [
        'id' => $response->json('data.id'),
        'invoice_number' => $assignedNumber,
    ]);
});

test('it creates an estimate without estimate_number and assigns next sequential number from backend', function () {
    $estimateData = Estimate::factory()->raw([
        'items' => [EstimateItem::factory()->raw()],
        'taxes' => [Tax::factory()->raw()],
    ]);
    unset($estimateData['estimate_number']);

    $response = postJson('api/v1/estimates', $estimateData);

    $response->assertStatus(201);
    $assignedNumber = $response->json('data.estimate_number');
    expect($assignedNumber)->not->toBeEmpty();

    $this->assertDatabaseHas('estimates', [
        'id' => $response->json('data.id'),
        'estimate_number' => $assignedNumber,
        'company_id' => $this->companyId,
    ]);
});

test('it ignores client-supplied estimate_number on create and overrides it with true backend sequence', function () {
    $estimateData = Estimate::factory()->raw([
        'estimate_number' => 'CLIENT-FORGED-EST-9999',
        'items' => [EstimateItem::factory()->raw()],
        'taxes' => [Tax::factory()->raw()],
    ]);

    $response = postJson('api/v1/estimates', $estimateData);

    $response->assertStatus(201);
    $assignedNumber = $response->json('data.estimate_number');
    expect($assignedNumber)->not->toBe('CLIENT-FORGED-EST-9999');

    $this->assertDatabaseMissing('estimates', [
        'estimate_number' => 'CLIENT-FORGED-EST-9999',
    ]);
});

test('it creates a payment without payment_number and assigns next sequential number from backend', function () {
    $paymentData = Payment::factory()->raw([
        'exchange_rate' => 1,
    ]);
    unset($paymentData['payment_number']);

    $response = postJson('api/v1/payments', $paymentData);

    $response->assertOk();
    $assignedNumber = $response->json('data.payment_number');
    expect($assignedNumber)->not->toBeEmpty();

    $this->assertDatabaseHas('payments', [
        'id' => $response->json('data.id'),
        'payment_number' => $assignedNumber,
        'company_id' => $this->companyId,
    ]);
});

test('it ignores client-supplied payment_number on create and overrides it with true backend sequence', function () {
    $paymentData = Payment::factory()->raw([
        'payment_number' => 'CLIENT-FORGED-PAY-9999',
        'exchange_rate' => 1,
    ]);

    $response = postJson('api/v1/payments', $paymentData);

    $response->assertOk();
    $assignedNumber = $response->json('data.payment_number');
    expect($assignedNumber)->not->toBe('CLIENT-FORGED-PAY-9999');

    $this->assertDatabaseMissing('payments', [
        'payment_number' => 'CLIENT-FORGED-PAY-9999',
    ]);
});

test('it preserves tenant isolation for document numbering across companies', function () {
    $companyA = Company::find($this->companyId);
    $companyB = Company::factory()->create();
    $companyB->setupDefaultData();
    $this->user->companies()->attach($companyB->id);
    \Silber\Bouncer\BouncerFacade::scope()->to($companyB->id);
    $this->user->assign('super admin');

    // Create invoice in Company A
    $invA = Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]);
    unset($invA['invoice_number']);
    $resA = postJson('api/v1/invoices', $invA)->assertOk();
    $numA = $resA->json('data.invoice_number');

    // Switch to Company B and create invoice
    $this->withHeaders(['company' => $companyB->id]);
    $invB = Invoice::factory()->raw([
        'taxes' => [Tax::factory()->raw()],
        'items' => [InvoiceItem::factory()->raw()],
    ]);
    unset($invB['invoice_number']);
    $resB = postJson('api/v1/invoices', $invB)->assertOk();
    $numB = $resB->json('data.invoice_number');

    expect(Invoice::find($resA->json('data.id'))->company_id)->toBe($companyA->id);
    expect(Invoice::find($resB->json('data.id'))->company_id)->toBe($companyB->id);
    expect(Invoice::find($resB->json('data.id'))->sequence_number)->toBe(1);
});
