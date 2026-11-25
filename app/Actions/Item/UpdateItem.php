<?php

namespace App\Actions\Item;

use App\Actions\Action;
use App\Data\Item\ItemData;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class UpdateItem implements Action
{
    public function __invoke(Item $item, ItemData $data, int|string|null $companyId = null): Item
    {
        return DB::transaction(function () use ($item, $data, $companyId): Item {
            $payload = $data->rawPayload;

            $companyId = $item->company_id;
            $payload['company_id'] = $item->company_id;

            $item->update($payload);
            $item->taxes()->delete();

            if (! empty($data->taxes)) {
                foreach ($data->taxes as $tax) {
                    $item->tax_per_item = true;
                    $item->save();
                    $tax['company_id'] = $companyId;
                    $item->taxes()->create($tax);
                }
            }

            return Item::with('taxes')->find($item->id);
        });
    }
}
