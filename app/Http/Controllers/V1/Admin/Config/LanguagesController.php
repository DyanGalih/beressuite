<?php

namespace App\Http\Controllers\V1\Admin\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguagesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'languages' => config('crater.languages'),
        ]);
    }
}
