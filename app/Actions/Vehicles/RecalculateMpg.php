<?php

namespace App\Actions\Vehicles;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\Vehicle;

/**
 * MPG for every full-tank fill-up, worked out again from the whole history.
 *
 * MPG is only meaningful between two full tanks: the miles since the previous
 * full fill divided by the gallons it took to fill up again. A partial fill or
 * a first-ever fill stays null rather than guessing.
 *
 * Each fill depends on the one before it, so computing it once at insert went
 * stale whenever an earlier fill-up was backfilled, edited or deleted. A
 * vehicle's fuel history is short enough to walk in full every time.
 */
class RecalculateMpg
{
    public function handle(Vehicle $vehicle): void
    {
        $fills = $vehicle->events()
            ->where('type', EventType::Fuel)
            ->where('full_tank', true)
            ->orderBy('occurred_on')
            ->orderBy('odometer')
            ->orderBy('id')
            ->get();

        $previous = null;

        foreach ($fills as $fill) {
            $mpg = $this->mpgFor($fill, $previous);

            if ($fill->mpg === null ? $mpg !== null : (float) $fill->mpg !== $mpg) {
                $fill->forceFill(['mpg' => $mpg])->save();
            }

            $previous = $fill;
        }

        // A fill that is no longer full (or never was) has no MPG to show.
        $vehicle->events()
            ->where('type', EventType::Fuel)
            ->where(fn ($query) => $query->where('full_tank', false)->orWhereNull('full_tank'))
            ->whereNotNull('mpg')
            ->update(['mpg' => null]);
    }

    private function mpgFor(Event $fill, ?Event $previous): ?float
    {
        if ($previous === null || ! is_numeric($fill->gallons) || (float) $fill->gallons <= 0.0) {
            return null;
        }

        $miles = $fill->odometer - $previous->odometer;

        return $miles <= 0 ? null : round($miles / (float) $fill->gallons, 2);
    }
}
