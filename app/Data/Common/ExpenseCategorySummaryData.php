<?php

namespace App\Data\Common;

use App\Data\Data;
use App\Models\ExpenseCategory;

class ExpenseCategorySummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public static function fromModel(?ExpenseCategory $category): ?self
    {
        if (! $category) {
            return null;
        }

        return new self(
            id: (int) $category->id,
            name: (string) $category->name,
        );
    }
}
