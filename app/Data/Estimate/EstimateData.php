<?php

namespace App\Data\Estimate;

use App\Data\Data;
use App\Http\Requests\EstimatesRequest;

class EstimateData extends Data
{
    public function __construct(
        public int $customer_id,
        public string $estimate_date,
        public string $estimate_number,
        public string $template_name,
        public float $sub_total,
        public float $total,
        public float $tax,
        public float $discount,
        public float $discount_val,
        public array $items,
        public ?string $expiry_date = null,
        public ?string $discount_type = null,
        public float|int|null $exchange_rate = null,
        public ?int $currency_id = null,
        public ?string $reference_number = null,
        public ?string $notes = null,
        public ?string $tax_per_item = null,
        public ?string $discount_per_item = null,
        public ?string $sales_tax_type = null,
        public ?string $sales_tax_address_type = null,
        public ?array $taxes = [],
        public ?array $customFields = [],
        public ?bool $estimateSend = false,
        public ?string $subject = null,
        public ?string $body = null,
        public array $rawPayload = [],
    ) {
    }

    public static function fromRequest(EstimatesRequest $request): self
    {
        $payload = $request->getEstimatePayload();

        if (empty($payload['company_id'])) {
            $payload['company_id'] = $request->input('company_id') ?? $request->company_id ?? $request->header('company');
        }

        return new self(
            customer_id: (int) $request->input('customer_id'),
            estimate_date: (string) $request->input('estimate_date'),
            estimate_number: (string) $request->input('estimate_number'),
            template_name: (string) $request->input('template_name'),
            sub_total: (float) ($payload['sub_total'] ?? $request->input('sub_total')),
            total: (float) ($payload['total'] ?? $request->input('total')),
            tax: (float) ($payload['tax'] ?? $request->input('tax')),
            discount: (float) ($request->input('discount') ?? 0),
            discount_val: (float) ($payload['discount_val'] ?? $request->input('discount_val') ?? 0),
            items: (array) ($request->input('items') ?? []),
            expiry_date: $request->input('expiry_date'),
            discount_type: $request->input('discount_type'),
            exchange_rate: $payload['exchange_rate'] ?? $request->input('exchange_rate'),
            currency_id: isset($payload['currency_id']) ? (int) $payload['currency_id'] : ($request->input('currency_id') ? (int) $request->input('currency_id') : null),
            reference_number: $request->input('reference_number'),
            notes: $request->input('notes'),
            tax_per_item: $payload['tax_per_item'] ?? null,
            discount_per_item: $payload['discount_per_item'] ?? null,
            sales_tax_type: $request->input('sales_tax_type'),
            sales_tax_address_type: $request->input('sales_tax_address_type'),
            taxes: (array) ($request->input('taxes') ?? []),
            customFields: (array) ($request->input('customFields') ?? []),
            estimateSend: $request->has('estimateSend'),
            subject: $request->input('subject'),
            body: $request->input('body'),
            rawPayload: $payload,
        );
    }
}
