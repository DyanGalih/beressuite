<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PaymentPdfController extends Controller
{
    public function __invoke(Request $request, Payment $payment): Response|View
    {
        $user = Auth::guard('sanctum')->user() ?: Auth::guard('web')->user();
        abort_unless($user && $user->hasCompany($payment->company_id), 403, 'Unauthorized company access.');

        if ($request->has('preview')) {
            return view('app.pdf.payment.payment');
        }

        return $payment->getGeneratedPDFOrStream('payment');
    }
}
