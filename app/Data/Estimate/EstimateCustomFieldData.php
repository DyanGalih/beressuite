<?php

namespace App\Data\Estimate;

use App\Data\Data;

class EstimateCustomFieldData extends Data
{
    public function __construct(
        public int $id,
        public mixed $value = null,
    ) {
    }
}
