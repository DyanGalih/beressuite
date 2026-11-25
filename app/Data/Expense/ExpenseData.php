<?php

namespace App\Data\Expense;

use App\Data\Data;
use App\Http\Requests\ExpenseRequest;

class ExpenseData extends Data
{
    public function __construct(
        public int $expense_category_id,
        public string $expense_date,
        public float $amount,
        public ?int $customer_id = null,
        public ?int $payment_method_id = null,
        public ?string $notes = null,
        public float|int|null $exchange_rate = null,
        public ?int $currency_id = null,
        public mixed $customFields = null,
        public bool $is_attachment_receipt_removed = false,
        public array $rawPayload = [],
    ) {
    }

    public static function fromRequest(ExpenseRequest $request): self
    {
        $payload = $request->getExpensePayload();

        if (empty($payload['company_id'])) {
            $payload['company_id'] = $request->input('company_id') ?? $request->company_id ?? $request->header('company');
        }

        return new self(
            expense_category_id: (int) $request->input('expense_category_id'),
            expense_date: (string) $request->input('expense_date'),
            amount: (float) $request->input('amount'),
            customer_id: $request->input('customer_id') ? (int) $request->input('customer_id') : null,
            payment_method_id: $request->input('payment_method_id') ? (int) $request->input('payment_method_id') : null,
            notes: $request->input('notes'),
            exchange_rate: $payload['exchange_rate'] ?? $request->input('exchange_rate'),
            currency_id: isset($payload['currency_id']) ? (int) $payload['currency_id'] : ($request->input('currency_id') ? (int) $request->input('currency_id') : null),
            customFields: $request->customFields,
            is_attachment_receipt_removed: (bool) $request->input('is_attachment_receipt_removed', false),
            rawPayload: $payload,
        );
    }
}
