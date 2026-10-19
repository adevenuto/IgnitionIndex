<?php

namespace App\Support\Capture;

use App\Support\VehicleMileage;

/**
 * Everything one quick-add lane contributes to assisted capture.
 *
 * The pipeline around it — validation, the image pass, the call to the reader,
 * the response — is shared. A lane only says what its fields are, how to read
 * them, and which readings deserve a second look.
 */
interface LaneDefinition
{
    /**
     * JSON Schema properties for the lane's fields, every one of them nullable:
     * a field the source does not show must come back null, never guessed.
     *
     * @return array<string, array<string, mixed>>
     */
    public function properties(): array;

    /**
     * What to read and how, in the lane's own terms. The shared preamble about
     * nulls, context and units is added around it.
     */
    public function instructions(): string;

    /**
     * The reader's raw output, reduced to values the entry form will accept.
     * Anything out of range or of the wrong type becomes null.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, int|float|string|null>
     */
    public function fields(array $raw): array;

    /**
     * Readings that are plausible enough to keep but worth a second look —
     * shown on the review card, never corrected.
     *
     * @param  array<string, int|float|string|null>  $fields
     * @return list<array{field: string, message: string}>
     */
    public function flags(array $fields, VehicleMileage $mileage): array;
}
