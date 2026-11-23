<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingsRequest;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;

class GetCompanySettingsController extends Controller
{
    public function __invoke(GetSettingsRequest $request): JsonResponse
    {
        $settings = CompanySetting::getSettings($request->settings, $request->header('company'));

        return response()->json($settings);
    }
}
