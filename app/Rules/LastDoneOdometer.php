<?php

namespace App\Rules;

use App\Models\Event;
use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The odometer a service was last done at has to fit the readings on record.
 *
 * A gauge's mileage side counts from this number, so one above the car's
 * current reading leaves it negative and the time side silently wins — the
 * gauge then ignores every mile driven. The usual cause is reading "odometer
 * then" as "odometer now", which is worth saying back in the message.
 *
 * Not MonotonicOdometer: that bounds a reading by others on the same day from
 * both sides, but a service done this morning can sit below a reading logged
 * this afternoon. Here a same-day reading only caps it.
 */
class LastDoneOdometer implements ValidationRule
{
    public function __construct(
        private Vehicle $vehicle,
        private ?string $lastDoneAt,
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
        $date = $this->date();

        // Capped by the lowest reading taken on or after the service. With no
        // date, all we know is that it happened by now: the highest reading.
        $after = $date === null
            ? $this->vehicle->events()->orderByDesc('odometer')->first()
            : $this->vehicle->events()
                ->whereDate('occurred_on', '>=', $date)
                ->orderBy('odometer')
                ->first();

        if ($after !== null && $odometer > $after->odometer) {
            $fail(sprintf(
                'This is higher than the %s mi recorded on %s. Enter the reading from when this service was done, not today\'s.',
                number_format($after->odometer),
                $after->occurred_on->format('M j, Y'),
            ));

            return;
        }

        if ($date === null) {
            return;
        }

        /** @var Event|null $before */
        $before = $this->vehicle->events()
            ->whereDate('occurred_on', '<', $date)
            ->orderByDesc('odometer')
            ->first();

        if ($before !== null && $odometer < $before->odometer) {
            $fail(sprintf(
                'This is lower than the %s mi recorded on %s, before this service. Odometers only go up.',
                number_format($before->odometer),
                $before->occurred_on->format('M j, Y'),
            ));
        }
    }

    /**
     * An unparseable date is the date field's error to report, not this one's,
     * so it falls back to "no date" rather than throwing.
     */
    private function date(): ?string
    {
        if ($this->lastDoneAt === null || $this->lastDoneAt === '') {
            return null;
        }

        try {
            return Carbon::parse($this->lastDoneAt)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
