<?php

namespace App\Actions\Invoice;

use App\Actions\Action;
use App\Data\Invoice\InvoiceData;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Services\SerialNumberFormatter;
use Illuminate\Support\Facades\DB;

class UpdateInvoice implements Action
{
    public function __invoke(Invoice $invoice, InvoiceData $data, int|string|null $companyId = null): Invoice|string
    {
        return DB::transaction(function () use ($invoice, $data, $companyId): Invoice|string {
            $companyId = $companyId ?? $invoice->company_id;

            $serial = (new SerialNumberFormatter())
                ->setModel($invoice)
                ->setCompany($invoice->company_id)
                ->setCustomer($data->customer_id)
                ->setModelObject($invoice->id)
                ->setNextNumbers();

            $payload = $data->rawPayload;
            $payload['customer_id'] = $payload['customer_id'] ?? $data->customer_id;
            $payload['currency_id'] = $payload['currency_id'] ?? $data->currency_id ?? $invoice->currency_id;
            $payload['exchange_rate'] = $payload['exchange_rate'] ?? $data->exchange_rate ?? $invoice->exchange_rate ?? 1;
            $payload['total'] = $data->total;
            $oldTotal = $invoice->total;

            $totalPaidAmount = $invoice->total - $invoice->due_amount;

            if ($totalPaidAmount > 0 && $invoice->customer_id !== $data->customer_id) {
                return 'customer_cannot_be_changed_after_payment_is_added';
            }

            if ($data->total < $totalPaidAmount) {
                return 'total_invoice_amount_must_be_more_than_paid_amount';
            }

            if ($oldTotal != $data->total) {
                $diff = (int) round($data->total) - (int) $oldTotal;
            } else {
                $diff = 0;
            }

            $payload['due_amount'] = ($invoice->due_amount + $diff);
            $exchangeRate = $payload['exchange_rate'] ?? 1;
            $payload['base_due_amount'] = $payload['due_amount'] * $exchangeRate;
            $payload['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

            $invoice->changeInvoiceStatus($payload['due_amount']);
            $invoice->update($payload);

            if ($companyId !== null) {
                $companyCurrency = CompanySetting::getSetting('currency', $companyId);

                if ((string) $payload['currency_id'] !== $companyCurrency) {
                    ExchangeRateLog::addExchangeRateLog($invoice);
                }
            }

            foreach ($invoice->items as $item) {
                $item->fields()->delete();
            }

            $invoice->items()->delete();
            $invoice->taxes()->delete();

            Invoice::createItems($invoice, $data->items);

            if (! empty($data->taxes)) {
                Invoice::createTaxes($invoice, $data->taxes);
            }

            if (! empty($data->customFields)) {
                $invoice->updateCustomFields($data->customFields);
            }

            $invoice->load([
                'items',
                'items.taxes',
                'items.taxes.taxType',
                'items.fields',
                'items.fields.customField',
                'customer',
                'taxes',
                'fields',
                'fields.customField',
            ]);

            return $invoice;
        });
    }
}
