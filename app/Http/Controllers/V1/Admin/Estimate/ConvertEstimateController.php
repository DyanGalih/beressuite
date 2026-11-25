<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Actions\Estimate\ConvertEstimateToInvoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Estimate;
use App\Models\Invoice;
use Illuminate\Http\Request;

class ConvertEstimateController extends Controller
{
    public function __invoke(Request $request, Estimate $estimate, Invoice $invoice): InvoiceResource
    {
        $this->authorize('create', Invoice::class);

        $invoice = (new ConvertEstimateToInvoice())($estimate, $request->header('company'));

        return new InvoiceResource($invoice);
    }
}
