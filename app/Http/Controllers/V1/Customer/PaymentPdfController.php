<?php

namespace Crater\Http\Controllers\V1\Customer;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\PaymentResource;
use Crater\Models\EmailLog;
use Crater\Models\Payment;
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
