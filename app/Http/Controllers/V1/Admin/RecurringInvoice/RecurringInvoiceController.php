<?php

namespace App\Http\Controllers\V1\Admin\RecurringInvoice;

use App\Data\RecurringInvoice\RecurringInvoiceTableData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteRecurringInvoicesRequest;
use App\Http\Requests\RecurringInvoiceRequest;
use App\Http\Resources\RecurringInvoiceResource;
use App\Models\RecurringInvoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringInvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RecurringInvoice::class);

        $limit = $request->input('limit', 10);

        $recurringInvoices = RecurringInvoice::whereCompany()
            ->with(['customer.currency'])
            ->applyFilters($request->all())
            ->select([
                'recurring_invoices.id',
                'recurring_invoices.company_id',
                'recurring_invoices.customer_id',
                'recurring_invoices.starts_at',
                'recurring_invoices.status',
                'recurring_invoices.frequency',
                'recurring_invoices.total',
                'recurring_invoices.created_at',
            ])
            ->latest('recurring_invoices.created_at')
            ->paginateData($limit);

        $recurringInvoices = $recurringInvoices instanceof LengthAwarePaginator
            ? $recurringInvoices->through(fn (RecurringInvoice $invoice) => RecurringInvoiceTableData::fromModel($invoice))
            : $recurringInvoices->map(fn (RecurringInvoice $invoice) => RecurringInvoiceTableData::fromModel($invoice));

        return (JsonResource::collection($recurringInvoices))
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

    public function delete(DeleteRecurringInvoicesRequest $request): JsonResponse
    {
        $this->authorize('delete multiple recurring invoices');

        RecurringInvoice::deleteRecurringInvoice($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
