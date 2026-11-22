<?php

namespace App\Http\Controllers\V1\Customer\Expense;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\ExpenseResource;
use App\Models\Company;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class ExpensesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $expenses = Expense::with('category', 'creator', 'fields')
            ->whereUser(Auth::guard('customer')->id())
            ->applyFilters($request->only([
                'expense_category_id',
                'from_date',
                'to_date',
                'orderByField',
                'orderBy',
            ]))
            ->paginateData($limit);

        return (ExpenseResource::collection($expenses))
            ->additional(['meta' => [
                'expenseTotalCount' => Expense::whereCustomer(Auth::guard('customer')->id())->count(),
            ]]);
    }

    public function show(Company $company, int|string $id): ExpenseResource|JsonResponse
    {
        $expense = $company->expenses()
            ->whereUser(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $expense) {
            return response()->json(['error' => 'expense_not_found'], 404);
        }

        return new ExpenseResource($expense);
    }
}
