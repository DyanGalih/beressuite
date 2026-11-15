<?php

namespace Crater\Http\Controllers\V1\Admin\Estimate;

use Crater\Http\Controllers\Controller;
use Crater\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChangeEstimateStatusController extends Controller
{
    /**
    * Handle the incoming request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  Estimate $estimate
    * @return \Illuminate\Http\Response
    */
    public function __invoke(Request $request, Estimate $estimate)
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
