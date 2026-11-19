<?php

namespace Crater\Http\Controllers\V1\Admin\Estimate;

use Crater\Http\Controllers\Controller;
use Crater\Models\Estimate;
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
