<?php

namespace App\Http\Controllers\V1\Customer\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\EstimateResource;
use App\Models\Company;
use App\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcceptEstimateController extends Controller
{
    public function __invoke(Request $request, Company $company, int|string $id): EstimateResource|JsonResponse
    {
        $estimate = $company->estimates()
            ->whereCustomer(Auth::guard('customer')->id())
            ->where('id', $id)
            ->first();

        if (! $estimate) {
            return response()->json(['error' => 'estimate_not_found'], 404);
        }

        $newStatus = (string) $request->input('status');
        if (! in_array($newStatus, [Estimate::STATUS_ACCEPTED, Estimate::STATUS_REJECTED], true) || ! $estimate->canTransitionTo($newStatus)) {
            return response()->json([
                'error' => 'invalid_status_transition',
                'message' => 'Customers can only accept or reject eligible estimates.',
            ], 422);
        }

        $estimate->update(['status' => $newStatus]);

        return new EstimateResource($estimate);
    }
}
