<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Actions\Invoice\CreateInvoice;
use App\Actions\Invoice\DeleteInvoices;
use App\Actions\Invoice\UpdateInvoice;
use App\Data\Invoice\InvoiceData;
use App\Data\Invoice\InvoiceTableData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteInvoiceRequest;
use App\Http\Requests\InvoicesRequest;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $limit = $request->input('limit', 10);

        $invoices = Invoice::whereCompany()
            ->with(['customer.currency', 'currency'])
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->applyFilters($request->all())
            ->select([
                'invoices.id',
                'invoices.company_id',
                'invoices.customer_id',
                'invoices.currency_id',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.due_date',
                'invoices.status',
                'invoices.paid_status',
                'invoices.total',
                'invoices.due_amount',
                'invoices.overdue',
                'invoices.unique_hash',
                'invoices.created_at',
            ])
            ->latest('invoices.created_at')
            ->paginateData($limit);

        $invoices = $invoices instanceof LengthAwarePaginator
            ? $invoices->through(fn (Invoice $invoice) => InvoiceTableData::fromModel($invoice))
            : $invoices->map(fn (Invoice $invoice) => InvoiceTableData::fromModel($invoice));

        return (JsonResource::collection($invoices))
            ->additional(['meta' => [
                'invoice_total_count' => Invoice::whereCompany()->count(),
            ]]);
    }

    public function store(InvoicesRequest $request, CreateInvoice $createInvoice): InvoiceResource
    {
        $this->authorize('create', Invoice::class);

        $data = InvoiceData::fromRequest($request);
        $invoice = $createInvoice($data, $request->header('company'));

        if ($data->invoiceSend) {
            $invoice->send($data->subject, $data->body);
        }

        GenerateInvoicePdfJob::dispatch($invoice);

        return new InvoiceResource($invoice);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice);
    }

    public function update(InvoicesRequest $request, Invoice $invoice, UpdateInvoice $updateInvoice): InvoiceResource|JsonResponse
    {
        $this->authorize('update', $invoice);

        $data = InvoiceData::fromRequest($request);
        $result = $updateInvoice($invoice, $data, $request->header('company'));

        if (is_string($result)) {
            return respondJson($result, $result);
        }

        GenerateInvoicePdfJob::dispatch($result, true);

        return new InvoiceResource($result);
    }

    public function delete(DeleteInvoiceRequest $request, DeleteInvoices $deleteInvoices): JsonResponse
    {
        $this->authorize('delete multiple invoices');

        $deleteInvoices($request->ids, $request->header('company'));

        return response()->json([
            'success' => true,
        ]);
    }
}
