<?php

namespace Crater\Http\Controllers\V1\Admin\Item;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\UnitRequest;
use Crater\Http\Resources\UnitResource;
use Crater\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Unit::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $units = Unit::applyFilters($request->all())
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return UnitResource::collection($units);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UnitRequest $request): UnitResource
    {
        $this->authorize('create', Unit::class);

        $unit = Unit::create($request->getUnitPayload());

        return new UnitResource($unit);
    }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit): UnitResource
    {
        $this->authorize('view', $unit);

        return new UnitResource($unit);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UnitRequest $request, Unit $unit): UnitResource
    {
        $this->authorize('update', $unit);

        $unit->update($request->getUnitPayload());

        return new UnitResource($unit);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit): JsonResponse
    {
        $this->authorize('delete', $unit);

        if ($unit->items()->exists()) {
            return respondJson('items_attached', 'Items Attached');
        }

        $unit->delete();

        return response()->json([
            'success' => 'Unit deleted successfully',
        ]);
    }
}
