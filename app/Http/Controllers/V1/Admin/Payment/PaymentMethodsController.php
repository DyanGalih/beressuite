<?php

namespace App\Http\Controllers\V1\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $paymentMethods = PaymentMethod::applyFilters($request->all())
            ->where('type', PaymentMethod::TYPE_GENERAL)
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return PaymentMethodResource::collection($paymentMethods);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PaymentMethodRequest $request): PaymentMethodResource
    {
        $this->authorize('create', PaymentMethod::class);

        $paymentMethod = PaymentMethod::createPaymentMethod($request);

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentMethod $paymentMethod): PaymentMethodResource
    {
        $this->authorize('view', $paymentMethod);

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): PaymentMethodResource
    {
        $this->authorize('update', $paymentMethod);

        $paymentMethod->update($request->getPaymentMethodPayload());

        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('delete', $paymentMethod);

        if ($paymentMethod->payments()->exists()) {
            return respondJson('payments_attached', 'Payments Attached.');
        }

        if ($paymentMethod->expenses()->exists()) {
            return respondJson('expenses_attached', 'Expenses Attached.');
        }

        $paymentMethod->delete();

        return response()->json([
            'success' => 'Payment method deleted successfully',
        ]);
    }
}
