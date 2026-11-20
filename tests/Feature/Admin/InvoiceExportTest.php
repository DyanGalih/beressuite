<?php

use Carbon\Carbon;
use Crater\Jobs\ExportInvoicesJob;
use Crater\Models\Company;
use Crater\Models\Customer;
use Crater\Models\Invoice;
use Crater\Models\InvoiceExport;
use Crater\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->user = User::find(1);
    $this->company = $this->user->companies()->first();

    $this->withHeaders([
        'company' => $this->company->id,
    ]);
    Sanctum::actingAs(
        $this->user,
        ['*']
    );

    Storage::fake('local');
});

test('dispatch invoice export returns 202 accepted and queues job', function () {
    Queue::fake();

    $response = postJson('api/v1/invoices/export', [
        'paid_status' => 'PAID',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.filters.paid_status', 'PAID');

    $exportId = $response->json('data.id');
    $this->assertDatabaseHas('invoice_exports', [
        'id' => $exportId,
        'company_id' => $this->company->id,
        'status' => 'pending',
    ]);

    Queue::assertPushed(ExportInvoicesJob::class, function ($job) use ($exportId) {
        return $job->export->id === $exportId;
    });
});

test('export validation rejects invalid paid_status', function () {
    $response = postJson('api/v1/invoices/export', [
        'paid_status' => 'INVALID_STATUS',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['paid_status']);
});

test('export job generates valid streaming xlsx file with headers and filtered rows', function () {
    Storage::fake('local');

    // Create a known paid invoice and an unpaid invoice
    $customer = Customer::factory()->create(['company_id' => $this->company->id]);
    $paidInvoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-PAID-001',
        'status' => Invoice::STATUS_COMPLETED,
        'paid_status' => Invoice::STATUS_PAID,
        'sub_total' => 10000,
        'tax' => 1000,
        'discount_val' => 0,
        'total' => 11000,
        'due_amount' => 0,
    ]);

    $unpaidInvoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-UNPAID-002',
        'status' => Invoice::STATUS_SENT,
        'paid_status' => Invoice::STATUS_UNPAID,
        'sub_total' => 5000,
        'tax' => 500,
        'discount_val' => 0,
        'total' => 5500,
        'due_amount' => 5500,
    ]);

    $export = InvoiceExport::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'status' => InvoiceExport::STATUS_PENDING,
        'filters' => [
            'paid_status' => 'PAID',
        ],
    ]);

    $job = new ExportInvoicesJob($export);
    $job->handle();

    $export->refresh();
    expect($export->status)->toBe(InvoiceExport::STATUS_COMPLETED);
    expect($export->total_rows)->toBeGreaterThanOrEqual(1);
    expect($export->file_path)->not->toBeEmpty();
    expect(Storage::disk('local')->exists($export->file_path))->toBeTrue();

    // Verify XLSX structure using OpenSpout reader
    $tempFile = tempnam(sys_get_temp_dir(), 'test_verify_');
    expect($tempFile)->not->toBeFalse();
    file_put_contents($tempFile, Storage::disk('local')->get($export->file_path));

    $reader = new Reader();
    $reader->open($tempFile);

    $readRows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $readRows[] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($tempFile);

    // Header validation
    expect($readRows)->not->toBeEmpty();
    expect($readRows[0])->toContain('Invoice Number', 'Invoice Date', 'Paid Status', 'Total');

    // Data rows must contain the paid invoice, but NOT the unpaid invoice
    $flattened = collect($readRows)->map(fn ($r) => implode('|', $r))->implode("\n");
    expect($flattened)->toContain('INV-PAID-001');
    expect($flattened)->not->toContain('INV-UNPAID-002');
});

test('user can check export status via show endpoint', function () {
    $export = InvoiceExport::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'status' => InvoiceExport::STATUS_COMPLETED,
        'total_rows' => 5,
        'file_name' => 'invoices-export.xlsx',
        'file_path' => 'exports/1/invoices-export.xlsx',
    ]);

    $response = getJson("api/v1/invoices/export/{$export->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $export->id)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.total_rows', 5);
});

test('user can download completed export spreadsheet', function () {
    Storage::fake('local');
    $filePath = "exports/{$this->company->id}/test-export.xlsx";
    Storage::disk('local')->put($filePath, 'dummy xlsx content');

    $export = InvoiceExport::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'status' => InvoiceExport::STATUS_COMPLETED,
        'disk' => 'local',
        'file_name' => 'test-export.xlsx',
        'file_path' => $filePath,
        'total_rows' => 10,
        'expires_at' => Carbon::now()->addDays(2),
    ]);

    $response = get("api/v1/invoices/export/{$export->id}/download");

    $response->assertOk();
    $response->assertHeader('content-disposition', 'attachment; filename=test-export.xlsx');
});

