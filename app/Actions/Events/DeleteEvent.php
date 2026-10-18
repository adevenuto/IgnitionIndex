<?php

namespace App\Actions\Events;

use App\Actions\Vehicles\AssertHandSetGaugesFit;
use App\Actions\Vehicles\RecalculateMpg;
use App\Actions\Vehicles\RefreshVehicleOdometer;
use App\Actions\Vehicles\SyncServiceIntervals;
use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes a logged entry, and takes it back out of everything it fed.
 *
 * The vehicle's last remaining reading cannot go: every car is added with a
 * reading, the estimate and every mileage gauge stand on it, and a wrong one
 * can be edited instead.
 */
class DeleteEvent
{
    public function __construct(
        private SyncServiceIntervals $syncIntervals,
        private RecalculateMpg $recalculateMpg,
        private RefreshVehicleOdometer $refreshOdometer,
        private AssertHandSetGaugesFit $assertGaugesFit,
    ) {}

    public function handle(Event $event): void
    {
        $vehicle = $event->vehicle;

        if ($vehicle->events()->count() <= 1) {
            throw ValidationException::withMessages([
                'event' => 'A vehicle needs at least one reading. Edit this one instead.',
            ]);
        }

        DB::transaction(function () use ($event, $vehicle): void {
            $alreadyMisfit = $this->assertGaugesFit->misfits($vehicle);

            // Released before the delete: the foreign key would otherwise null
            // the link first and leave these gauges looking set by hand.
            $affected = [
                ...SyncServiceIntervals::serviceTypesOf($event),
                ...$this->syncIntervals->release($event),
            ];
            $wasFuel = $event->type === EventType::Fuel;

            $event->delete();

            $this->syncIntervals->handle($vehicle, $affected);

            if ($wasFuel) {
                $this->recalculateMpg->handle($vehicle);
            }

            $this->refreshOdometer->handle($vehicle);

            // Last, so it sees the readings as they will be; throwing undoes it all.
            $this->assertGaugesFit->handle($vehicle, 'event', $alreadyMisfit);
        });
    }
}
