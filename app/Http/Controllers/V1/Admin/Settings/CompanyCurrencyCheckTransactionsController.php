<?php

namespace Crater\Http\Controllers\V1\Admin\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyCurrencyCheckTransactionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $company = Company::find($request->header('company'));

        $this->authorize('manage company', $company);

        return response()->json([
            'has_transactions' => $company->hasTransactions(),
        ]);
    }
}
