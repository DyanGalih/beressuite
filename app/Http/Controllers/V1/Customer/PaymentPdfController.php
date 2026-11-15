<?php

namespace Crater\Http\Controllers\V1\Customer;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\PaymentResource;
use Crater\Models\EmailLog;
use Crater\Models\Payment;
use Illuminate\Http\Request;

class PaymentPdfController extends Controller
{
    public function getPdf(EmailLog $emailLog, Request $request)
    {
        $payment = $emailLog->validMailable(Payment::class);
        abort_unless($payment, 403, 'Invalid or expired document link.');

        return $payment->getGeneratedPDFOrStream('payment');
    }

    public function getPayment(EmailLog $emailLog)
    {
        $payment = $emailLog->validMailable(Payment::class);
        abort_unless($payment, 403, 'Invalid or expired document link.');

        return new PaymentResource($payment);
    }
}
