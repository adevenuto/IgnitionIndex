<?php

namespace App\Actions\Vehicles;

use App\Enums\EventType;
use App\Enums\IntervalSource;
use App\Models\Event;
use App\Models\ServiceType;
use App\Models\Vehicle;
use App\Models\VehicleInterval;
use Illuminate\Support\Collection;

/**
 * Keeps each gauge's last-done pointed at the latest visit that did the job.
 *
 * One rule for logging, editing and deleting alike: a gauge moves forward to
 * the newest visit with its service, and never backwards past something newer
 * — a newer visit, or a date the owner set by hand in the gauge dialog.
 *
 * Going backwards only happens through release(): a gauge that a changed or
 * deleted visit had set lets go of it first, and handle() then settles it on
 * whichever visit is now latest, or leaves it unset if none is left. A gauge
 * set by hand has no visit to let go of, so an edit to an older entry never
 * overwrites it.
 */
class SyncServiceIntervals
{
    /**
     * What each released gauge said before it let go, keyed by interval id, so
     * one resettled on the same date and reading keeps its reminder history.
     * Editing a visit's notes must not make a due reminder go out again.
     *
     * @var array<int, array{0: string|null, 1: int|null}>
     */
    private array $released = [];

    /**
     * Clear every gauge this visit set, before it changes or goes.
     *
     * Must run before a delete: the foreign key nulls last_done_event_id the
     * moment the event row is gone, and a gauge with no link reads as set by
     * hand — which handle() would then leave alone.
     *
     * @return list<int> the service types released, for handle() to resettle
     */
    public function release(Event $event): array
    {
        $intervals = VehicleInterval::query()
            ->where('last_done_event_id', $event->id)
            ->get();

        foreach ($intervals as $interval) {
            $this->released[$interval->id] = [
                $interval->last_done_at?->toDateString(),
                $interval->last_done_odometer,
            ];

            $interval->forceFill([
                'last_done_at' => null,
                'last_done_odometer' => null,
                'last_done_event_id' => null,
            ])->save();
        }

        return $intervals->pluck('service_type_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  array<int, int>  $serviceTypeIds
     */
    public function handle(Vehicle $vehicle, array $serviceTypeIds): void
    {
        $serviceTypeIds = array_values(array_unique($serviceTypeIds));

        if ($serviceTypeIds === []) {
            return;
        }

        $serviceTypes = ServiceType::query()->whereKey($serviceTypeIds)->get()->keyBy('id');

        foreach ($serviceTypeIds as $serviceTypeId) {
            $serviceType = $serviceTypes->get($serviceTypeId);
            $latest = $this->latestVisit($vehicle, $serviceTypeId);

            if ($serviceType === null || $latest === null) {
                continue;
            }

            $this->apply($vehicle, $serviceType, $latest);
        }

        // Released with no visit left to settle on: unset now, so its reminder
        // cycle starts over whenever it is next set.
        if ($this->released !== []) {
            VehicleInterval::query()
                ->whereKey(array_keys($this->released))
                ->whereNull('last_done_at')
                ->update(['last_reminded_status' => null, 'last_reminded_at' => null]);

            $this->released = [];
        }
    }

    private function apply(Vehicle $vehicle, ServiceType $serviceType, Event $visit): void
    {
        /** @var VehicleInterval $interval */
        $interval = $vehicle->intervals()->firstOrNew(
            ['service_type_id' => $serviceType->id],
        );

        // An interval row is created on demand if the vehicle had none yet.
        if (! $interval->exists) {
            $interval->fill([
                'interval_months' => $serviceType->default_interval_months,
                'interval_miles' => $serviceType->default_interval_miles,
                'source' => IntervalSource::Default,
            ]);
        }

        if ($interval->last_done_event_id === $visit->id) {
            return;
        }

        // Only forward. Backfilling an older visit must not rewind an interval
        // that a more recent visit, or the owner, already set.
        $isNewer = $interval->last_done_at === null
            || $visit->occurred_on->greaterThanOrEqualTo($interval->last_done_at);

        if (! $isNewer) {
            return;
        }

        $before = $this->released[$interval->id] ?? null;

        $interval->last_done_at = $visit->occurred_on;
        $interval->last_done_odometer = $visit->odometer;
        $interval->last_done_event_id = $visit->id;

        // Start the reminder cycle over. Without this, the next time this item
        // came due we would believe we had already mentioned it.
        if ($before !== [$visit->occurred_on->toDateString(), $visit->odometer]) {
            $interval->last_reminded_status = null;
            $interval->last_reminded_at = null;
        }

        $interval->save();
    }

    private function latestVisit(Vehicle $vehicle, int $serviceTypeId): ?Event
    {
        return $vehicle->events()
            ->where('type', EventType::Visit)
            ->whereHas('lineItems', fn ($query) => $query->where('service_type_id', $serviceTypeId))
            ->orderByDesc('occurred_on')
            ->orderByDesc('odometer')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The service types a visit's line items name.
     *
     * @return list<int>
     */
    public static function serviceTypesOf(Event $event): array
    {
        /** @var Collection<int, int> $ids */
        $ids = $event->lineItems()->pluck('service_type_id');

        return $ids->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }
}
