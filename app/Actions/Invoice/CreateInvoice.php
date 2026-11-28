<?php

namespace App\Actions\Invoice;

use App\Actions\Action;
use App\Data\Invoice\InvoiceData;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Invoice;
use App\Services\SerialNumberFormatter;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class CreateInvoice implements Action
{
    public function __invoke(InvoiceData $data, int|string|null $companyId = null): Invoice
    {
        return DB::transaction(function () use ($data, $companyId): Invoice {
            $payload = $data->rawPayload;

            $needsFallback = empty($payload) || (count($payload) === 1 && isset($payload['company_id'])) || ! isset($payload['customer_id']);
            if ($needsFallback) {
                $exchangeRate = $payload['exchange_rate'] ?? $data->exchange_rate ?? 1;
                $payload = array_merge([
                    'company_id' => $companyId,
                    'customer_id' => $data->customer_id,
                    'invoice_date' => $data->invoice_date,
                    'invoice_number' => $data->invoice_number,
                    'template_name' => $data->template_name,
                    'sub_total' => $data->sub_total,
                    'total' => $data->total,
                    'tax' => $data->tax,
                    'discount' => $data->discount,
                    'discount_val' => $data->discount_val,
                    'due_date' => $data->due_date,
                    'due_amount' => $data->due_amount ?? $data->total,
                    'base_discount_val' => ($data->discount_val ?? 0) * $exchangeRate,
                    'base_sub_total' => ($data->sub_total ?? 0) * $exchangeRate,
                    'base_total' => ($data->total ?? 0) * $exchangeRate,
                    'base_tax' => ($data->tax ?? 0) * $exchangeRate,
                    'base_due_amount' => ($data->due_amount ?? $data->total) * $exchangeRate,
                    'discount_type' => $data->discount_type,
                    'exchange_rate' => $exchangeRate,
                    'currency_id' => $data->currency_id,
                    'reference_number' => $data->reference_number,
                    'notes' => $data->notes,
                    'tax_per_item' => $data->tax_per_item,
                    'discount_per_item' => $data->discount_per_item,
                    'sales_tax_type' => $data->sales_tax_type,
                    'sales_tax_address_type' => $data->sales_tax_address_type,
                ], $payload);
            }

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? null;
            }

            if ($companyId !== null && empty($payload['company_id'])) {
                $payload['company_id'] = $companyId;
            }

            if ($data->invoiceSend) {
                $payload['status'] = Invoice::STATUS_SENT;
            }

            $invoice = Invoice::create($payload);

            $serial = (new SerialNumberFormatter())
                ->setModel($invoice)
                ->setCompany($invoice->company_id)
                ->setCustomer($invoice->customer_id)
                ->setNextNumbers();

            $invoice->sequence_number = $serial->nextSequenceNumber;
            $invoice->customer_sequence_number = $serial->nextCustomerSequenceNumber;
            $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
            $invoice->save();

            Invoice::createItems($invoice, $data->items);

            if ($companyId !== null) {
                $companyCurrency = CompanySetting::getSetting('currency', $companyId);

                if ((string) $payload['currency_id'] !== $companyCurrency) {
                    ExchangeRateLog::addExchangeRateLog($invoice);
                }
            }

            if (! empty($data->taxes)) {
                Invoice::createTaxes($invoice, $data->taxes);
            }

            if (! empty($data->customFields)) {
                $invoice->addCustomFields($data->customFields);
            }

            return Invoice::with([
                'items',
                'items.taxes',
                'items.taxes.taxType',
                'items.fields',
                'items.fields.customField',
                'customer',
                'taxes',
            ])->find($invoice->id);
        });
    }
}
