<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadInvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): BinaryFileResponse
    {
        $path = storage_path('app/temp/invoice/'.$invoice->id.'.pdf');

        return response()->download($path);
    }
}
