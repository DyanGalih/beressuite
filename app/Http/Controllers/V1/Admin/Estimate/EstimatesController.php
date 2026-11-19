<?php

namespace Crater\Http\Controllers\V1\Admin\Estimate;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\DeleteEstimatesRequest;
use Crater\Http\Requests\EstimatesRequest;
use Crater\Http\Resources\EstimateResource;
use Crater\Jobs\GenerateEstimatePdfJob;
use Crater\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EstimatesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Estimate::class);

        $limit = $request->input('limit', 10);

        $estimates = Estimate::whereCompany()
            ->join('customers', 'customers.id', '=', 'estimates.customer_id')
            ->applyFilters($request->all())
            ->select('estimates.*', 'customers.name')
            ->latest()
            ->paginateData($limit);

        return (EstimateResource::collection($estimates))
            ->additional(['meta' => [
                'estimate_total_count' => Estimate::whereCompany()->count(),
            ]]);
    }

    public function store(EstimatesRequest $request): EstimateResource
    {
        $this->authorize('create', Estimate::class);

        $estimate = Estimate::createEstimate($request);

        if ($request->has('estimateSend')) {
            $estimate->send($request->title, $request->body);
        }

        GenerateEstimatePdfJob::dispatch($estimate);

        return new EstimateResource($estimate);
    }

    public function show(Request $request, Estimate $estimate): EstimateResource
    {
        $this->authorize('view', $estimate);

        return new EstimateResource($estimate);
    }

    public function update(EstimatesRequest $request, Estimate $estimate): EstimateResource
    {
        $this->authorize('update', $estimate);

        $estimate = $estimate->updateEstimate($request);

        GenerateEstimatePdfJob::dispatch($estimate, true);

        return new EstimateResource($estimate);
    }

    public function delete(DeleteEstimatesRequest $request): JsonResponse
    {
        $this->authorize('delete multiple estimates');

        Estimate::destroy($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
