<?php

namespace Crater\Http\Controllers\V1\PDF;

use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoicePdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Invoice $invoice)
    {
        $user = Auth::guard('sanctum')->user() ?: Auth::guard('web')->user();
        abort_unless($user && $user->hasCompany($invoice->company_id), 403, 'Unauthorized company access.');

        if ($request->has('preview')) {
            return $invoice->getPDFData();
        }

        return $invoice->getGeneratedPDFOrStream('invoice');
    }
}
