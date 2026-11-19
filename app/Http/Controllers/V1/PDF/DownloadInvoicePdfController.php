<?php

namespace Crater\Http\Controllers\V1\PDF;

use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadInvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): BinaryFileResponse
    {
        $path = storage_path('app/temp/invoice/'.$invoice->id.'.pdf');

        return response()->download($path);
    }
}
