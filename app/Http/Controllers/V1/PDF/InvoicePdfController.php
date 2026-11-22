<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InvoicePdfController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice): Response|View
    {
        $user = Auth::guard('sanctum')->user() ?: Auth::guard('web')->user();
        abort_unless($user && $user->hasCompany($invoice->company_id), 403, 'Unauthorized company access.');

        if ($request->has('preview')) {
            return $invoice->getPDFData();
        }

        return $invoice->getGeneratedPDFOrStream('invoice');
    }
}
