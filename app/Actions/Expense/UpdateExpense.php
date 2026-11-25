<?php

namespace App\Actions\Expense;

use App\Actions\Action;
use App\Data\Expense\ExpenseData;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateExpense implements Action
{
    public function __invoke(Expense $expense, ExpenseData $data, ?Request $request = null, int|string|null $companyId = null): Expense
    {
        return DB::transaction(function () use ($expense, $data, $request, $companyId): Expense {
            $payload = $data->rawPayload;

            if (empty($payload)) {
                $payload = [
                    'expense_category_id' => $data->expense_category_id,
                    'amount' => $data->amount,
                    'expense_date' => $data->expense_date,
                    'notes' => $data->notes,
                    'customer_id' => $data->customer_id,
                    'exchange_rate' => $data->exchange_rate ?? 1,
                    'currency_id' => $data->currency_id,
                ];
            }

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? $expense->company_id;
            }

            $expense->update($payload);

            if ($companyId !== null) {
                $companyCurrency = CompanySetting::getSetting('currency', $companyId);

                if ((string) ($payload['currency_id'] ?? '') !== $companyCurrency) {
                    ExchangeRateLog::addExchangeRateLog($expense);
                }
            }

            if ($data->is_attachment_receipt_removed) {
                $expense->clearMediaCollection('receipts');
            }

            if ($request && $request->hasFile('attachment_receipt')) {
                $expense->clearMediaCollection('receipts');
                $expense->addMediaFromRequest('attachment_receipt')->toMediaCollection('receipts');
            }

            if ($data->customFields) {
                $customFields = is_string($data->customFields) ? json_decode($data->customFields) : $data->customFields;
                $expense->updateCustomFields($customFields);
            }

            return $expense;
        });
    }
}
