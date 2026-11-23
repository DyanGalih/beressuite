<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadPaymentPdfController extends Controller
{
    public function __invoke(Payment $payment): BinaryFileResponse
    {
        $path = storage_path('app/temp/payment/'.$payment->id.'.pdf');

        return response()->download($path);
    }
}
