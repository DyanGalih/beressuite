<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceTemplatesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $invoiceTemplates = Invoice::invoiceTemplates();

        return response()->json([
            'invoiceTemplates' => $invoiceTemplates,
        ]);
    }
}
