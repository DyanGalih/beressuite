<?php

namespace App\Http\Controllers\V1\Admin\Payment;

use App\Actions\Payment\CreatePayment;
use App\Actions\Payment\DeletePayments;
use App\Actions\Payment\UpdatePayment;
use App\Data\Payment\PaymentData;
use App\Data\Payment\PaymentTableData;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePaymentsRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $payments = Payment::whereCompany()
            ->with(['customer.currency', 'currency', 'paymentMethod', 'invoice'])
            ->join('customers', 'customers.id', '=', 'payments.customer_id')
            ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->applyFilters($request->all())
            ->select([
                'payments.id',
                'payments.company_id',
                'payments.customer_id',
                'payments.invoice_id',
                'payments.payment_method_id',
                'payments.currency_id',
                'payments.payment_number',
                'payments.payment_date',
                'payments.amount',
                'payments.unique_hash',
                'payments.created_at',
            ])
            ->latest('payments.created_at')
            ->paginateData($limit);

        $payments->through(fn (Payment $payment) => PaymentTableData::fromModel($payment));

        return (JsonResource::collection($payments))
            ->additional(['meta' => [
                'payment_total_count' => Payment::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PaymentRequest $request): PaymentResource
    {
        $this->authorize('create', Payment::class);

        $data = PaymentData::fromRequest($request);
        $payment = (new CreatePayment())($data, $request->header('company'));

        return new PaymentResource($payment);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment);

        return new PaymentResource($payment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PaymentRequest $request, Payment $payment): PaymentResource
    {
        $this->authorize('update', $payment);

        $data = PaymentData::fromRequest($request);
        $payment = (new UpdatePayment())($payment, $data, $request->header('company'));

        return new PaymentResource($payment);
    }

    /**
     * Remove the specified resources from storage.
     */
    public function delete(DeletePaymentsRequest $request): JsonResponse
    {
        $this->authorize('delete multiple payments');

        (new DeletePayments())($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
