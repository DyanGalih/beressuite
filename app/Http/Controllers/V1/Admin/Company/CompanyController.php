<?php

namespace App\Http\Controllers\V1\Admin\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __invoke(Request $request): CompanyResource
    {
        $company = Company::find($request->header('company'));

        return new CompanyResource($company);
    }
}
