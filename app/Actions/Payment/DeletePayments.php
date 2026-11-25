<?php

namespace App\Actions\Payment;

use App\Actions\Action;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class DeletePayments implements Action
{
    /**
     * @param array<int|string> $ids
     */
    public function __invoke(array $ids): bool
    {
        return DB::transaction(function () use ($ids): bool {
            foreach ($ids as $id) {
                $payment = Payment::find($id);

                if ($payment) {
                    if ($payment->invoice_id !== null) {
                        $invoice = Invoice::find($payment->invoice_id);
                        if ($invoice) {
                            $invoice->due_amount = ((int) $invoice->due_amount + (int) $payment->amount);
                            $exchangeRate = $invoice->exchange_rate ?? 1;
                            $invoice->base_due_amount = (float) $invoice->due_amount * (float) $exchangeRate;

                            if ($invoice->due_amount == $invoice->total) {
                                $invoice->paid_status = Invoice::STATUS_UNPAID;
                            } else {
                                $invoice->paid_status = Invoice::STATUS_PARTIALLY_PAID;
                            }

                            $invoice->status = $invoice->getPreviousStatus();
                            $invoice->save();
                        }
                    }

                    $payment->delete();
                }
            }

            return true;
        });
    }
}
