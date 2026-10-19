<?php

namespace App\Actions\Capture;

use App\Enums\CaptureLane;
use App\Models\Vehicle;
use App\Support\Capture\CaptureFailed;
use App\Support\Capture\CaptureInput;
use App\Support\Capture\CaptureReader;
use App\Support\VehicleMileage;
use Illuminate\Support\Facades\Date;

/**
 * Proposes field values for one entry from a photo or a sentence.
 *
 * It proposes and nothing more. The values go back to the review card, the
 * person confirms or corrects them, and the entry is saved through the same
 * endpoint as a typed one — so every guardrail on that path still applies,
 * and nothing is ever written on the strength of a read alone.
 *
 * The shared half lives here: the rules every lane reads by, the vehicle's
 * context, the schema wrapper and the response. The lane supplies the rest.
 */
class ReadCapture
{
    public function __construct(private CaptureReader $reader) {}

    /**
     * @return array{
     *     fields: array<string, int|float|string|null>,
     *     flags: list<array{field: string, message: string}>,
     *     note: string|null,
     * }
     *
     * @throws CaptureFailed
     */
    public function handle(Vehicle $vehicle, CaptureLane $lane, CaptureInput $input): array
    {
        $definition = $lane->definition();
        $mileage = VehicleMileage::for($vehicle);

        $raw = $this->reader->read(
            $this->instructions($definition->instructions(), $vehicle, $mileage),
            $this->schema($definition->properties()),
            $input,
        );

        $fields = $definition->fields($raw);
        $note = $raw['note'] ?? null;

        return [
            'fields' => $fields,
            'flags' => $definition->flags($fields, $mileage),
            'note' => is_string($note) && trim($note) !== '' ? mb_substr(trim($note), 0, 200) : null,
        ];
    }

    /**
     * The lane's fields plus a note, every one required and nullable. Required
     * matters: it makes "not shown" an explicit null rather than an omission
     * the reader might fill with a guess.
     *
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    private function schema(array $properties): array
    {
        $properties['note'] = [
            'type' => ['string', 'null'],
            'description' => 'One short sentence for the person, only if something stopped a value '
                .'from being read (glare, cropped, ambiguous). Otherwise null.',
        ];

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    private function instructions(string $lane, Vehicle $vehicle, VehicleMileage $mileage): string
    {
        $today = Date::today()->toDateString();

        $known = $mileage->lastOdometer === null
            ? 'No odometer reading is on record yet.'
            : 'Last recorded odometer: '.number_format($mileage->lastOdometer)
                .' mi on '.$mileage->lastOdometerAt?->toDateString()
                .'. Estimated today: about '.number_format($mileage->projectedOdometer).' mi.';

        return <<<TEXT
        You read the numbers a driver needs to log an entry for their car, from a photo
        or from something they said, and return them as JSON.

        Rules:
        - Only report values the source actually shows or says. If a value is not
          there, is unreadable, or is ambiguous, return null for it. Never estimate,
          infer, or fill a value in from the context below; the person will type
          anything you leave out, and a wrong number is worse than a missing one.
        - Units are US: miles, gallons, US dollars. Return plain numbers, without
          units, currency symbols or thousands separators.
        - The context is there to help you tell which number is which (for example an
          odometer from a trip meter), not to supply values.

        {$lane}

        Context:
        - Vehicle: {$vehicle->displayName()}
        - {$known}
        - Today: {$today}
        TEXT;
    }
}
