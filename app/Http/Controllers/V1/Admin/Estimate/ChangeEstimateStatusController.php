<?php

namespace Crater\Http\Controllers\V1\Admin\Estimate;

use Crater\Http\Controllers\Controller;
use Crater\Models\Estimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChangeEstimateStatusController extends Controller
{
    public function __invoke(Request $request, Estimate $estimate): JsonResponse
    {
        $this->authorize('send estimate', $estimate);

        return DB::transaction(function () use ($request, $estimate) {
            $lockedEstimate = Estimate::where('id', $estimate->id)->lockForUpdate()->first();
            $newStatus = (string) $request->input('status');

            if (! $lockedEstimate || ! $lockedEstimate->canTransitionTo($newStatus)) {
                return response()->json([
                    'error' => 'invalid_status_transition',
                    'message' => 'Cannot transition estimate from '.($lockedEstimate->status ?? 'unknown')." to {$newStatus}.",
                ], 422);
            }

            $lockedEstimate->update(['status' => $newStatus]);
            $estimate->status = $newStatus;

            return response()->json([
                'success' => true,
            ]);
        });
    }
}
