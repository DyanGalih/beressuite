<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetSettingsRequest;
use Illuminate\Http\JsonResponse;

class GetUserSettingsController extends Controller
{
    public function __invoke(GetSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user->getSettings($request->settings));
    }
}
