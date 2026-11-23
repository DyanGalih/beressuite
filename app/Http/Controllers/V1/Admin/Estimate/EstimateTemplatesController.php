<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstimateTemplatesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Estimate::class);

        $estimateTemplates = Estimate::estimateTemplates();

        return response()->json([
            'estimateTemplates' => $estimateTemplates
        ]);
    }
}
