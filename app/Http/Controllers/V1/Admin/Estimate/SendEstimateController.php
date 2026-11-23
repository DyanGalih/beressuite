<?php

namespace App\Http\Controllers\V1\Admin\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendEstimatesRequest;
use App\Models\Estimate;
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
