<?php

use Crater\Models\Company;
use Crater\Models\Customer;
use Crater\Models\Invoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

test('Company::logo_path returns local filesystem path for public disk even without a default FileDisk', function () {
    Storage::fake('public');

    $company = Company::create([
        'name' => 'Local Logo Co',
        'slug' => 'local-logo-co',
    ]);

    $company->addMedia(UploadedFile::fake()->image('logo.png', 40, 40))
        ->toMediaCollection('logo', 'public');

    $logoPath = $company->fresh()->logo_path;

    expect($logoPath)->toBeString()
        ->and($logoPath)->not->toStartWith('http://')
        ->and($logoPath)->not->toStartWith('https://')
        ->and(is_file($logoPath))->toBeTrue();
});

test('Company::logo_path falls back to full URL when logo is on a non-local disk', function () {
    config()->set('filesystems.disks.remote_test', [
        'driver' => 's3',
        'url' => 'https://cdn.example.test',
    ]);

    $company = Company::create([
        'name' => 'Remote Logo Co',
        'slug' => 'remote-logo-co',
    ]);

    $media = new class () extends Media {
        public function getPath(string $conversionName = ''): string
        {
            return '/non-existent/remote/logo.png';
        }

        public function getFullUrl(string $conversionName = ''): string
        {
            return 'https://cdn.example.test/logos/company.png';
        }
    };

    $media->forceFill([
        'collection_name' => 'logo',
        'disk' => 'remote_test',
    ]);

    $company->setRelation('media', collect([$media]));

    expect($company->logo_path)->toBe('https://cdn.example.test/logos/company.png');
});

test('Company::logo_path returns null when local disk logo file is missing instead of HTTP URL', function () {
    $company = Company::create([
        'name' => 'Missing Local Logo Co',
        'slug' => 'missing-local-logo-co',
    ]);

    $media = new class () extends Media {
        public function getPath(string $conversionName = ''): string
        {
            return storage_path('app/public/does-not-exist/logo.png');
        }

        public function getFullUrl(string $conversionName = ''): string
        {
            return 'http://127.0.0.1:8000/storage/does-not-exist/logo.png';
        }
    };

    $media->forceFill([
        'collection_name' => 'logo',
        'disk' => 'public',
    ]);

    $company->setRelation('media', collect([$media]));

    expect($company->logo_path)->toBeNull();
});

test('Invoice::getPDFData uses browser-loadable URL for HTML previews with local logo and no default FileDisk', function () {
    Storage::fake('public');

    $company = Company::create([
        'name' => 'Preview Logo Co',
        'slug' => 'preview-logo-co',
    ]);

    $company->addMedia(UploadedFile::fake()->image('logo.png', 40, 40))
        ->toMediaCollection('logo', 'public');

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Preview Customer',
    ]);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(7)->format('Y-m-d'),
        'invoice_number' => 'INV-PREVIEW-001',
        'status' => Invoice::STATUS_DRAFT,
        'paid_status' => Invoice::STATUS_UNPAID,
        'tax_per_item' => 'NO',
        'discount_per_item' => 'NO',
        'sub_total' => 1000,
        'total' => 1000,
        'tax' => 0,
        'discount' => 0,
        'discount_val' => 0,
        'due_amount' => 1000,
        'template_name' => 'invoice1',
    ]);

    request()->merge(['preview' => '1']);

    try {
        $view = $invoice->getPDFData();
        $sharedLogo = view()->shared('logo');

        expect($view)->toBeInstanceOf(\Illuminate\Contracts\View\View::class)
            ->and($sharedLogo)->toBeString()
            ->and(str_starts_with($sharedLogo, 'http://') || str_starts_with($sharedLogo, 'https://'))->toBeTrue();
    } finally {
        request()->request->remove('preview');
        request()->query->remove('preview');
    }
});
