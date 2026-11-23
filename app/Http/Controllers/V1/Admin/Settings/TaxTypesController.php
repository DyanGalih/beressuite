<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaxTypeRequest;
use App\Http\Resources\TaxTypeResource;
use App\Models\TaxType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxTypesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TaxType::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $taxTypes = TaxType::applyFilters($request->all())
            ->where('type', TaxType::TYPE_GENERAL)
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return TaxTypeResource::collection($taxTypes);
    }

    public function store(TaxTypeRequest $request): TaxTypeResource
    {
        $this->authorize('create', TaxType::class);

        $taxType = TaxType::create($request->getTaxTypePayload());

        return new TaxTypeResource($taxType);
    }

    public function show(TaxType $taxType): TaxTypeResource
    {
        $this->authorize('view', $taxType);

        return new TaxTypeResource($taxType);
    }

    public function update(TaxTypeRequest $request, TaxType $taxType): TaxTypeResource
    {
        $this->authorize('update', $taxType);

        $taxType->update($request->getTaxTypePayload());

        return new TaxTypeResource($taxType);
    }

    public function destroy(TaxType $taxType): JsonResponse
    {
        $this->authorize('delete', $taxType);

        if ($taxType->taxes() && $taxType->taxes()->count() > 0) {
            return respondJson('taxes_attached', 'Taxes Attached.');
        }

        $taxType->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
