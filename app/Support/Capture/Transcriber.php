<?php

namespace App\Support\Capture;

/**
 * Turns a short recorded clip into the words said in it.
 *
 * An interface for the same reason as CaptureReader: the real one is a paid
 * network call, and nothing downstream cares which service heard it.
 */
interface Transcriber
{
    /**
     * @throws CaptureFailed when nothing usable comes back.
     */
    public function transcribe(string $path, string $mimeType): string;
}
