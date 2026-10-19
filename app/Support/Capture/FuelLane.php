<?php

namespace App\Support\Capture;

use App\Support\VehicleMileage;
use Illuminate\Support\Facades\Date;

/**
 * A fill-up: the odometer, and what the pump says.
 *
 * A pump display usually carries gallons, total and price per gallon at once,
 * which is the whole reason capture beats typing here — one photo fills most
 * of the entry. Price per gallon is read only to cross-check the other two;
 * the entry itself stores gallons and total.
 */
class FuelLane implements LaneDefinition
{
    /** Pump prices outside this band are almost certainly a misread. */
    private const PLAUSIBLE_PRICE = [1.5, 10.0];

    /** How far a read may sit from the projection before it is questioned. */
    private const ESTIMATE_TOLERANCE_MILES = 3_000;

    public function properties(): array
    {
        return [
            'odometer' => [
                'type' => ['integer', 'null'],
                'description' => 'Total miles on the odometer, as a whole number. Not a trip meter.',
            ],
            'gallons' => [
                'type' => ['number', 'null'],
                'description' => 'Gallons of fuel pumped.',
            ],
            'total_cost' => [
                'type' => ['number', 'null'],
                'description' => 'Total sale amount in US dollars.',
            ],
            'price_per_gallon' => [
                'type' => ['number', 'null'],
                'description' => 'Price per gallon in US dollars.',
            ],
            'occurred_on' => [
                'type' => ['string', 'null'],
                'description' => 'Date of the fill-up as YYYY-MM-DD, only if the source states or clearly implies one.',
            ],
        ];
    }

    public function instructions(): string
    {
        return <<<'TEXT'
        This is a fuel fill-up. The source may be a photo of a fuel pump display, a
        receipt, or an instrument cluster, or a sentence the person said.

        - A pump display usually shows the sale total, the gallons, and the price per
          gallon. Pumps label these differently ("Sale", "Total", "$", "Gallons",
          "Volume", "Price/Gal"); read them by their labels and units, not by position.
        - Some pumps show a price per gallon with a trailing 9/10 of a cent (e.g. 3.85⁹);
          include it as a decimal (3.859).
        - On an instrument cluster, the odometer is the total distance. Ignore trip
          meters (often marked "Trip", "A" or "B"), range-to-empty and fuel economy.
        - In speech, people say numbers loosely: "sixty-two four ten" is 62410 miles,
          "twelve and a half" is 12.5 gallons, "forty-eight twelve" after a mention of
          money is $48.12.
        TEXT;
    }

    public function fields(array $raw): array
    {
        return [
            'odometer' => $this->number($raw['odometer'] ?? null, 0, 2_000_000, 0),
            'gallons' => $this->number($raw['gallons'] ?? null, 0.001, 200, 3),
            'total_cost' => $this->number($raw['total_cost'] ?? null, 0, 999_999.99, 2),
            'price_per_gallon' => $this->number($raw['price_per_gallon'] ?? null, 0.001, 100, 3),
            'occurred_on' => $this->date($raw['occurred_on'] ?? null),
        ];
    }

    public function flags(array $fields, VehicleMileage $mileage): array
    {
        $flags = [];
        $odometer = $fields['odometer'];
        $gallons = $fields['gallons'];
        $total = $fields['total_cost'];
        $price = $fields['price_per_gallon'];

        if (is_int($odometer) && $mileage->lastOdometer !== null && $odometer < $mileage->lastOdometer) {
            $flags[] = [
                'field' => 'odometer',
                'message' => 'Lower than your last reading of '.number_format($mileage->lastOdometer).' mi.',
            ];
        } elseif (
            is_int($odometer)
            && $mileage->lastOdometer !== null
            && abs($odometer - $mileage->projectedOdometer) > self::ESTIMATE_TOLERANCE_MILES
        ) {
            $flags[] = [
                'field' => 'odometer',
                'message' => 'Far from the estimate of ≈'.number_format($mileage->projectedOdometer)
                    .' mi. Check it is the odometer and not a trip meter.',
            ];
        }

        if (is_float($gallons) && is_float($total)) {
            $perGallon = $total / $gallons;

            if ($perGallon < self::PLAUSIBLE_PRICE[0] || $perGallon > self::PLAUSIBLE_PRICE[1]) {
                $flags[] = [
                    'field' => 'total_cost',
                    'message' => 'That works out to $'.number_format($perGallon, 2).' a gallon.',
                ];
            } elseif (is_float($price) && abs($gallons * $price - $total) > 0.05) {
                $flags[] = [
                    'field' => 'total_cost',
                    'message' => 'Gallons × $'.number_format($price, 3).' comes to $'
                        .number_format($gallons * $price, 2).', not $'.number_format($total, 2).'.',
                ];
            }
        }

        return $flags;
    }

    /**
     * Rounding also settles the type: whole numbers come back as int, the rest
     * as float, so the client never has to tell "12" from "12.0".
     */
    private function number(mixed $value, float $min, float $max, int $precision): int|float|null
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        if ($value < $min || $value > $max) {
            return null;
        }

        return $precision === 0 ? (int) round($value) : round((float) $value, $precision);
    }

    /** A real calendar date, not in the future. */
    private function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if (! checkdate($month, $day, $year) || $value > Date::today()->toDateString()) {
            return null;
        }

        return $value;
    }
}
