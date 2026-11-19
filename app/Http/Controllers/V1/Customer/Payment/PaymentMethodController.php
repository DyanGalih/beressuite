<?php

namespace Crater\Http\Controllers\V1\Customer\Payment;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\Customer\PaymentMethodResource;
use Crater\Models\Company;
use Crater\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Company $company): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(PaymentMethod::where('company_id', $company->id)->get());
    }
}
