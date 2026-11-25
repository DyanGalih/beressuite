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

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? $payment->company_id;
            }

            if ($data->invoice_id && (! $payment->invoice_id || $payment->invoice_id !== $data->invoice_id)) {
                $invoice = Invoice::find($data->invoice_id);
                if ($invoice) {
                    $invoice->subtractInvoicePayment($data->amount);
                }
            }

            if ($payment->invoice_id && (! $data->invoice_id || $payment->invoice_id !== $data->invoice_id)) {
                $invoice = Invoice::find($payment->invoice_id);
                if ($invoice) {
                    $invoice->addInvoicePayment($payment->amount);
                }
            }

            if ($payment->invoice_id && $payment->invoice_id === $data->invoice_id && $data->amount !== (float) $payment->amount) {
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
