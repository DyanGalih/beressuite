<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class ReportPolicy
{
    use HandlesAuthorization;

    public function viewReport(User $user, Company $company)
    {
        if (! $user->hasCompany($company->id)) {
            return false;
        }

        BouncerFacade::scope()->to($company->id);

        return (bool) BouncerFacade::can('view-financial-reports');
    }
}
