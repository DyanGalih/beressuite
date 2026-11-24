<?php

namespace App\Data\Company;

use App\Data\Data;

class CompanySettingData extends Data
{
    public function __construct(
        public int $company_id,
        public string $option,
        public ?string $value = null,
    ) {
    }
}
