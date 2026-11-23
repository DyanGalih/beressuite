<?php

namespace App\Http\Controllers\V1\Admin\Role;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbilitiesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['abilities' => config('abilities.abilities')]);
    }
}
