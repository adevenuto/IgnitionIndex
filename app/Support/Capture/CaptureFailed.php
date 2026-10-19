<?php

namespace App\Support\Capture;

use RuntimeException;

/**
 * The reader could not produce a reading — unreachable, declined, or answered
 * with something that was not the requested shape. The person types instead;
 * nothing about the entry depends on capture working.
 */
class CaptureFailed extends RuntimeException {}
