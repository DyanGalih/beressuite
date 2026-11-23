<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetCompanyMailConfigurationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $mailConfig = [
            'from_name' => config('mail.from.name'),
            'from_mail' => config('mail.from.address'),
        ];

        return response()->json($mailConfig);
    }
}
