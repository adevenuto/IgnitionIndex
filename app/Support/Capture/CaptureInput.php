<?php

namespace App\Support\Capture;

/**
 * What the person gave us: a photo, already re-encoded as JPEG, or the words
 * their phone heard. Exactly one of the two.
 */
readonly class CaptureInput
{
    private function __construct(
        public ?string $jpeg,
        public ?string $transcript,
    ) {}

    public static function photo(string $jpeg): self
    {
        return new self($jpeg, null);
    }

    public static function speech(string $transcript): self
    {
        return new self(null, $transcript);
    }

    public function isPhoto(): bool
    {
        return $this->jpeg !== null;
    }
}
