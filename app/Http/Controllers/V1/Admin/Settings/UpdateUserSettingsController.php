<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;

class UpdateUserSettingsController extends Controller
{
    public function __invoke(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->setSettings($request->settings);

        return response()->json([
            'success' => true,
        ]);
    }
}
