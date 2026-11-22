<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePaymentPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Payment $payment,
        public bool $deleteExistingFile = false
    ) {
    }

    public function handle(): int
    {
        $this->payment->generatePDF('payment', $this->payment->payment_number, $this->deleteExistingFile);

        return 0;
    }
}
