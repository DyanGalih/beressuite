<?php

namespace App\Actions\Company;

use App\Actions\Action;
use App\Data\Company\CompanySettingData;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;

class UpdateCompanySetting implements Action
{
    public function __invoke(CompanySettingData $data): CompanySetting
    {
        return DB::transaction(function () use ($data): CompanySetting {
            return CompanySetting::updateOrCreate(
                [
                    'company_id' => $data->company_id,
                    'option' => $data->option,
                ],
                [
                    'value' => $data->value,
                ]
            );
        });
    }
}
