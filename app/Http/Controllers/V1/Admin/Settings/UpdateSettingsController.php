<?php

namespace Crater\Http\Controllers\V1\Admin\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\SettingRequest;
use Crater\Models\Setting;
use Illuminate\Http\JsonResponse;

class UpdateSettingsController extends Controller
{
    public function __invoke(SettingRequest $request): JsonResponse
    {
        $this->authorize('manage settings');

        Setting::setSettings($request->settings);

        return response()->json([
            'success' => true,
            $request->settings
        ]);
    }
}
