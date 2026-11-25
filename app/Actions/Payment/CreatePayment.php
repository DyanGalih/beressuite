<?php

namespace App\Actions\Payment;

use App\Actions\Action;
use App\Data\Payment\PaymentData;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\SerialNumberFormatter;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class CreatePayment implements Action
{
    public function __invoke(PaymentData $data, int|string|null $companyId = null): Payment
    {
        return DB::transaction(function () use ($data, $companyId): Payment {
            $payload = $data->rawPayload;

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? null;
            }

            if ($data->invoice_id) {
                $invoice = Invoice::find($data->invoice_id);
                if ($invoice) {
                    $invoice->subtractInvoicePayment($data->amount);
                }
            }

            $payment = Payment::create($payload);
            $payment->unique_hash = Hashids::connection(Payment::class)->encode($payment->id);

            $serial = (new SerialNumberFormatter())
                ->setModel($payment)
                ->setCompany($payment->company_id)
                ->setCustomer($payment->customer_id)
                ->setNextNumbers();

            $payment->sequence_number = $serial->nextSequenceNumber;
            $payment->customer_sequence_number = $serial->nextCustomerSequenceNumber;
            $payment->save();

            if ($companyId !== null) {
                $companyCurrency = CompanySetting::getSetting('currency', $companyId);

                if ((string) ($payload['currency_id'] ?? '') !== $companyCurrency) {
                    ExchangeRateLog::addExchangeRateLog($payment);
                }
            }

            if (! empty($data->customFields)) {
                $payment->addCustomFields($data->customFields);
            }

            return Payment::with([
                'customer',
                'invoice',
                'paymentMethod',
                'fields',
            ])->find($payment->id);
        });
    }
}
