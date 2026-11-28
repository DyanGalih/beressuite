<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Actions\Estimate\CreateEstimate;
use App\Actions\Estimate\DeleteEstimates;
use App\Actions\Estimate\UpdateEstimate;
use App\Data\Estimate\EstimateData;
use App\Data\Estimate\EstimateTableData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEstimatesRequest;
use App\Http\Requests\EstimatesRequest;
use App\Http\Resources\EstimateResource;
use App\Jobs\GenerateEstimatePdfJob;
use App\Models\Estimate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class EstimatesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Estimate::class);

        $limit = $request->input('limit', 10);

        $estimates = Estimate::whereCompany()
            ->with(['customer.currency', 'currency'])
            ->join('customers', 'customers.id', '=', 'estimates.customer_id')
            ->applyFilters($request->all())
            ->select([
                'estimates.id',
                'estimates.company_id',
                'estimates.customer_id',
                'estimates.currency_id',
                'estimates.estimate_number',
                'estimates.estimate_date',
                'estimates.expiry_date',
                'estimates.status',
                'estimates.total',
                'estimates.unique_hash',
                'estimates.created_at',
            ])
            ->latest('estimates.created_at')
            ->paginateData($limit);

        $estimates = $estimates instanceof LengthAwarePaginator
            ? $estimates->through(fn (Estimate $estimate) => EstimateTableData::fromModel($estimate))
            : $estimates->map(fn (Estimate $estimate) => EstimateTableData::fromModel($estimate));

        return (JsonResource::collection($estimates))
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

        $estimate->load([
            'taxes.taxType',
            'items.taxes.taxType',
            'items.fields',
            'customer.billingAddress',
            'customer.shippingAddress',
            'customer.currency',
            'customer.fields',
            'customer.company',
        ]);

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
