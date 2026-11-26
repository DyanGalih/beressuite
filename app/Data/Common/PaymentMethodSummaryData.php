<?php

namespace App\Data\Common;

use App\Data\Data;
use App\Models\PaymentMethod;

class PaymentMethodSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public static function fromModel(?PaymentMethod $method): ?self
    {
        if (! $method) {
            return null;
        }

        return new self(
            id: (int) $method->id,
            name: (string) $method->name,
        );
    }
}
