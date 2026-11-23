<?php

namespace App\Http\Controllers\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\EmailLog;
use App\Models\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentPdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request): Response
    {
        $payment = $emailLog->validMailable(Payment::class);
        abort_unless($payment, 403, 'Invalid or expired document link.');

        return $payment->getGeneratedPDFOrStream('payment');
    }

    public function getPayment(EmailLog $emailLog): PaymentResource
    {
        $payment = $emailLog->validMailable(Payment::class);
        abort_unless($payment, 403, 'Invalid or expired document link.');

        return new PaymentResource($payment);
    }
}
