<?php

namespace App\Data\Invoice;

use App\Data\Common\CurrencySummaryData;
use App\Data\Common\CustomerSummaryData;
use App\Data\Data;
use App\Models\CompanySetting;
use App\Models\Invoice;
use Carbon\Carbon;

class InvoiceTableData extends Data
{
    public function __construct(
        public int $id,
        public string $invoice_number,
        public ?string $invoice_date,
        public ?string $formatted_invoice_date,
        public ?string $due_date,
        public ?string $formatted_due_date,
        public string $status,
        public string $paid_status,
        public float|int $total,
        public float|int $due_amount,
        public bool $overdue,
        public ?int $customer_id,
        public ?int $currency_id,
        public ?CurrencySummaryData $currency,
        public ?CustomerSummaryData $customer,
        public bool $allow_edit,
        public ?string $invoice_pdf_url = null,
        public ?string $unique_hash = null,
        public ?string $formatted_created_at = null,
    ) {
    }

    public static function fromModel(Invoice $invoice): self
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $invoice->company_id) ?? config('app.date_format', 'Y-m-d');

        $customer = $invoice->relationLoaded('customer') && $invoice->customer
            ? CustomerSummaryData::fromModel($invoice->customer)
            : null;

        $currency = $invoice->relationLoaded('currency') && $invoice->currency
            ? CurrencySummaryData::fromModel($invoice->currency)
            : ($customer?->currency ?? null);

        $invoiceDate = $invoice->invoice_date instanceof \DateTimeInterface
            ? $invoice->invoice_date->format('Y-m-d')
            : ($invoice->invoice_date ? (string) $invoice->invoice_date : null);

        $dueDate = $invoice->due_date instanceof \DateTimeInterface
            ? $invoice->due_date->format('Y-m-d')
            : ($invoice->due_date ? (string) $invoice->due_date : null);

        return new self(
            id: (int) $invoice->id,
            invoice_number: (string) $invoice->invoice_number,
            invoice_date: $invoiceDate,
            formatted_invoice_date: $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format($dateFormat) : null,
            due_date: $dueDate,
            formatted_due_date: $invoice->due_date ? Carbon::parse($invoice->due_date)->format($dateFormat) : null,
            status: (string) $invoice->status,
            paid_status: (string) $invoice->paid_status,
            total: (float) ($invoice->total ?? 0),
            due_amount: (float) ($invoice->due_amount ?? 0),
            overdue: (bool) ($invoice->overdue ?? false),
            customer_id: $invoice->customer_id ? (int) $invoice->customer_id : null,
            currency_id: $invoice->currency_id ? (int) $invoice->currency_id : ($currency?->id ?? null),
            currency: $currency,
            customer: $customer,
            allow_edit: (bool) ($invoice->allow_edit ?? true),
            invoice_pdf_url: $invoice->invoicePdfUrl,
            unique_hash: $invoice->unique_hash,
            formatted_created_at: $invoice->created_at ? Carbon::parse($invoice->created_at)->format($dateFormat) : null,
        );
    }
}
