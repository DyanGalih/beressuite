<?php

namespace Crater\Jobs;

use Carbon\Carbon;
use Crater\Models\FileDisk;
use Crater\Models\Invoice;
use Crater\Models\InvoiceExport;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class ExportInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public InvoiceExport $export
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->export->update([
            'status' => InvoiceExport::STATUS_PROCESSING,
        ]);

        $tempPath = tempnam(sys_get_temp_dir(), 'inv_exp_');
        if ($tempPath === false) {
            throw new Exception('Failed to create temporary export file.');
        }

        $writer = new Writer();
        $writerClosed = false;

        try {
            $writer->openToFile($tempPath);

            $filters = $this->export->filters ?? [];
            $companyId = $this->export->company_id;

            // Sheet 1: Invoices (summary + purpose + payment summary)
            $writer->getCurrentSheet()->setName('Invoices');
            $writer->addRow(Row::fromValues([
                'Invoice Number',
                'Reference Number',
                'Invoice Date',
                'Due Date',
                'Customer Name',
                'Customer Email',
                'Items Summary',
                'Notes',
                'Status',
                'Paid Status',
                'Currency',
                'Sub Total',
                'Tax',
                'Discount',
                'Total',
                'Due Amount',
                'Paid Amount',
                'Payment Count',
                'Last Payment Date',
                'Payment Methods',
                'Payment Numbers',
            ]));

            $invoicesQuery = Invoice::query()
                ->whereCompany($companyId)
                ->applyFilters($filters)
                ->with(['customer', 'currency', 'items', 'payments.paymentMethod']);

            $rowCount = 0;

            foreach ($invoicesQuery->lazyById(200) as $invoice) {
                $currencyCode = $invoice->currency?->code ?? $invoice->customer?->currency?->code ?? 'USD';
                $payments = $invoice->payments;
                $paidAmount = (int) $payments->sum('amount');
                $paymentCount = $payments->count();
                $lastPaymentDate = $payments->max('payment_date');
                $paymentMethods = $payments
                    ->map(fn ($payment) => $payment->paymentMethod?->name)
                    ->filter()
                    ->unique()
                    ->implode(', ');
                $paymentNumbers = $payments
                    ->pluck('payment_number')
                    ->filter()
                    ->implode(', ');

                $writer->addRow($this->stringRow([
                    $this->sanitizeCell((string) $invoice->invoice_number),
                    $this->sanitizeCell((string) ($invoice->reference_number ?? '')),
                    $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '',
                    $invoice->due_date ? Carbon::parse($invoice->due_date)->format('Y-m-d') : '',
                    $this->sanitizeCell((string) ($invoice->customer?->name ?? '')),
                    $this->sanitizeCell((string) ($invoice->customer?->email ?? '')),
                    $this->sanitizeCell($this->buildItemsSummary($invoice)),
                    $this->sanitizeCell($this->cleanRichText($invoice->notes)),
                    (string) $invoice->status,
                    (string) $invoice->paid_status,
                    (string) $currencyCode,
                    $this->formatMoney($invoice->sub_total),
                    $this->formatMoney($invoice->tax),
                    $this->formatMoney($invoice->discount_val),
                    $this->formatMoney($invoice->total),
                    $this->formatMoney($invoice->due_amount),
                    $this->formatMoney($paidAmount),
                    (string) $paymentCount,
                    $lastPaymentDate ? Carbon::parse($lastPaymentDate)->format('Y-m-d') : '',
                    $this->sanitizeCell($paymentMethods),
                    $this->sanitizeCell($paymentNumbers),
                ]));

                $rowCount++;
            }

            // Sheet 2: Invoice Items (line-item details)
            $writer->addNewSheetAndMakeItCurrent()->setName('Invoice Items');
            $writer->addRow(Row::fromValues([
                'Invoice Number',
                'Reference Number',
                'Invoice Date',
                'Customer Name',
                'Item Name',
                'Description',
                'Quantity',
                'Unit Price',
                'Discount',
                'Tax',
                'Item Total',
                'Currency',
            ]));

            $itemsQuery = Invoice::query()
                ->whereCompany($companyId)
                ->applyFilters($filters)
                ->with(['customer', 'currency', 'items']);

            foreach ($itemsQuery->lazyById(200) as $invoice) {
                $currencyCode = $invoice->currency?->code ?? $invoice->customer?->currency?->code ?? 'USD';
                $invoiceDate = $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '';

                foreach ($invoice->items as $item) {
                    $writer->addRow($this->stringRow([
                        $this->sanitizeCell((string) $invoice->invoice_number),
                        $this->sanitizeCell((string) ($invoice->reference_number ?? '')),
                        $invoiceDate,
                        $this->sanitizeCell((string) ($invoice->customer?->name ?? '')),
                        $this->sanitizeCell((string) $item->name),
                        $this->sanitizeCell($this->cleanRichText($item->description)),
                        (string) ($item->quantity ?? 0),
                        $this->formatMoney($item->price),
                        $this->formatMoney($item->discount_val),
                        $this->formatMoney($item->tax),
                        $this->formatMoney($item->total),
                        (string) $currencyCode,
                    ]));
                }
            }

            // Sheet 3: Payments (payment details for paid / partially-paid invoices)
            $writer->addNewSheetAndMakeItCurrent()->setName('Payments');
            $writer->addRow(Row::fromValues([
                'Invoice Number',
                'Reference Number',
                'Customer Name',
                'Payment Number',
                'Payment Date',
                'Payment Method',
                'Payment Amount',
                'Currency',
                'Payment Notes',
            ]));

            $paymentsQuery = Invoice::query()
                ->whereCompany($companyId)
                ->applyFilters($filters)
                ->with(['customer', 'currency', 'payments.paymentMethod']);

            foreach ($paymentsQuery->lazyById(200) as $invoice) {
                $currencyCode = $invoice->currency?->code ?? $invoice->customer?->currency?->code ?? 'USD';

                foreach ($invoice->payments as $payment) {
                    $writer->addRow($this->stringRow([
                        $this->sanitizeCell((string) $invoice->invoice_number),
                        $this->sanitizeCell((string) ($invoice->reference_number ?? '')),
                        $this->sanitizeCell((string) ($invoice->customer?->name ?? '')),
                        $this->sanitizeCell((string) ($payment->payment_number ?? '')),
                        $payment->payment_date ? Carbon::parse($payment->payment_date)->format('Y-m-d') : '',
                        $this->sanitizeCell((string) ($payment->paymentMethod?->name ?? '')),
                        $this->formatMoney($payment->amount),
                        (string) $currencyCode,
                        $this->sanitizeCell($this->cleanRichText($payment->notes)),
                    ]));
                }
            }

            $writer->close();
            $writerClosed = true;

            $fileName = 'invoices-export-'.Carbon::now()->format('Y-m-d-His').'-'.$this->export->id.'.xlsx';
            $storageRelativePath = 'exports/'.$companyId.'/'.$fileName;

            // Store to disk (FileDisk default or local) using stream
            $fileDisk = FileDisk::whereCompany($companyId)->whereSetAsDefault(true)->first();
            $diskName = 'local';
            $fileDiskId = null;

            $stream = fopen($tempPath, 'rb');
            if ($stream === false) {
                throw new Exception('Failed to open temporary export file for reading.');
            }

            try {
                if ($fileDisk && $fileDisk->driver !== 'local') {
                    $written = false;
                    $fileDisk->withStorage(function ($customDisk) use ($storageRelativePath, $stream, &$written) {
                        $written = Storage::disk($customDisk)->put($storageRelativePath, $stream);
                    });
                    if (! $written) {
                        throw new Exception("Failed to write export to custom disk: {$fileDisk->driver}");
                    }
                    $diskName = $fileDisk->driver;
                    $fileDiskId = $fileDisk->id;
                } else {
                    $written = Storage::disk('local')->put($storageRelativePath, $stream);
                    if (! $written) {
                        throw new Exception('Failed to write export to local disk.');
                    }
                    $diskName = 'local';
                    $fileDiskId = $fileDisk?->id;
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $this->export->update([
                'status' => InvoiceExport::STATUS_COMPLETED,
                'file_name' => $fileName,
                'file_path' => $storageRelativePath,
                'disk' => $diskName,
                'file_disk_id' => $fileDiskId,
                'total_rows' => $rowCount,
                'completed_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays(7),
            ]);
        } catch (Exception $e) {
            if (! $writerClosed) {
                try {
                    $writer->close();
                } catch (Throwable) {
                    // Suppress secondary cleanup exceptions to preserve original exception
                }
            }

            Log::error('Invoice export job failed: '.$e->getMessage(), [
                'export_id' => $this->export->id,
                'exception' => $e,
            ]);

            $this->export->update([
                'status' => InvoiceExport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Handle a job failure (e.g. worker timeouts or exhausted attempts).
     */
    public function failed(?Throwable $exception = null): void
    {
        if ($this->export->fresh()?->status === InvoiceExport::STATUS_COMPLETED) {
            return;
        }

        Log::error('Invoice export job failed permanently: '.($exception?->getMessage() ?? 'Unknown error'), [
            'export_id' => $this->export->id,
            'exception' => $exception,
        ]);

        $this->export->update([
            'status' => InvoiceExport::STATUS_FAILED,
            'error_message' => $exception?->getMessage() ?? 'Job execution failed or timed out.',
        ]);
    }

    /**
     * Build a concise line-item summary showing what an invoice is for.
     */
    private function buildItemsSummary(Invoice $invoice): string
    {
        $summary = $invoice->items
            ->map(function ($item): string {
                $name = trim((string) $item->name);
                $description = $this->cleanRichText($item->description);

                if ($description !== '') {
                    return "{$name} ({$description})";
                }

                return $name;
            })
            ->filter(fn (string $part): bool => $part !== '')
            ->implode('; ');

        if (mb_strlen($summary) > 32700) {
            return mb_substr($summary, 0, 32697).'...';
        }

        return $summary;
    }

    /**
     * Convert optional rich-text HTML into normalized plain text.
     */
    private function cleanRichText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $plain = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }

    /**
     * Neutralize spreadsheet formula injection (CWE-1236) on free-text cells.
     */
    private function sanitizeCell(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    private function formatMoney(int|float|null $amountInCents): string
    {
        return number_format(($amountInCents ?? 0) / 100, 2, '.', '');
    }

    /**
     * @param  array<int, string>  $values
     */
    private function stringRow(array $values): Row
    {
        return new Row(array_map(
            static fn (string $value): StringCell => new StringCell($value, null),
            $values
        ));
    }
}
