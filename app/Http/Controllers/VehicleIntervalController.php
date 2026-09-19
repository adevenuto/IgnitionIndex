<?php

namespace App\Http\Controllers;

use App\Enums\IntervalSource;
use App\Models\VehicleInterval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Overriding one item on a vehicle's schedule.
 *
 * There used to be a second entry point: a quick-calibrate screen that ran
 * straight after a car was added and asked "roughly when did you last do these?"
 * about four items. It asked before the owner had seen the gauges it was talking
 * about, and it is gone — calibrating is now the same gesture as any other
 * change to a gauge, made on the gauge.
 */
class VehicleIntervalController extends Controller
{
    /**
     * Per-item edit from the gauge cluster: last-done, and the interval itself.
     */
    public function update(Request $request, VehicleInterval $interval): RedirectResponse
    {
        Gate::authorize('update', $interval->vehicle);

        $validated = $request->validate([
            'last_done_at' => ['nullable', 'date', 'before_or_equal:today'],
            'last_done_odometer' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'interval_months' => ['nullable', 'integer', 'min:1', 'max:240'],
            'interval_miles' => ['nullable', 'integer', 'min:100', 'max:200000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $changedInterval = array_key_exists('interval_months', $validated)
            || array_key_exists('interval_miles', $validated);

        $interval->fill($validated);

        // Changing either axis makes this the user's schedule, not ours — which
        // is what stops a later default/VIN refresh from overwriting it.
        if ($changedInterval && $interval->isDirty(['interval_months', 'interval_miles'])) {
            $interval->source = IntervalSource::UserOverride;
        }

        $interval->save();

        return back();
    }
}
