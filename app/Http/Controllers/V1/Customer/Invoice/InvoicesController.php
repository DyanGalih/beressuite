<?php

namespace App\Http\Controllers\V1\Customer\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\InvoiceResource;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class InvoicesController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $invoices = Invoice::with(['items', 'customer', 'creator', 'taxes'])
            ->where('status', '<>', 'DRAFT')
            ->applyFilters($request->all())
            ->whereCustomer(Auth::guard('customer')->id())
            ->latest()
            ->paginateData($limit);

        return (InvoiceResource::collection($invoices))
            ->additional(['meta' => [
                'invoiceTotalCount' => Invoice::where('status', '<>', 'DRAFT')->whereCustomer(Auth::guard('customer')->id())->count(),
            ]]);
    }

    public function show(Company $company, int|string $id): InvoiceResource|JsonResponse
    {
        $invoice = $company->invoices()
            ->whereCustomer(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $invoice) {
            return response()->json(['error' => 'invoice_not_found'], 404);
        }

        return new InvoiceResource($invoice);
    }
}
