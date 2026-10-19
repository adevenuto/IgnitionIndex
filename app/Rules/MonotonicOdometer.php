<?php

namespace App\Rules;

use App\Models\Event;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Odometer readings must be monotonic, because the mileage estimate and every
 * gauge built on it depend on clean data.
 *
 * Crucially this is monotonic *in time*, not merely "higher than the newest
 * reading". Backfilling past history is a first-class flow — the brief makes
 * manual backfill the only way to record a vehicle's past — so a reading dated
 * last year is checked against its neighbours on either side of that date, not
 * against today's odometer.
 *
 * Implausibly large jumps are not rejected outright; they ask for confirmation,
 * matching the rule that assisted input never silently commits.
 */
class MonotonicOdometer implements ValidationRule
{
    /** Above this implied daily rate a jump is treated as suspicious. */
    private const SUSPICIOUS_MILES_PER_DAY = 300;

    /** Below this absolute delta a jump is never worth questioning. */
    private const JUMP_FLOOR_MILES = 3000;

    public function __construct(
        private Vehicle $vehicle,
        private string $occurredOn,
        private bool $confirmed = false,
        private ?int $ignoreEventId = null,
    ) {}

    /**
     * @param  Closure(string, string|null=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $odometer = (int) $value;
        $date = Carbon::parse($this->occurredOn)->toDateString();

        $previous = $this->neighbour($date, before: true);
        $next = $this->neighbour($date, before: false);

        if ($previous !== null && $odometer < $previous->odometer) {
            $fail(sprintf(
                'This reading is lower than the %s mi recorded on %s. Odometers only go up.',
                number_format($previous->odometer),
                $previous->occurred_on->format('M j, Y'),
            ));

            return;
        }

        if ($next !== null && $odometer > $next->odometer) {
            $fail(sprintf(
                'This reading is higher than the %s mi already recorded on %s.',
                number_format($next->odometer),
                $next->occurred_on->format('M j, Y'),
            ));

            return;
        }

        if ($this->confirmed || $previous === null) {
            return;
        }

        $days = (int) $previous->occurred_on->startOfDay()
            ->diffInDays(Carbon::parse($date)->startOfDay());
        $delta = $odometer - $previous->odometer;

        if ($days < 1 || $delta < self::JUMP_FLOOR_MILES) {
            return;
        }

        if ($delta / $days > self::SUSPICIOUS_MILES_PER_DAY) {
            $fail(sprintf(
                'That is %s mi in %d day%s. Confirm the reading is right.',
                number_format($delta),
                $days,
                $days === 1 ? '' : 's',
            ));
        }
    }

    /**
     * The nearest reading on either side of the given date, so a backfilled
     * event is bounded by its actual neighbours rather than by the newest row.
     *
     * Strictly before and strictly after. Readings carry a date but no time,
     * so two on the same day cannot be put in order — a fill-up in the morning
     * and a shop visit in the afternoon are both right whichever is higher.
     * Counting same-day readings as neighbours made each one bound the other
     * from both sides, so a second reading had to match the first exactly.
     */
    private function neighbour(string $date, bool $before): ?Event
    {
        return $this->vehicle->events()
            ->when(
                $this->ignoreEventId !== null,
                fn ($query) => $query->whereKeyNot($this->ignoreEventId),
            )
            ->whereDate('occurred_on', $before ? '<' : '>', $date)
            ->orderBy('occurred_on', $before ? 'desc' : 'asc')
            ->orderBy('odometer', $before ? 'desc' : 'asc')
            ->first();
    }
}
