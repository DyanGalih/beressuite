<?php

namespace App\Data\Estimate;

use App\Data\Data;

class EstimateTaxData extends Data
{
    public function __construct(
        public int $tax_type_id,
        public float $amount,
        public ?float $percent = null,
        public ?string $name = null,
    ) {
    }
}
