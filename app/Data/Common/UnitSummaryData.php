<?php

namespace App\Data\Common;

use App\Data\Data;
use App\Models\Unit;

class UnitSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public static function fromModel(?Unit $unit): ?self
    {
        if (! $unit) {
            return null;
        }

        return new self(
            id: (int) $unit->id,
            name: (string) $unit->name,
        );
    }
}
