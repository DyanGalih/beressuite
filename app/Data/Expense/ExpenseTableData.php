<?php

namespace App\Data\Expense;

use App\Data\Common\CurrencySummaryData;
use App\Data\Common\CustomerSummaryData;
use App\Data\Common\ExpenseCategorySummaryData;
use App\Data\Data;
use App\Models\CompanySetting;
use App\Models\Expense;
use Carbon\Carbon;

class ExpenseTableData extends Data
{
    public function __construct(
        public int $id,
        public ?string $expense_date,
        public ?string $formatted_expense_date,
        public float|int $amount,
        public ?string $notes = null,
        public ?int $expense_category_id = null,
        public ?ExpenseCategorySummaryData $expense_category = null,
        public ?int $customer_id = null,
        public ?CustomerSummaryData $customer = null,
        public ?int $currency_id = null,
        public ?CurrencySummaryData $currency = null,
        public ?array $attachment_receipt_url = null,
    ) {
    }

    public static function fromModel(Expense $expense): self
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $expense->company_id) ?? config('app.date_format', 'Y-m-d');

        $customer = $expense->relationLoaded('customer') && $expense->customer
            ? CustomerSummaryData::fromModel($expense->customer)
            : null;

        $currency = $expense->relationLoaded('currency') && $expense->currency
            ? CurrencySummaryData::fromModel($expense->currency)
            : ($customer?->currency ?? null);

        $category = $expense->relationLoaded('category') && $expense->category
            ? ExpenseCategorySummaryData::fromModel($expense->category)
            : null;

        $expenseDate = $expense->expense_date instanceof \DateTimeInterface
            ? $expense->expense_date->format('Y-m-d')
            : ($expense->expense_date ? (string) $expense->expense_date : null);

        return new self(
            id: (int) $expense->id,
            expense_date: $expenseDate,
            formatted_expense_date: $expense->expense_date ? Carbon::parse($expense->expense_date)->format($dateFormat) : null,
            amount: (float) ($expense->amount ?? 0),
            notes: $expense->notes,
            expense_category_id: $expense->expense_category_id ? (int) $expense->expense_category_id : null,
            expense_category: $category,
            customer_id: $expense->customer_id ? (int) $expense->customer_id : null,
            customer: $customer,
            currency_id: $expense->currency_id ? (int) $expense->currency_id : ($currency?->id ?? null),
            currency: $currency,
            attachment_receipt_url: $expense->relationLoaded('media') && $expense->media->isNotEmpty() ? $expense->receipt_url : null,
        );
    }
}
