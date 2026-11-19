<?php

namespace Crater\Policies;

use Crater\Models\Payment;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class PaymentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return (bool) BouncerFacade::can('view-payment', Payment::class);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('view-payment', $payment) && $user->hasCompany($payment->company_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (bool) BouncerFacade::can('create-payment', Payment::class);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('edit-payment', $payment) && $user->hasCompany($payment->company_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('delete-payment', $payment) && $user->hasCompany($payment->company_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('delete-payment', $payment) && $user->hasCompany($payment->company_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('delete-payment', $payment) && $user->hasCompany($payment->company_id);
    }

    /**
     * Determine whether the user can send email of the model.
     */
    public function send(User $user, Payment $payment): bool
    {
        return BouncerFacade::can('send-payment', $payment) && $user->hasCompany($payment->company_id);
    }

    /**
     * Determine whether the user can delete models.
     */
    public function deleteMultiple(User $user): bool
    {
        return (bool) BouncerFacade::can('delete-payment', Payment::class);
    }
}
