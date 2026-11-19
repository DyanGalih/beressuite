<?php

namespace Crater\Policies;

use Crater\Models\Estimate;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class EstimatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return (bool) BouncerFacade::can('view-estimate', Estimate::class);
    }

    public function view(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('view-estimate', $estimate) && $user->hasCompany($estimate->company_id);
    }

    public function create(User $user): bool
    {
        return (bool) BouncerFacade::can('create-estimate', Estimate::class);
    }

    public function update(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('edit-estimate', $estimate) && $user->hasCompany($estimate->company_id);
    }

    public function delete(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('delete-estimate', $estimate) && $user->hasCompany($estimate->company_id);
    }

    public function restore(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('delete-estimate', $estimate) && $user->hasCompany($estimate->company_id);
    }

    public function forceDelete(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('delete-estimate', $estimate) && $user->hasCompany($estimate->company_id);
    }

    public function send(User $user, Estimate $estimate): bool
    {
        return BouncerFacade::can('send-estimate', $estimate) && $user->hasCompany($estimate->company_id);
    }

    public function deleteMultiple(User $user): bool
    {
        return (bool) BouncerFacade::can('delete-estimate', Estimate::class);
    }
}
