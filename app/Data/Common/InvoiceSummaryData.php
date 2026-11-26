<?php

namespace App\Data\Common;

use App\Data\Data;
use App\Models\Invoice;

class InvoiceSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $invoice_number,
    ) {
    }

    public static function fromModel(?Invoice $invoice): ?self
    {
        if (! $invoice) {
            return null;
        }

        return new self(
            id: (int) $invoice->id,
            invoice_number: (string) $invoice->invoice_number,
        );
    }
}
