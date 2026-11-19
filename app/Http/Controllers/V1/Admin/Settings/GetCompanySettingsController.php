<?php

namespace Crater\Http\Controllers\V1\Admin\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\GetSettingsRequest;
use Crater\Models\CompanySetting;
use Illuminate\Http\JsonResponse;

class GetCompanySettingsController extends Controller
{
    public function __invoke(GetSettingsRequest $request): JsonResponse
    {
        $settings = CompanySetting::getSettings($request->settings, $request->header('company'));

        return response()->json($settings);
    }
}
