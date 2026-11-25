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

class UpdatePayment implements Action
{
    public function __invoke(Payment $payment, PaymentData $data, int|string|null $companyId = null): Payment
    {
        return DB::transaction(function () use ($payment, $data, $companyId): Payment {
            $payload = $data->rawPayload;

            if (empty($payload)) {
                $payload = [
                    'payment_date' => $data->payment_date,
                    'payment_number' => $data->payment_number,
                    'amount' => $data->amount,
                    'customer_id' => $data->customer_id,
                    'payment_method_id' => $data->payment_method_id,
                    'invoice_id' => $data->invoice_id,
                    'notes' => $data->notes,
                ];
            }

            $payload['amount'] = $data->amount;

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? $payment->company_id;
            }

            $hasInvoiceIdInPayload = array_key_exists('invoice_id', $payload);
            $targetInvoiceId = $hasInvoiceIdInPayload ? $data->invoice_id : $payment->invoice_id;

            if ($targetInvoiceId && (! $payment->invoice_id || $payment->invoice_id !== $targetInvoiceId)) {
                $invoice = Invoice::find($targetInvoiceId);
                if ($invoice) {
                    $invoice->subtractInvoicePayment($data->amount);
                }
            }

            if ($payment->invoice_id && $hasInvoiceIdInPayload && $data->invoice_id === null) {
                $invoice = Invoice::find($payment->invoice_id);
                if ($invoice) {
                    $invoice->addInvoicePayment($payment->amount);
                }
            }

            if ($payment->invoice_id && $payment->invoice_id === $targetInvoiceId && $data->amount !== (float) $payment->amount) {
                $invoice = Invoice::find($payment->invoice_id);
                if ($invoice) {
                    $invoice->addInvoicePayment($payment->amount);
                    $invoice->subtractInvoicePayment($data->amount);
                }
            }

            $serial = (new SerialNumberFormatter())
                ->setModel($payment)
                ->setCompany($payment->company_id)
                ->setCustomer($data->customer_id)
                ->setModelObject($payment->id)
                ->setNextNumbers();

            $payload['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;
            $payment->update($payload);

            if ($companyId !== null) {
                $companyCurrency = CompanySetting::getSetting('currency', $companyId);

                if ((string) ($payload['currency_id'] ?? '') !== $companyCurrency) {
                    ExchangeRateLog::addExchangeRateLog($payment);
                }
            }

            if (! empty($data->customFields)) {
                $payment->updateCustomFields($data->customFields);
            }

            return Payment::with([
                'customer',
                'invoice',
                'paymentMethod',
            ])->find($payment->id);
        });
    }
}
