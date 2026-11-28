<?php

namespace App\Actions\Item;

use App\Actions\Action;
use App\Data\Item\ItemData;
use App\Models\CompanySetting;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateItem implements Action
{
    public function __invoke(ItemData $data, int|string|null $companyId = null): Item
    {
        return DB::transaction(function () use ($data, $companyId): Item {
            $payload = $data->rawPayload;

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? null;
            }

            $payload['company_id'] = $companyId;
            $payload['creator_id'] = $payload['creator_id'] ?? Auth::id();
            $payload['currency_id'] = CompanySetting::getSetting('currency', $companyId);

            $item = Item::create($payload);

            if (! empty($data->taxes)) {
                foreach ($data->taxes as $tax) {
                    $item->tax_per_item = true;
                    $item->save();
                    $tax['company_id'] = $companyId;
                    $item->taxes()->create($tax);
                }
            }

            return Item::with('taxes.taxType')->find($item->id);
        });
    }
}
