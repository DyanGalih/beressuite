<?php

namespace App\Data\Estimate;

use App\Data\Data;

class EstimateItemData extends Data
{
    public function __construct(
        public string $name,
        public float $price,
        public float $quantity,
        public ?string $description = null,
        public ?float $discount = 0,
        public ?string $discount_type = null,
        public ?float $discount_val = 0,
        public ?float $tax = 0,
        public ?float $total = 0,
        public ?array $taxes = [],
        public ?array $custom_fields = [],
        public ?int $item_id = null,
    ) {
    }
}
