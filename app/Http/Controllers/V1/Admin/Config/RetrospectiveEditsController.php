<?php

namespace Crater\Http\Controllers\V1\Admin\Config;

use Crater\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetrospectiveEditsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'retrospective_edits' => config('crater.retrospective_edits'),
        ]);
    }
}
