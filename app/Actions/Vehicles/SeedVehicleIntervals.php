<?php

namespace App\Actions\Vehicles;

use App\Enums\IntervalSource;
use App\Models\ServiceType;
use App\Models\Vehicle;

/**
 * Gives a new vehicle its full maintenance schedule from the global catalogue.
 *
 * Until now an interval row only appeared once a service was logged against it,
 * which meant a brand-new car had no gauges at all. Seeding up front means the
 * cluster exists immediately, and adding a car lands straight on its gauge wall
 * — every item uncalibrated until the owner sets a last-done date on the gauge
 * itself.
 *
 * Existing rows are left alone: a user override must survive this being run
 * again.
 */
class SeedVehicleIntervals
{
    /**
     * The three large dials on the gauge wall (design doc §6), named rather
     * than "the first three in the catalogue".
     *
     * Sort order is the catalogue's own idea of importance and it put tire
     * rotation and the engine air filter up front. These three are what an
     * owner actually recognises as the headline items on a car they just added.
     */
    public const PINNED_KEYS = ['oil-change', 'brake-pads', 'coolant'];

    public function handle(Vehicle $vehicle): void
    {
        $existing = $vehicle->intervals()->pluck('service_type_id')->all();

        // Continue the vehicle's existing order rather than restarting at 0, so
        // re-running this cannot collide with positions already in use.
        $position = $vehicle->intervals()->exists()
            ? (int) $vehicle->intervals()->max('position') + 1
            : 0;

        $types = ServiceType::query()
            ->whereNotIn('id', $existing)
            ->orderBy('sort_order')
            ->get();

        foreach ($types as $type) {
            $isActive = $type->default_interval_months !== null
                || $type->default_interval_miles !== null;

            $vehicle->intervals()->create([
                'service_type_id' => $type->id,
                'position' => $position++,
                // A named set, so the pinned three are the same on every
                // vehicle and cannot drift when the catalogue is reordered.
                'is_pinned' => $isActive && in_array($type->key, self::PINNED_KEYS, true),
                'interval_months' => $type->default_interval_months,
                'interval_miles' => $type->default_interval_miles,
                'source' => IntervalSource::Default,
                // Deliberately null: we do not know when this was last done, and
                // guessing would tell someone their oil is overdue when they
                // changed it last week.
                'last_done_at' => null,
                'last_done_odometer' => null,
                // A type with neither axis ("Other Service") can never be due,
                // so it starts inactive rather than cluttering the cluster.
                'is_active' => $isActive,
            ]);
        }
    }
}
