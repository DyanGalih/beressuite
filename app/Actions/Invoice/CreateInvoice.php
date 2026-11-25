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

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? null;
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
                'items.fields',
                'items.fields.customField',
                'customer',
                'taxes',
            ])->find($invoice->id);
        });
    }
}
