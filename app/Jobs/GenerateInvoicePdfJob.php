<?php

namespace Crater\Jobs;

use Crater\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public bool $deleteExistingFile = false
    ) {
    }

    public function handle(): int
    {
        $this->invoice->generatePDF('invoice', $this->invoice->invoice_number, $this->deleteExistingFile);

        return 0;
    }
}
