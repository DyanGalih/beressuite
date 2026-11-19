<?php

namespace Crater\Http\Controllers\V1\Admin\RecurringInvoice;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\RecurringInvoiceRequest;
use Crater\Http\Resources\RecurringInvoiceResource;
use Crater\Models\RecurringInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecurringInvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RecurringInvoice::class);

        $limit = $request->input('limit', 10);

        $recurringInvoices = RecurringInvoice::whereCompany()
            ->applyFilters($request->all())
            ->paginateData($limit);

        return (RecurringInvoiceResource::collection($recurringInvoices))
            ->additional(['meta' => [
                'recurring_invoice_total_count' => RecurringInvoice::whereCompany()->count(),
            ]]);
    }

    public function store(RecurringInvoiceRequest $request): RecurringInvoiceResource
    {
        $this->authorize('create', RecurringInvoice::class);

        $recurringInvoice = RecurringInvoice::createFromRequest($request);

        return new RecurringInvoiceResource($recurringInvoice);
    }

    public function show(RecurringInvoice $recurringInvoice): RecurringInvoiceResource
    {
        $this->authorize('view', $recurringInvoice);

        return new RecurringInvoiceResource($recurringInvoice);
    }

    public function update(RecurringInvoiceRequest $request, RecurringInvoice $recurringInvoice): RecurringInvoiceResource
    {
        $this->authorize('update', $recurringInvoice);

        $recurringInvoice->updateFromRequest($request);

        return new RecurringInvoiceResource($recurringInvoice);
    }

    public function delete(Request $request): JsonResponse
    {
        $this->authorize('delete multiple recurring invoices');

        RecurringInvoice::deleteRecurringInvoice($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
