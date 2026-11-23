<?php

namespace App\Jobs;

use App\Models\Estimate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateEstimatePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Estimate $estimate,
        public bool $deleteExistingFile = false
    ) {
    }

    public function handle(): int
    {
        $this->estimate->generatePDF('estimate', $this->estimate->estimate_number, $this->deleteExistingFile);

        return 0;
    }
}
