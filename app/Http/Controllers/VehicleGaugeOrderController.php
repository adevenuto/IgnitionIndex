<?php

namespace App\Http\Controllers;

use App\Actions\Vehicles\SwapGaugePositions;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Rearranging the gauge wall: which gauges sit where, and which three are pinned.
 *
 * Vehicle-scoped on purpose. The sibling route, vehicle-intervals/{interval},
 * carries no vehicle, so it has nothing to check a second interval id against —
 * an endpoint there could authorise the caller's own vehicle while quietly
 * repositioning a row belonging to someone else's.
 */
class VehicleGaugeOrderController extends Controller
{
    /**
     * Swap two gauges. See SwapGaugePositions for why it is an exchange of two
     * ids rather than a new full ordering.
     */
    public function update(
        Request $request,
        Vehicle $vehicle,
        SwapGaugePositions $swap,
    ): RedirectResponse {
        // Before validation, so naming another user's vehicle is a 403 rather
        // than leaking through as a 422 about the ids.
        Gate::authorize('update', $vehicle);

        // Scoped to THIS vehicle and to rows the wall actually draws. An id from
        // another vehicle simply fails to exist, and pinning can never land on an
        // inactive row — which is the "pinned ⊆ active" invariant, enforced here
        // rather than asserted afterwards.
        $onThisWall = fn (): Exists => Rule::exists('vehicle_intervals', 'id')
            ->where('vehicle_id', $vehicle->id)
            ->where('is_active', true);

        $validated = $request->validate([
            'source_id' => ['required', 'integer', $onThisWall()],
            'target_id' => ['required', 'integer', 'different:source_id', $onThisWall()],
        ]);

        $swap->handle($vehicle, $validated['source_id'], $validated['target_id']);

        return back();
    }
}
