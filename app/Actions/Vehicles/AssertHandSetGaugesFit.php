<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use App\Models\VehicleInterval;
use App\Rules\LastDoneOdometer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * A gauge's hand-set "odometer then" still has to fit the readings once one of
 * them changes.
 *
 * LastDoneOdometer checks that number when the gauge is saved, but the readings
 * it was checked against can be edited or deleted afterwards. A reading moved
 * below the service's odometer would leave the gauge's mileage side negative —
 * the time side silently wins and every mile driven is ignored again.
 *
 * Runs inside the edit or delete's transaction, after the change: throwing
 * rolls the change back. Gauges set by a visit are not checked, because they
 * follow the visit, and the visit's own reading is one of the readings.
 */
class AssertHandSetGaugesFit
{
    /**
     * The hand-set gauges already out of order, before the change is made.
     *
     * Those are not this change's doing — old data can predate the check — so
     * they must not block an unrelated edit. Only a gauge the change newly puts
     * out of order does.
     *
     * @return list<int>
     */
    public function misfits(Vehicle $vehicle): array
    {
        return array_values($this->handSet($vehicle)
            ->reject(fn (VehicleInterval $interval): bool => $this->fits($vehicle, $interval))
            ->map(fn (VehicleInterval $interval): int => $interval->id)
            ->all());
    }

    /**
     * @param  string  $field  where the error belongs on the form that made the change
     * @param  list<int>  $alreadyMisfit  from misfits(), taken before the change
     */
    public function handle(Vehicle $vehicle, string $field, array $alreadyMisfit = []): void
    {
        foreach ($this->handSet($vehicle) as $interval) {
            if (in_array($interval->id, $alreadyMisfit, true)) {
                continue;
            }

            if (! $this->fits($vehicle, $interval)) {
                throw ValidationException::withMessages([
                    $field => $this->message($interval),
                ]);
            }
        }
    }

    /**
     * @return Collection<int, VehicleInterval>
     */
    private function handSet(Vehicle $vehicle): Collection
    {
        return $vehicle->intervals()
            ->with('serviceType')
            ->whereNull('last_done_event_id')
            ->whereNotNull('last_done_odometer')
            ->get();
    }

    private function fits(Vehicle $vehicle, VehicleInterval $interval): bool
    {
        // The same question the gauge dialog asked when the value was saved,
        // asked again of the readings as they are now.
        $rule = new LastDoneOdometer($vehicle, $interval->last_done_at?->toDateString());

        return $rule->problemWith((int) $interval->last_done_odometer) === null;
    }

    private function message(VehicleInterval $interval): string
    {
        $when = $interval->last_done_at !== null
            ? ' on '.$interval->last_done_at->format('M j, Y')
            : '';

        return sprintf(
            '%s is set as last done at %s mi%s, and this change would put your readings out of order with it. Update that gauge first.',
            $interval->serviceType->name,
            number_format((int) $interval->last_done_odometer),
            $when,
        );
    }
}
