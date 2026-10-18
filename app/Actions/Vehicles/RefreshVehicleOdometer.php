<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use Illuminate\Support\Carbon;

/**
 * Re-derives a vehicle's last known reading from its events.
 *
 * The last reading is whichever event is newest by date, recomputed rather
 * than assumed — a backfilled event must not clobber a newer one, and a deleted
 * one must not linger. The daily rate is recomputed too, so the projection the
 * gauges use is never stale relative to the readings behind it.
 */
class RefreshVehicleOdometer
{
    public function __construct(private RecalculateMileageRate $recalculateRate) {}

    public function handle(Vehicle $vehicle): void
    {
        $newest = $vehicle->events()
            ->orderByDesc('occurred_on')
            ->orderByDesc('odometer')
            ->first();

        // No readings left means nothing is known, not that the old one holds.
        if ($newest === null) {
            $vehicle->forceFill([
                'last_odometer' => null,
                'last_odometer_at' => null,
                'avg_miles_per_day' => null,
            ])->save();

            return;
        }

        $vehicle->forceFill([
            'last_odometer' => $newest->odometer,
            'last_odometer_at' => Carbon::parse($newest->occurred_on)->toDateString(),
        ])->save();

        $this->recalculateRate->handle($vehicle);
    }
}
