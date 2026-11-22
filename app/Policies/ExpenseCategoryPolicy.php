<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Silber\Bouncer\BouncerFacade;

class ExpenseCategoryPolicy
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
    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        return BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (bool) BouncerFacade::can('view-expense', Expense::class);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExpenseCategory $expenseCategory): bool
    {
        return BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExpenseCategory $expenseCategory): bool
    {
        return BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return BouncerFacade::can('view-expense', Expense::class) && $user->hasCompany($expenseCategory->company_id);
    }
}
