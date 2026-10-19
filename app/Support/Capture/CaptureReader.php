<?php

namespace App\Support\Capture;

/**
 * Turns a photo or a sentence into a JSON object matching a schema.
 *
 * An interface because the real one is a paid network call: tests bind a fake,
 * and nothing else in the pipeline knows which model sits behind it.
 */
interface CaptureReader
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws CaptureFailed when nothing usable comes back.
     */
    public function read(string $instructions, array $schema, CaptureInput $input): array;
}
