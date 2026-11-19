<?php

namespace Crater\Http\Controllers\V1\PDF;

use Crater\Http\Controllers\Controller;
use Crater\Models\Payment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadPaymentPdfController extends Controller
{
    public function __invoke(Payment $payment): BinaryFileResponse
    {
        $path = storage_path('app/temp/payment/'.$payment->id.'.pdf');

        return response()->download($path);
    }
}
