<?php

namespace App\Data\Customer;

use App\Data\Common\CurrencySummaryData;
use App\Data\Data;
use App\Models\CompanySetting;
use App\Models\Customer;
use Carbon\Carbon;

class CustomerTableData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $contact_name,
        public ?string $phone,
        public ?string $email,
        public float|int $due_amount,
        public ?int $currency_id,
        public ?CurrencySummaryData $currency,
        public ?string $formatted_created_at = null,
    ) {
    }

    public static function fromModel(Customer $customer): self
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $customer->company_id) ?? config('app.date_format', 'Y-m-d');

        $currency = $customer->relationLoaded('currency') && $customer->currency
            ? CurrencySummaryData::fromModel($customer->currency)
            : null;

        return new self(
            id: (int) $customer->id,
            name: (string) $customer->name,
            contact_name: $customer->contact_name,
            phone: $customer->phone,
            email: $customer->email,
            due_amount: (float) ($customer->due_amount ?? 0),
            currency_id: $customer->currency_id ? (int) $customer->currency_id : null,
            currency: $currency,
            formatted_created_at: $customer->created_at ? Carbon::parse($customer->created_at)->format($dateFormat) : null,
        );
    }
}
