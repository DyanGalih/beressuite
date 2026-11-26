<?php

namespace App\Data\Estimate;

use App\Data\Common\CurrencySummaryData;
use App\Data\Common\CustomerSummaryData;
use App\Data\Data;
use App\Models\CompanySetting;
use App\Models\Estimate;
use Carbon\Carbon;

class EstimateTableData extends Data
{
    public function __construct(
        public int $id,
        public string $estimate_number,
        public ?string $estimate_date,
        public ?string $formatted_estimate_date,
        public ?string $expiry_date,
        public ?string $formatted_expiry_date,
        public string $status,
        public float|int $total,
        public ?int $customer_id,
        public ?int $currency_id,
        public ?CurrencySummaryData $currency,
        public ?CustomerSummaryData $customer,
        public ?string $estimate_pdf_url = null,
        public ?string $unique_hash = null,
    ) {
    }

    public static function fromModel(Estimate $estimate): self
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $estimate->company_id) ?? config('app.date_format', 'Y-m-d');

        $customer = $estimate->relationLoaded('customer') && $estimate->customer
            ? CustomerSummaryData::fromModel($estimate->customer)
            : null;

        $currency = $estimate->relationLoaded('currency') && $estimate->currency
            ? CurrencySummaryData::fromModel($estimate->currency)
            : ($customer?->currency ?? null);

        $estimateDate = $estimate->estimate_date instanceof \DateTimeInterface
            ? $estimate->estimate_date->format('Y-m-d')
            : ($estimate->estimate_date ? (string) $estimate->estimate_date : null);

        $expiryDate = $estimate->expiry_date instanceof \DateTimeInterface
            ? $estimate->expiry_date->format('Y-m-d')
            : ($estimate->expiry_date ? (string) $estimate->expiry_date : null);

        return new self(
            id: (int) $estimate->id,
            estimate_number: (string) $estimate->estimate_number,
            estimate_date: $estimateDate,
            formatted_estimate_date: $estimate->estimate_date ? Carbon::parse($estimate->estimate_date)->format($dateFormat) : null,
            expiry_date: $expiryDate,
            formatted_expiry_date: $estimate->expiry_date ? Carbon::parse($estimate->expiry_date)->format($dateFormat) : null,
            status: (string) $estimate->status,
            total: (float) ($estimate->total ?? 0),
            customer_id: $estimate->customer_id ? (int) $estimate->customer_id : null,
            currency_id: $estimate->currency_id ? (int) $estimate->currency_id : ($currency?->id ?? null),
            currency: $currency,
            customer: $customer,
            estimate_pdf_url: $estimate->estimatePdfUrl,
            unique_hash: $estimate->unique_hash,
        );
    }
}
