<?php

namespace App\Http\Controllers\V1\PDF;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class EstimatePdfController extends Controller
{
    public function __invoke(Request $request, Estimate $estimate): Response|View
    {
        $user = Auth::guard('sanctum')->user() ?: Auth::guard('web')->user();
        abort_unless($user && $user->hasCompany($estimate->company_id), 403, 'Unauthorized company access.');

        if ($request->has('preview')) {
            return $estimate->getPDFData();
        }


        return $estimate->getGeneratedPDFOrStream('estimate');
    }
}
