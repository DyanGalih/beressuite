<?php

namespace Crater\Http\Controllers\V1\Admin\Expense;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\DeleteExpensesRequest;
use Crater\Http\Requests\ExpenseRequest;
use Crater\Http\Resources\ExpenseResource;
use Crater\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpensesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $expenses = Expense::with('category', 'creator', 'fields')
            ->whereCompany()
            ->leftJoin('customers', 'customers.id', '=', 'expenses.customer_id')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->applyFilters($request->all())
            ->select('expenses.*', 'expense_categories.name', 'customers.name as user_name')
            ->paginateData($limit);

        return (ExpenseResource::collection($expenses))
            ->additional(['meta' => [
                'expense_total_count' => Expense::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseRequest $request): ExpenseResource
    {
        $this->authorize('create', Expense::class);

        $expense = Expense::createExpense($request);

        return new ExpenseResource($expense);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);

        return new ExpenseResource($expense);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $this->authorize('update', $expense);

        $expense->updateExpense($request);

        return new ExpenseResource($expense);
    }

    /**
     * Remove the specified resources from storage.
     */
    public function delete(DeleteExpensesRequest $request): JsonResponse
    {
        $this->authorize('delete multiple expenses');

        Expense::destroy($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
