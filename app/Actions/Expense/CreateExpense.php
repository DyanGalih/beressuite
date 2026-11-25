<?php

namespace App\Actions\Expense;

use App\Actions\Action;
use App\Data\Expense\ExpenseData;
use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateExpense implements Action
{
    public function __invoke(ExpenseData $data, ?Request $request = null, int|string|null $companyId = null): Expense
    {
        return DB::transaction(function () use ($data, $request, $companyId): Expense {
            $payload = $data->rawPayload;

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? null;
            }

            $expense = Expense::create($payload);

            if ($companyId !== null) {
                $companyCurrency = CompanySetting::getSetting('currency', $companyId);

                if ((string) ($payload['currency_id'] ?? '') !== $companyCurrency) {
                    ExchangeRateLog::addExchangeRateLog($expense);
                }
            }

            if ($request && $request->hasFile('attachment_receipt')) {
                $expense->addMediaFromRequest('attachment_receipt')->toMediaCollection('receipts');
            }

            if ($data->customFields) {
                $customFields = is_string($data->customFields) ? json_decode($data->customFields) : $data->customFields;
                $expense->addCustomFields($customFields);
            }

            return $expense;
        });
    }
}
