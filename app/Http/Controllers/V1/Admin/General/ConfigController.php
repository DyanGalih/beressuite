<?php

namespace Crater\Http\Controllers\V1\Admin\General;

use Crater\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            $request->key => config('crater.'.$request->key),
        ]);
    }
}
