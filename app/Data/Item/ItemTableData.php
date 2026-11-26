<?php

namespace App\Data\Item;

use App\Data\Common\UnitSummaryData;
use App\Data\Data;
use App\Models\CompanySetting;
use App\Models\Item;
use Carbon\Carbon;

class ItemTableData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public float|int $price,
        public ?int $unit_id,
        public ?UnitSummaryData $unit = null,
        public ?string $created_at = null,
        public ?string $formatted_created_at = null,
    ) {
    }

    public static function fromModel(Item $item): self
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $item->company_id) ?? config('app.date_format', 'Y-m-d');

        $unit = $item->relationLoaded('unit') && $item->unit
            ? UnitSummaryData::fromModel($item->unit)
            : null;

        return new self(
            id: (int) $item->id,
            name: (string) $item->name,
            description: $item->description,
            price: (float) $item->price,
            unit_id: $item->unit_id ? (int) $item->unit_id : null,
            unit: $unit,
            created_at: $item->created_at instanceof \DateTimeInterface
                ? $item->created_at->format('Y-m-d')
                : ($item->created_at ? (string) $item->created_at : null),
            formatted_created_at: $item->created_at ? Carbon::parse($item->created_at)->format($dateFormat) : null,
        );
    }
}
