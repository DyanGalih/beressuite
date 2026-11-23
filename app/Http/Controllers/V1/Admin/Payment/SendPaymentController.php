<?php

namespace App\Http\Controllers\V1\Admin\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendPaymentRequest;
use App\Models\Payment;
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
