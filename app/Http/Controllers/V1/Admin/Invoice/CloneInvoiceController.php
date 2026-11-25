<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Actions\Invoice\CloneInvoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;

class CloneInvoiceController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, CloneInvoice $cloneInvoice): InvoiceResource
    {
        $this->authorize('create', Invoice::class);

        $newInvoice = $cloneInvoice($invoice, $request->header('company'));

        return new InvoiceResource($newInvoice);
    }
}
