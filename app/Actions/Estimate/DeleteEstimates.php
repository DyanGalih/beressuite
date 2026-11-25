<?php

namespace App\Actions\Estimate;

use App\Actions\Action;
use App\Models\Estimate;
use Illuminate\Support\Facades\DB;

class DeleteEstimates implements Action
{
    /**
     * @param array<int|string> $ids
     */
    public function __invoke(array $ids): bool
    {
        return DB::transaction(function () use ($ids): bool {
            foreach ($ids as $id) {
                $estimate = Estimate::find($id);

                if ($estimate) {
                    $estimate->items()->delete();
                    $estimate->taxes()->delete();
                    $estimate->delete();
                }
            }

            return true;
        });
    }
}
