<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class SendInvoiceController extends Controller
{
    public function __invoke(SendInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('send invoice', $invoice);

        $invoice->send($request->all());

        return response()->json([
            'success' => true,
        ]);
    }
}
