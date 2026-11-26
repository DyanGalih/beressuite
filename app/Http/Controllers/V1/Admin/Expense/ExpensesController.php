<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Actions\Expense\CreateExpense;
use App\Actions\Expense\DeleteExpenses;
use App\Actions\Expense\UpdateExpense;
use App\Data\Expense\ExpenseData;
use App\Data\Expense\ExpenseTableData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteExpensesRequest;
use App\Http\Requests\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpensesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $expenses = Expense::whereCompany()
            ->with(['category', 'customer.currency', 'currency', 'media'])
            ->leftJoin('customers', 'customers.id', '=', 'expenses.customer_id')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->applyFilters($request->all())
            ->select([
                'expenses.id',
                'expenses.company_id',
                'expenses.customer_id',
                'expenses.expense_category_id',
                'expenses.currency_id',
                'expenses.expense_date',
                'expenses.amount',
                'expenses.notes',
                'expenses.created_at',
            ])
            ->latest('expenses.created_at')
            ->paginateData($limit);

        $expenses = $expenses instanceof LengthAwarePaginator
            ? $expenses->through(fn (Expense $expense) => ExpenseTableData::fromModel($expense))
            : $expenses->map(fn (Expense $expense) => ExpenseTableData::fromModel($expense));

        return (JsonResource::collection($expenses))
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

        $data = ExpenseData::fromRequest($request);
        $expense = (new CreateExpense())($data, $request, $request->header('company'));

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

        $data = ExpenseData::fromRequest($request);
        (new UpdateExpense())($expense, $data, $request, $request->header('company'));

        return new ExpenseResource($expense);
    }

    /**
     * Remove the specified resources from storage.
     */
    public function delete(DeleteExpensesRequest $request): JsonResponse
    {
        $this->authorize('delete multiple expenses');

        (new DeleteExpenses())($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
