<?php

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use App\Models\VehicleInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Trades two gauges' places on the wall.
 *
 * ONE RULE: exchange both `position` and `is_pinned` between the two rows. That
 * covers every case correctly —
 *
 *   unpinned ↔ unpinned   reorders the list
 *   pinned   ↔ pinned     reorders the top row
 *   pinned   ↔ unpinned   they genuinely trade places
 *
 * — and the wall's invariants hold by construction rather than by validation.
 * A flag is MOVED, never created, so the pinned count cannot drift from three;
 * the multiset of position values is preserved, so positions stay unique; and
 * no row other than these two is written, so the inactive intervals that hold
 * positions but never render (VehicleGauges::for filters is_active) keep theirs.
 *
 * That last point is why this is a two-id exchange rather than "here is the new
 * full order". The client only ever sees the active subset, so it cannot send a
 * complete order; renumbering from a partial one would shuffle the inactive rows
 * and silently reorder gauges that reappear when someone switches them back on.
 */
class SwapGaugePositions
{
    public function handle(Vehicle $vehicle, int $sourceId, int $targetId): void
    {
        DB::transaction(function () use ($vehicle, $sourceId, $targetId): void {
            // The whole set, inactive rows included: they own positions too, and
            // normalise() has to see them or it would hand out a number already
            // in use. lockForUpdate serialises two racing swaps.
            $intervals = $vehicle->intervals()
                ->lockForUpdate()
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $this->normalise($intervals);

            $source = $intervals->firstWhere('id', $sourceId);
            $target = $intervals->firstWhere('id', $targetId);

            if (! $source instanceof VehicleInterval || ! $target instanceof VehicleInterval) {
                return;
            }

            [$sourcePosition, $sourcePinned] = [$source->position, $source->is_pinned];

            $source->forceFill([
                'position' => $target->position,
                'is_pinned' => $target->is_pinned,
            ])->save();

            $target->forceFill([
                'position' => $sourcePosition,
                'is_pinned' => $sourcePinned,
            ])->save();
        });
    }

    /**
     * Give every interval its own position before anything is exchanged.
     *
     * `position` defaults to 0 and the (vehicle_id, position) index is NOT
     * unique, so rows can legitimately arrive sharing one — a factory-built
     * vehicle has every row at 0. Two rows on the same number make a swap a
     * silent no-op: it exchanges identical values, the server reports success,
     * and the card the user just dragged snaps back with nothing to explain it.
     *
     * Renumbering follows the repair pass in SyncVehiclePhotos::applyOrder():
     * one deterministic sweep to 0..n-1 in the current order, writing only the
     * rows that actually move. Untouched when positions are already distinct,
     * so the common path costs one comparison.
     *
     * @param  Collection<int, VehicleInterval>  $intervals
     */
    private function normalise(Collection $intervals): void
    {
        if ($intervals->pluck('position')->unique()->count() === $intervals->count()) {
            return;
        }

        foreach ($intervals->values() as $position => $interval) {
            if ($interval->position !== $position) {
                $interval->forceFill(['position' => $position])->save();
            }
        }
    }
}
