<?php

namespace App\Data\Payment;

use App\Data\Data;
use App\Http\Requests\PaymentRequest;

class PaymentData extends Data
{
    public function __construct(
        public int $customer_id,
        public string $payment_date,
        public float $amount,
        public int $payment_method_id,
        public ?string $payment_number = null,
        public ?int $invoice_id = null,
        public ?string $notes = null,
        public float|int|null $exchange_rate = null,
        public ?int $currency_id = null,
        public ?array $customFields = [],
        public array $rawPayload = [],
    ) {
    }

    public static function fromRequest(PaymentRequest $request): self
    {
        $payload = $request->getPaymentPayload();

        if (empty($payload['company_id'])) {
            $payload['company_id'] = $request->input('company_id') ?? $request->company_id ?? $request->header('company');
        }

        return new self(
            customer_id: (int) $request->input('customer_id'),
            payment_date: (string) $request->input('payment_date'),
            amount: (float) $request->input('amount'),
            payment_method_id: (int) $request->input('payment_method_id'),
            payment_number: $request->input('payment_number'),
            invoice_id: $request->input('invoice_id') ? (int) $request->input('invoice_id') : null,
            notes: $request->input('notes'),
            exchange_rate: $payload['exchange_rate'] ?? $request->input('exchange_rate'),
            currency_id: isset($payload['currency_id']) ? (int) $payload['currency_id'] : ($request->input('currency_id') ? (int) $request->input('currency_id') : null),
            customFields: (array) ($request->input('customFields') ?? []),
            rawPayload: $payload,
        );
    }
}
