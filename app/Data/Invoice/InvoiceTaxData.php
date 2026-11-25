<?php

namespace App\Data\Invoice;

use App\Data\Data;

class InvoiceTaxData extends Data
{
    public function __construct(
        public int $tax_type_id,
        public float $amount,
        public ?float $percent = null,
        public ?string $name = null,
    ) {
    }
}
