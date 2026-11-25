<?php

namespace App\Actions\Expense;

use App\Actions\Action;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class DeleteExpenses implements Action
{
    /**
     * @param array<int|string> $ids
     */
    public function __invoke(array $ids): bool
    {
        return DB::transaction(function () use ($ids): bool {
            Expense::destroy($ids);

            return true;
        });
    }
}
