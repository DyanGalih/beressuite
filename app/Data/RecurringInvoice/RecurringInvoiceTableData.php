<?php

namespace App\Data\RecurringInvoice;

use App\Data\Common\CustomerSummaryData;
use App\Data\Data;
use App\Models\CompanySetting;
use App\Models\RecurringInvoice;
use Carbon\Carbon;

class RecurringInvoiceTableData extends Data
{
    public function __construct(
        public int $id,
        public ?string $starts_at,
        public ?string $formatted_starts_at,
        public ?int $customer_id,
        public ?CustomerSummaryData $customer,
        public string $status,
        public string $frequency,
        public float|int $total,
    ) {
    }

    public static function fromModel(RecurringInvoice $recurringInvoice): self
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $recurringInvoice->company_id) ?? config('app.date_format', 'Y-m-d');

        $customer = $recurringInvoice->relationLoaded('customer') && $recurringInvoice->customer
            ? CustomerSummaryData::fromModel($recurringInvoice->customer)
            : null;

        $startsAt = $recurringInvoice->starts_at instanceof \DateTimeInterface
            ? $recurringInvoice->starts_at->format('Y-m-d')
            : ($recurringInvoice->starts_at ? (string) $recurringInvoice->starts_at : null);

        return new self(
            id: (int) $recurringInvoice->id,
            starts_at: $startsAt,
            formatted_starts_at: $recurringInvoice->starts_at ? Carbon::parse($recurringInvoice->starts_at)->format($dateFormat) : null,
            customer_id: $recurringInvoice->customer_id ? (int) $recurringInvoice->customer_id : null,
            customer: $customer,
            status: (string) $recurringInvoice->status,
            frequency: (string) $recurringInvoice->frequency,
            total: (float) ($recurringInvoice->total ?? 0),
        );
    }
}
