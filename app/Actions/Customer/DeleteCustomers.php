<?php

namespace App\Actions\Customer;

use App\Actions\Action;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class DeleteCustomers implements Action
{
    /**
     * @param array<int|string> $ids
     */
    public function __invoke(array $ids): bool
    {
        return DB::transaction(function () use ($ids): bool {
            foreach ($ids as $id) {
                $customer = Customer::find($id);
                if ($customer) {
                    if ($customer->estimates()->exists()) {
                        $customer->estimates()->delete();
                    }

                    if ($customer->invoices()->exists()) {
                        foreach ($customer->invoices as $invoice) {
                            if ($invoice->transactions()->exists()) {
                                $invoice->transactions()->delete();
                            }
                            $invoice->delete();
                        }
                    }

                    if ($customer->payments()->exists()) {
                        $customer->payments()->delete();
                    }

                    if ($customer->addresses()->exists()) {
                        $customer->addresses()->delete();
                    }

                    if ($customer->expenses()->exists()) {
                        $customer->expenses()->delete();
                    }

                    if ($customer->recurringInvoices()->exists()) {
                        foreach ($customer->recurringInvoices as $recurringInvoice) {
                            if ($recurringInvoice->items()->exists()) {
                                $recurringInvoice->items()->delete();
                            }

                            $recurringInvoice->delete();
                        }
                    }

                    $customer->delete();
                }
            }

            return true;
        });
    }
}
