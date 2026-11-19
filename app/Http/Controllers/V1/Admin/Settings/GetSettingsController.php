<?php

namespace Crater\Http\Controllers\V1\Admin\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\GetSettingRequest;
use Crater\Models\Setting;
use Illuminate\Http\JsonResponse;

class GetSettingsController extends Controller
{
    public function __invoke(GetSettingRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        $setting = Setting::getSetting($request->key);

        return response()->json([
            $request->key => $setting
        ]);
    }
}
