<?php

namespace App\Actions\Estimate;

use App\Actions\Action;
use App\Data\Estimate\EstimateData;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\ExchangeRateLog;
use App\Services\SerialNumberFormatter;
use Illuminate\Support\Facades\DB;

class UpdateEstimate implements Action
{
    public function __invoke(Estimate $estimate, EstimateData $data, int|string|null $companyId = null): Estimate
    {
        return DB::transaction(function () use ($estimate, $data, $companyId): Estimate {
            $payload = $data->rawPayload;

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? $estimate->company_id;
            }

            $serial = (new SerialNumberFormatter())
                ->setModel($estimate)
                ->setCompany($estimate->company_id)
                ->setCustomer($data->customer_id)
                ->setModelObject($estimate->id)
                ->setNextNumbers();

            $payload['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

            $estimate->update($payload);

            if ($companyId !== null) {
                $companyCurrency = CompanySetting::getSetting('currency', $companyId);

                if ((string) $payload['currency_id'] !== $companyCurrency) {
                    ExchangeRateLog::addExchangeRateLog($estimate);
                }
            }

            foreach ($estimate->items as $item) {
                $item->fields()->delete();
            }

            $estimate->items()->delete();
            $estimate->taxes()->delete();

            Estimate::createItems($estimate, $data->items, $estimate->exchange_rate, (string) $companyId);

            if (! empty($data->taxes)) {
                Estimate::createTaxes($estimate, $data->taxes, $estimate->exchange_rate, (string) $companyId);
            }

            if (! empty($data->customFields)) {
                $estimate->updateCustomFields($data->customFields);
            }

            return Estimate::with([
                'items.taxes',
                'items.fields',
                'items.fields.customField',
                'customer',
                'taxes',
            ])->find($estimate->id);
        });
    }
}
