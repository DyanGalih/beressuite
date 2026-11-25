<?php

namespace App\Actions\Estimate;

use App\Actions\Action;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\SerialNumberFormatter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class ConvertEstimateToInvoice implements Action
{
    public function __invoke(Estimate $estimate, int|string|null $companyId = null): Invoice
    {
        return DB::transaction(function () use ($estimate, $companyId): Invoice {
            $companyId = $companyId ?? $estimate->company_id;

            $estimate->load(['items', 'items.taxes', 'customer', 'taxes']);

            $invoiceDate = Carbon::now();
            $dueDate = null;

            $dueDateEnabled = CompanySetting::getSetting(
                'invoice_set_due_date_automatically',
                $companyId
            );

            if ($dueDateEnabled === 'YES') {
                $dueDateDays = CompanySetting::getSetting(
                    'invoice_due_date_days',
                    $companyId
                );
                $dueDate = Carbon::now()->addDays((int) $dueDateDays)->format('Y-m-d');
            }

            $invoiceModel = new Invoice();

            $serial = (new SerialNumberFormatter())
                ->setModel($invoiceModel)
                ->setCompany($estimate->company_id)
                ->setCustomer($estimate->customer_id)
                ->setNextNumbers();

            $templateName = $estimate->getInvoiceTemplateName();
            $exchangeRate = $estimate->exchange_rate;

            $invoice = Invoice::create([
                'creator_id' => Auth::id(),
                'invoice_date' => $invoiceDate->format('Y-m-d'),
                'due_date' => $dueDate,
                'invoice_number' => $serial->getNextNumber(),
                'sequence_number' => $serial->nextSequenceNumber,
                'customer_sequence_number' => $serial->nextCustomerSequenceNumber,
                'reference_number' => $serial->getNextNumber(),
                'customer_id' => $estimate->customer_id,
                'company_id' => $companyId,
                'template_name' => $templateName,
                'status' => Invoice::STATUS_DRAFT,
                'paid_status' => Invoice::STATUS_UNPAID,
                'sub_total' => $estimate->sub_total,
                'discount' => $estimate->discount,
                'discount_type' => $estimate->discount_type,
                'discount_val' => $estimate->discount_val,
                'total' => $estimate->total,
                'due_amount' => $estimate->total,
                'tax_per_item' => $estimate->tax_per_item,
                'discount_per_item' => $estimate->discount_per_item,
                'tax' => $estimate->tax,
                'notes' => $estimate->notes,
                'exchange_rate' => $exchangeRate,
                'base_discount_val' => $estimate->discount_val * $exchangeRate,
                'base_sub_total' => $estimate->sub_total * $exchangeRate,
                'base_total' => $estimate->total * $exchangeRate,
                'base_tax' => $estimate->tax * $exchangeRate,
                'currency_id' => $estimate->currency_id,
                'sales_tax_type' => $estimate->sales_tax_type,
                'sales_tax_address_type' => $estimate->sales_tax_address_type,
            ]);

            $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);
            $invoice->save();

            $invoiceItems = $estimate->items->toArray();

            foreach ($invoiceItems as $invoiceItem) {
                $invoiceItem['company_id'] = $companyId;
                $invoiceItem['exchange_rate'] = $exchangeRate;
                $invoiceItem['base_price'] = $invoiceItem['price'] * $exchangeRate;
                $invoiceItem['base_discount_val'] = $invoiceItem['discount_val'] * $exchangeRate;
                $invoiceItem['base_tax'] = ($invoiceItem['tax'] ?? 0) * $exchangeRate;
                $invoiceItem['base_total'] = $invoiceItem['total'] * $exchangeRate;

                $item = $invoice->items()->create($invoiceItem);

                if (array_key_exists('taxes', $invoiceItem) && $invoiceItem['taxes']) {
                    foreach ($invoiceItem['taxes'] as $tax) {
                        $tax['company_id'] = $companyId;

                        if ($tax['amount']) {
                            $item->taxes()->create($tax);
                        }
                    }
                }
            }

            if ($estimate->taxes) {
                foreach ($estimate->taxes->toArray() as $tax) {
                    $tax['company_id'] = $companyId;
                    $tax['exchange_rate'] = $exchangeRate;
                    $tax['base_amount'] = $tax['amount'] * $exchangeRate;
                    $tax['currency_id'] = $estimate->currency_id;
                    unset($tax['estimate_id']);

                    $invoice->taxes()->create($tax);
                }
            }

            $estimate->checkForEstimateConvertAction();

            return Invoice::with([
                'items',
                'items.taxes',
                'customer',
                'taxes',
            ])->find($invoice->id);
        });
    }
}
