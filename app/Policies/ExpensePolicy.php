<?php

namespace Crater\Policies;

use Crater\Models\Expense;
use Crater\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class ExpensePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return (bool) BouncerFacade::can('view-expense', Expense::class);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Expense $expense): bool
    {
        return BouncerFacade::can('view-expense', $expense) && $user->hasCompany($expense->company_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (bool) BouncerFacade::can('create-expense', Expense::class);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Expense $expense): bool
    {
        return BouncerFacade::can('edit-expense', $expense) && $user->hasCompany($expense->company_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return BouncerFacade::can('delete-expense', $expense) && $user->hasCompany($expense->company_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Expense $expense): bool
    {
        return BouncerFacade::can('delete-expense', $expense) && $user->hasCompany($expense->company_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return BouncerFacade::can('delete-expense', $expense) && $user->hasCompany($expense->company_id);
    }

    /**
     * Determine whether the user can delete models.
     */
    public function deleteMultiple(User $user): bool
    {
        return (bool) BouncerFacade::can('delete-expense', Expense::class);
    }
}
