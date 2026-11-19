<?php

namespace Crater\Http\Controllers\V1\Customer\Payment;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\Customer\PaymentResource;
use Crater\Models\Company;
use Crater\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PaymentsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $payments = Payment::with(['customer', 'invoice', 'paymentMethod', 'creator'])
            ->whereCustomer(Auth::guard('customer')->id())
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->applyFilters($request->only([
                'payment_number',
                'payment_method_id',
                'orderByField',
                'orderBy',
            ]))
            ->select('payments.*', 'invoices.invoice_number')
            ->latest()
            ->paginateData($limit);

        return (PaymentResource::collection($payments))
            ->additional(['meta' => [
                'paymentTotalCount' => Payment::whereCustomer(Auth::guard('customer')->id())->count(),
            ]]);
    }

    public function show(Company $company, int|string $id): PaymentResource|JsonResponse
    {
        $payment = $company->payments()
            ->whereCustomer(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $payment) {
            return response()->json(['error' => 'payment_not_found'], 404);
        }

        return new PaymentResource($payment);
    }
}
