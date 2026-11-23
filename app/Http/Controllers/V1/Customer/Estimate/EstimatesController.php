<?php

namespace App\Http\Controllers\V1\Customer\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\EstimateResource;
use App\Models\Company;
use App\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class EstimatesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = $request->input('limit', 10);

        $estimates = Estimate::with([
                'items',
                'customer',
                'taxes',
                'creator',
            ])
            ->where('status', '<>', 'DRAFT')
            ->whereCustomer(Auth::guard('customer')->id())
            ->applyFilters($request->only([
                'status',
                'estimate_number',
                'from_date',
                'to_date',
                'orderByField',
                'orderBy',
            ]))
            ->latest()
            ->paginateData($limit);

        return (EstimateResource::collection($estimates))
            ->additional(['meta' => [
                'estimateTotalCount' => Estimate::where('status', '<>', 'DRAFT')->whereCustomer(Auth::guard('customer')->id())->count(),
            ]]);
    }

    public function show(Company $company, int|string $id): EstimateResource|JsonResponse
    {
        $estimate = $company->estimates()
            ->whereCustomer(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $estimate) {
            return response()->json(['error' => 'estimate_not_found'], 404);
        }

        return new EstimateResource($estimate);
    }
}
