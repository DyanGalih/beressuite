<?php

namespace App\Http\Controllers\V1\Admin\Expense;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategoryRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseCategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $categories = ExpenseCategory::applyFilters($request->all())
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return ExpenseCategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseCategoryRequest $request): ExpenseCategoryResource
    {
        $this->authorize('create', ExpenseCategory::class);

        $category = ExpenseCategory::create($request->getExpenseCategoryPayload());

        return new ExpenseCategoryResource($category);
    }

    /**
     * Display the specified resource.
     */
    public function show(ExpenseCategory $category): ExpenseCategoryResource
    {
        $this->authorize('view', $category);

        return new ExpenseCategoryResource($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ExpenseCategoryRequest $request, ExpenseCategory $category): ExpenseCategoryResource
    {
        $this->authorize('update', $category);

        $category->update($request->getExpenseCategoryPayload());

        return new ExpenseCategoryResource($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ExpenseCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);

        if ($category->expenses() && $category->expenses()->count() > 0) {
            return respondJson('expense_attached', 'Expense Attached');
        }

        $category->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
