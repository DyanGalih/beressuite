<?php

namespace App\Actions\Estimate;

use App\Actions\Action;
use App\Data\Estimate\EstimateData;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\ExchangeRateLog;
use App\Services\SerialNumberFormatter;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class CreateEstimate implements Action
{
    public function __invoke(EstimateData $data, int|string|null $companyId = null): Estimate
    {
        return DB::transaction(function () use ($data, $companyId): Estimate {
            $payload = $data->rawPayload;

            if ($companyId === null) {
                $companyId = $payload['company_id'] ?? null;
            }

            if ($data->estimateSend) {
                $payload['status'] = Estimate::STATUS_SENT;
            }

            $serial = (new SerialNumberFormatter())
                ->setModel(new Estimate())
                ->setCompany($companyId)
                ->setCustomer($data->customer_id ?? $payload['customer_id'] ?? null)
                ->setNextNumbers();

            $payload['estimate_number'] = $serial->getNextNumber();
            $payload['sequence_number'] = $serial->nextSequenceNumber;
            $payload['customer_sequence_number'] = $serial->nextCustomerSequenceNumber;

            $estimate = Estimate::create($payload);
            $estimate->unique_hash = Hashids::connection(Estimate::class)->encode($estimate->id);
            $estimate->save();

            if ($companyId !== null) {
                $companyCurrency = CompanySetting::getSetting('currency', $companyId);

                if ((string) $payload['currency_id'] !== $companyCurrency) {
                    ExchangeRateLog::addExchangeRateLog($estimate);
                }
            }

            Estimate::createItems($estimate, $data->items, $estimate->exchange_rate, (string) $companyId);

            if (! empty($data->taxes)) {
                Estimate::createTaxes($estimate, $data->taxes, $estimate->exchange_rate, (string) $companyId);
            }

            if (! empty($data->customFields)) {
                $estimate->addCustomFields($data->customFields);
            }

            return $estimate->load([
                'items',
                'items.taxes',
                'items.fields',
                'items.fields.customField',
                'customer',
                'taxes',
            ]);
        });
    }
}
