<?php

namespace App\Actions\Events;

use App\Actions\Vehicles\RecalculateMpg;
use App\Actions\Vehicles\RefreshVehicleOdometer;
use App\Actions\Vehicles\SyncServiceIntervals;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Writes one event and everything derived from it.
 *
 * Keeping this in one place is the point of the single-spine design: whatever
 * lane the user logged through, the same things must stay true afterwards —
 * the vehicle's mileage estimate inputs, MPG, and (for visits) the intervals
 * that the visit just reset. UpdateEvent and DeleteEvent keep the same set
 * true through the same actions.
 */
class LogEvent
{
    public function __construct(
        private WriteLineItems $writeLineItems,
        private SyncServiceIntervals $syncIntervals,
        private RecalculateMpg $recalculateMpg,
        private RefreshVehicleOdometer $refreshOdometer,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{service_type_id: int, cost_cents?: int|null, notes?: string|null}>  $lineItems
     */
    public function handle(Vehicle $vehicle, array $attributes, array $lineItems = []): Event
    {
        return DB::transaction(function () use ($vehicle, $attributes, $lineItems): Event {
            $event = $vehicle->events()->create($attributes);

            // A visit's line items are the jobs done. Each one resets its
            // interval — which is why a single shop visit can reset several
            // gauges.
            if ($event->type === EventType::Visit && $lineItems !== []) {
                $this->syncIntervals->handle(
                    $vehicle,
                    $this->writeLineItems->handle($event, $lineItems),
                );
            }

            if ($event->type === EventType::Fuel) {
                $this->recalculateMpg->handle($vehicle);
            }

            $this->refreshOdometer->handle($vehicle);

            return $event->fresh(['lineItems']) ?? $event;
        });
    }
}
