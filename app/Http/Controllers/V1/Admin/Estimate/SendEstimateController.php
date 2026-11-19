<?php

namespace Crater\Http\Controllers\V1\Admin\Estimate;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\SendEstimatesRequest;
use Crater\Models\Estimate;
use Illuminate\Http\JsonResponse;

class SendEstimateController extends Controller
{
    public function __invoke(SendEstimatesRequest $request, Estimate $estimate): JsonResponse
    {
        $this->authorize('send estimate', $estimate);

        $response = $estimate->send($request->all());

        return response()->json($response);
    }
}
