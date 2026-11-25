<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Actions\Estimate\CreateEstimate;
use App\Actions\Estimate\DeleteEstimates;
use App\Actions\Estimate\UpdateEstimate;
use App\Data\Estimate\EstimateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEstimatesRequest;
use App\Http\Requests\EstimatesRequest;
use App\Http\Resources\EstimateResource;
use App\Jobs\GenerateEstimatePdfJob;
use App\Models\Estimate;
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

        $data = EstimateData::fromRequest($request);
        $estimate = (new CreateEstimate())($data, $request->header('company'));

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

        $data = EstimateData::fromRequest($request);
        $estimate = (new UpdateEstimate())($estimate, $data, $request->header('company'));

        GenerateEstimatePdfJob::dispatch($estimate, true);

        return new EstimateResource($estimate);
    }

    public function delete(DeleteEstimatesRequest $request): JsonResponse
    {
        $this->authorize('delete multiple estimates');

        (new DeleteEstimates())($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
