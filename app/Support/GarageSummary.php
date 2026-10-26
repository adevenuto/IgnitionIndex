<?php

namespace App\Support;

use App\Enums\GaugeStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;

/**
 * The counts the app shell needs: how much is due across the whole garage, and
 * how many vehicles want a fresh odometer reading.
 *
 * Shared globally so a due item is visible from anywhere rather than only from
 * the garage — previously you had to go looking for it, which rather defeats
 * the point of a reminder.
 *
 * Deliberately computed rather than stored: progress depends on a PROJECTED
 * odometer, which moves with the clock, so a cached count would be wrong by
 * tomorrow. It costs two queries and some arithmetic over a handful of rows.
 */
readonly class GarageSummary
{
    /**
     * The rail shows three. The artboard back-fills from the healthiest items
     * when fewer than three are pressing, so the list never renders ragged.
     */
    private const DUE_NEXT_LIMIT = 3;

    /**
     * @param  list<DueNextItem>  $dueNext
     */
    private function __construct(
        public int $dueCount,
        public int $needsReadingCount,
        public int $vehicleCount,
        public array $dueNext = [],
        public ?int $onlyVehicleId = null,
    ) {}

    public static function for(User $user): self
    {
        return self::fromVehicles(
            $user->vehicles()->with('intervals.serviceType')->get()
        );
    }

    public static function empty(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'due_count' => $this->dueCount,
            'needs_reading_count' => $this->needsReadingCount,
            'vehicle_count' => $this->vehicleCount,
            // The shell's Log button goes straight to a lone car instead of
            // asking which one.
            'only_vehicle_id' => $this->onlyVehicleId,
            'due_next' => array_map(
                static fn (DueNextItem $item): array => $item->toArray(),
                $this->dueNext,
            ),
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $vehicles
     */
    public static function fromVehicles(Collection $vehicles): self
    {
        $due = 0;
        $needsReading = 0;

        /** @var list<array{item: DueNextItem, progress: float}> $ranked */
        $ranked = [];

        foreach ($vehicles as $vehicle) {
            $gauges = VehicleGauges::for($vehicle);
            $due += $gauges->needingAttention()->count();

            if ($gauges->mileage->needsReading) {
                $needsReading++;
            }

            foreach ($gauges->gauges as $gauge) {
                // Uncalibrated items have no baseline, so they cannot be ranked
                // against anything — they are a prompt, not a countdown.
                if ($gauge->status === GaugeStatus::Uncalibrated) {
                    continue;
                }

                $ranked[] = [
                    'progress' => $gauge->progress,
                    'item' => new DueNextItem(
                        vehicleId: $vehicle->id,
                        vehicleName: $vehicle->displayName(),
                        serviceName: $gauge->interval->serviceType->name,
                        status: $gauge->status->value,
                        percent: (int) round($gauge->progress * 100),
                    ),
                ];
            }
        }

        usort($ranked, static fn (array $a, array $b): int => $b['progress'] <=> $a['progress']);

        return new self(
            $due,
            $needsReading,
            $vehicles->count(),
            array_map(
                static fn (array $row): DueNextItem => $row['item'],
                array_slice($ranked, 0, self::DUE_NEXT_LIMIT),
            ),
            $vehicles->count() === 1 ? $vehicles->first()?->id : null,
        );
    }
}
