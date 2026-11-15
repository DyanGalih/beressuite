<?php

namespace Crater\Http\Controllers\V1\PDF;

use Crater\Http\Controllers\Controller;
use Crater\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EstimatePdfController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Estimate $estimate)
    {
        $user = Auth::guard('sanctum')->user() ?: Auth::guard('web')->user();
        abort_unless($user && $user->hasCompany($estimate->company_id), 403, 'Unauthorized company access.');

        if ($request->has('preview')) {
            return $estimate->getPDFData();
        }


        return $estimate->getGeneratedPDFOrStream('estimate');
    }
}
