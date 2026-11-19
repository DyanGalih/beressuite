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

        $tempPath = tempnam(sys_get_temp_dir(), 'inv_exp_').'.xlsx';
        $writer = new Writer();
        $writerClosed = false;

        try {
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
                ->with(['customer', 'currency']);

            $rowCount = 0;

            // Stream records row-by-row in lazy chunks
            foreach ($query->lazyById(200) as $invoice) {
                $currencyCode = $invoice->currency?->code ?? $invoice->customer?->currency?->code ?? 'USD';

                $row = new Row([
                    new StringCell((string) $invoice->invoice_number, null),
                    new StringCell($invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '', null),
                    new StringCell($invoice->due_date ? Carbon::parse($invoice->due_date)->format('Y-m-d') : '', null),
                    new StringCell((string) ($invoice->customer?->name ?? ''), null),
                    new StringCell((string) ($invoice->customer?->email ?? ''), null),
                    new StringCell((string) $invoice->status, null),
                    new StringCell((string) $invoice->paid_status, null),
                    new StringCell((string) $currencyCode, null),
                    new StringCell(number_format(($invoice->sub_total ?? 0) / 100, 2, '.', ''), null),
                    new StringCell(number_format(($invoice->tax ?? 0) / 100, 2, '.', ''), null),
                    new StringCell(number_format(($invoice->discount_val ?? 0) / 100, 2, '.', ''), null),
                    new StringCell(number_format(($invoice->total ?? 0) / 100, 2, '.', ''), null),
                    new StringCell(number_format(($invoice->due_amount ?? 0) / 100, 2, '.', ''), null),
                ]);

                $writer->addRow($row);
                $rowCount++;
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
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }
}
