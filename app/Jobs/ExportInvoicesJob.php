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
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

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

        $tempPath = tempnam(sys_get_temp_dir(), 'inv_exp_').'.xlsx';

        try {
            $writer = new Writer();
            $writer->openToFile($tempPath);

            // Add Header Row
            $headerRow = Row::fromValues([
                'Invoice Number',
                'Invoice Date',
                'Due Date',
                'Customer Name',
                'Customer Email',
                'Status',
                'Paid Status',
                'Currency',
                'Sub Total',
                'Tax',
                'Discount',
                'Total',
                'Due Amount',
            ]);
            $writer->addRow($headerRow);

            $filters = $this->export->filters ?? [];
            $companyId = $this->export->company_id;

            $query = Invoice::query()
                ->whereCompany($companyId)
                ->applyFilters($filters)
                ->with(['customer', 'customer.currency']);

            $rowCount = 0;

            // Stream records row-by-row in lazy chunks
            foreach ($query->lazyById(200) as $invoice) {
                $currencyCode = $invoice->customer?->currency?->code ?? 'USD';

                $row = Row::fromValues([
                    $invoice->invoice_number,
                    $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '',
                    $invoice->due_date ? Carbon::parse($invoice->due_date)->format('Y-m-d') : '',
                    $invoice->customer?->name ?? '',
                    $invoice->customer?->email ?? '',
                    $invoice->status,
                    $invoice->paid_status,
                    $currencyCode,
                    number_format(($invoice->sub_total ?? 0) / 100, 2, '.', ''),
                    number_format(($invoice->tax ?? 0) / 100, 2, '.', ''),
                    number_format(($invoice->discount_val ?? 0) / 100, 2, '.', ''),
                    number_format(($invoice->total ?? 0) / 100, 2, '.', ''),
                    number_format(($invoice->due_amount ?? 0) / 100, 2, '.', ''),
                ]);

                $writer->addRow($row);
                $rowCount++;
            }

            $writer->close();

            $fileName = 'invoices-export-'.Carbon::now()->format('Y-m-d-His').'-'.$this->export->id.'.xlsx';
            $storageRelativePath = 'exports/'.$companyId.'/'.$fileName;

            // Store to disk (FileDisk default or local)
            $fileDisk = FileDisk::whereCompany($companyId)->whereSetAsDefault(true)->first();
            $diskName = 'local';

            if ($fileDisk && $fileDisk->driver !== 'local') {
                $fileDisk->withStorage(function ($customDisk) use ($storageRelativePath, $tempPath) {
                    Storage::disk($customDisk)->put($storageRelativePath, file_get_contents($tempPath));
                });
                $diskName = $fileDisk->driver;
            } else {
                Storage::disk('local')->put($storageRelativePath, file_get_contents($tempPath));
            }

            $this->export->update([
                'status' => InvoiceExport::STATUS_COMPLETED,
                'file_name' => $fileName,
                'file_path' => $storageRelativePath,
                'disk' => $diskName,
                'total_rows' => $rowCount,
                'completed_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays(7),
            ]);
        } catch (Exception $e) {
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
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
