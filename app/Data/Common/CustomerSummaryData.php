<?php

namespace App\Data\Common;

use App\Data\Data;
use App\Models\Customer;

class CustomerSummaryData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $contact_name = null,
        public ?string $email = null,
        public ?int $currency_id = null,
        public ?CurrencySummaryData $currency = null,
    ) {
    }

    public static function fromModel(?Customer $customer): ?self
    {
        if (! $customer) {
            return null;
        }

        return new self(
            id: (int) $customer->id,
            name: (string) $customer->name,
            contact_name: $customer->contact_name,
            email: $customer->email,
            currency_id: $customer->currency_id ? (int) $customer->currency_id : null,
            currency: $customer->relationLoaded('currency') && $customer->currency
                ? CurrencySummaryData::fromModel($customer->currency)
                : null,
        );
    }
}