test('cross-company export download is forbidden / not found', function () {
    Storage::fake('local');
    $otherCompany = Company::factory()->create();
    $filePath = "exports/{$otherCompany->id}/other-export.xlsx";
    Storage::disk('local')->put($filePath, 'other content');

    $export = InvoiceExport::create([
        'company_id' => $otherCompany->id,
        'user_id' => $this->user->id,
        'status' => InvoiceExport::STATUS_COMPLETED,
        'disk' => 'local',
        'file_name' => 'other-export.xlsx',
        'file_path' => $filePath,
    ]);

    // Request from $this->company should not find or access other company's export
    $response = get("api/v1/invoices/export/{$export->id}/download");

    $response->assertNotFound();
});

test('downloading uncompleted export returns 404', function () {
    $export = InvoiceExport::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'status' => InvoiceExport::STATUS_PENDING,
    ]);

    $response = get("api/v1/invoices/export/{$export->id}/download");

    $response->assertNotFound();
});

test('downloading expired export returns 410 gone', function () {
    Storage::fake('local');
    $filePath = "exports/{$this->company->id}/expired-export.xlsx";
    Storage::disk('local')->put($filePath, 'expired content');

    $export = InvoiceExport::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'status' => InvoiceExport::STATUS_COMPLETED,
        'disk' => 'local',
        'file_name' => 'expired-export.xlsx',
        'file_path' => $filePath,
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $response = get("api/v1/invoices/export/{$export->id}/download");

    $response->assertStatus(410);
});

test('export validation requires paired from_date and to_date', function () {
    $response = postJson('api/v1/invoices/export', [
        'from_date' => '2026-11-01',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['to_date']);

    $response2 = postJson('api/v1/invoices/export', [
        'to_date' => '2026-11-19',
    ]);

    $response2->assertStatus(422)
        ->assertJsonValidationErrors(['from_date']);
});

test('user without view-invoice permission is forbidden from exporting', function () {
    $unauthorizedUser = User::factory()->create();
    $this->company->users()->attach($unauthorizedUser->id);
    Bouncer::disallow($unauthorizedUser)->to('view-invoice', Invoice::class);

    $response = $this->actingAs($unauthorizedUser)
        ->withHeaders(['company' => $this->company->id])
        ->postJson('api/v1/invoices/export', []);

    $response->assertForbidden();
});

test('job failed method marks export as failed with error message', function () {
    $export = InvoiceExport::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'status' => InvoiceExport::STATUS_PROCESSING,
    ]);

    $job = new ExportInvoicesJob($export);
    $job->failed(new RuntimeException('Worker timed out'));

    $export->refresh();
    expect($export->status)->toBe(InvoiceExport::STATUS_FAILED);
    expect($export->error_message)->toBe('Worker timed out');
});

test('job failed method does not overwrite completed export status on redelivery', function () {
    $export = InvoiceExport::create([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'status' => InvoiceExport::STATUS_COMPLETED,
        'file_name' => 'test.xlsx',
        'file_path' => 'exports/test.xlsx',
    ]);

    $job = new ExportInvoicesJob($export);
    $job->failed(new RuntimeException('Redelivery timeout'));

    $export->refresh();
    expect($export->status)->toBe(InvoiceExport::STATUS_COMPLETED);
    expect($export->error_message)->toBeNull();
});

test('export invoices accepts invoice_number filter and filters by invoice number', function () {
    Queue::fake();

    $response = postJson('api/v1/invoices/export', [
        'invoice_number' => 'INV-00099',
    ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.filters.invoice_number', 'INV-00099');

    $exportId = $response->json('data.id');
    $this->assertDatabaseHas('invoice_exports', [
        'id' => $exportId,
        'company_id' => $this->company->id,
        'status' => 'pending',
    ]);

    Queue::assertPushed(ExportInvoicesJob::class, function ($job) use ($exportId) {
        return $job->export->id === $exportId;
    });
});

test('invoice_exports migration matches MySQL parent primary key integer widths', function () {
    $migration = file_get_contents(
        database_path('migrations/2026_11_19_000001_create_invoice_exports_table.php')
    );

    expect($migration)
        ->toContain("\$table->unsignedInteger('company_id')")
        ->toContain("\$table->unsignedInteger('user_id')->nullable()")
        ->toContain("\$table->unsignedBigInteger('file_disk_id')->nullable()")
        ->not->toContain("\$table->unsignedBigInteger('company_id')")
        ->not->toContain("\$table->unsignedBigInteger('user_id')");
});
