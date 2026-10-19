<?php

namespace App\Actions\Events;

use App\Actions\Vehicles\AssertHandSetGaugesFit;
use App\Actions\Vehicles\RecalculateMpg;
use App\Actions\Vehicles\RefreshVehicleOdometer;
use App\Actions\Vehicles\SyncServiceIntervals;
use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a logged entry, and everything that was derived from it.
 *
 * The entry's type and vehicle are fixed; everything else can change. Gauges
 * this visit had set let go of it first, then every service it named before
 * or after the edit is resettled on whichever visit is now latest.
 */
class UpdateEvent
{
    public function __construct(
        private WriteLineItems $writeLineItems,
        private SyncServiceIntervals $syncIntervals,
        private RecalculateMpg $recalculateMpg,
        private RefreshVehicleOdometer $refreshOdometer,
        private AssertHandSetGaugesFit $assertGaugesFit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{service_type_id: int, cost_cents?: int|null, notes?: string|null}>  $lineItems
     */
    public function handle(Event $event, array $attributes, array $lineItems = []): Event
    {
        return DB::transaction(function () use ($event, $attributes, $lineItems): Event {
            $vehicle = $event->vehicle;

            $alreadyMisfit = $this->assertGaugesFit->misfits($vehicle);

            $affected = [
                ...SyncServiceIntervals::serviceTypesOf($event),
                ...$this->syncIntervals->release($event),
            ];

            $event->update($attributes);

            if ($event->type === EventType::Visit) {
                $affected = [...$affected, ...$this->writeLineItems->handle($event, $lineItems)];
            }

            $this->syncIntervals->handle($vehicle, $affected);

            if ($event->type === EventType::Fuel) {
                $this->recalculateMpg->handle($vehicle);
            }

            $this->refreshOdometer->handle($vehicle);

            // Last, so it sees the readings as they will be; throwing undoes it all.
            $this->assertGaugesFit->handle($vehicle, 'odometer', $alreadyMisfit);

            return $event->fresh(['lineItems']) ?? $event;
        });
    }
}
