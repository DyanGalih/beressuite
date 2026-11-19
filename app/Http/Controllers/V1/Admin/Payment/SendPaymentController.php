<?php

namespace Crater\Http\Controllers\V1\Admin\Payment;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\SendPaymentRequest;
use Crater\Models\Payment;
use Illuminate\Http\JsonResponse;

class SendPaymentController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SendPaymentRequest $request, Payment $payment): JsonResponse
    {
        $this->authorize('send payment', $payment);

        $response = $payment->send($request->all());

        return response()->json($response);
    }
}
