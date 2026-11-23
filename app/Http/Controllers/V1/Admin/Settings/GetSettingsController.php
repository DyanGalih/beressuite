<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingRequest;
use App\Models\Setting;
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
