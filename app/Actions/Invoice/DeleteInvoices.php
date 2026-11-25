<?php

namespace App\Actions\Invoice;

use App\Actions\Action;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class DeleteInvoices implements Action
{
    public function __invoke(iterable $ids, int|string|null $companyId = null): void
    {
        DB::transaction(function () use ($ids, $companyId): void {
            foreach ($ids as $id) {
                $query = Invoice::query();

                if ($companyId !== null) {
                    $query->where('company_id', $companyId);
                }

                $invoice = $query->find($id);

                if ($invoice) {
                    if ($invoice->transactions()->exists()) {
                        $invoice->transactions()->delete();
                    }

                    $invoice->delete();
                }
            }
        });
    }
}
