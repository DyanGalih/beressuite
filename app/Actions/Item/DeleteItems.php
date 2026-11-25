<?php

namespace App\Actions\Item;

use App\Actions\Action;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class DeleteItems implements Action
{
    /**
     * @param array<int|string> $ids
     */
    public function __invoke(array $ids): bool
    {
        return DB::transaction(function () use ($ids): bool {
            Item::destroy($ids);

            return true;
        });
    }
}
