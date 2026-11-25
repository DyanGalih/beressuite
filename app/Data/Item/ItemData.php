<?php

namespace App\Data\Item;

use App\Data\Data;
use App\Http\Requests\ItemsRequest;

class ItemData extends Data
{
    public function __construct(
        public string $name,
        public float|int $price,
        public ?int $unit_id = null,
        public ?string $description = null,
        public ?array $taxes = [],
        public array $rawPayload = [],
    ) {
    }

    public static function fromRequest(ItemsRequest $request): self
    {
        $payload = $request->validated();
        $payload['company_id'] = $request->header('company');

        return new self(
            name: (string) $request->input('name'),
            price: $request->input('price'),
            unit_id: $request->input('unit_id') ? (int) $request->input('unit_id') : null,
            description: $request->input('description'),
            taxes: (array) ($request->input('taxes') ?? []),
            rawPayload: $payload,
        );
    }
}
