<?php

namespace App\Actions\Capture;

use Illuminate\Http\UploadedFile;
use Illuminate\Image\ImageException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Image;

/**
 * Makes a capture photo upright, smaller and anonymous, as JPEG bytes.
 *
 * orient() must come first — the rotation lives in EXIF, which re-encoding
 * discards — and re-encoding is also what strips GPS from a photo taken at a
 * pump. The result is never stored: it exists only for the length of the
 * request that reads it.
 */
class PrepareCapturePhoto
{
    /**
     * @throws ImageException when the bytes cannot be decoded as an image.
     */
    public function handle(UploadedFile $file): string
    {
        $edge = $this->longEdge();

        return Image::fromUpload($file)
            ->orient()
            ->scale($edge, $edge)
            ->toJpeg()
            ->quality($this->quality())
            ->toBytes();
    }

    /**
     * @return int<1, max>
     */
    private function longEdge(): int
    {
        $value = Config::integer('capture.photo.long_edge');

        if ($value < 1) {
            throw new ImageException('Config [capture.photo.long_edge] must be at least 1 pixel.');
        }

        return $value;
    }

    /**
     * @return int<1, 100>
     */
    private function quality(): int
    {
        $value = Config::integer('capture.photo.quality');

        if ($value < 1 || $value > 100) {
            throw new ImageException('Config [capture.photo.quality] must be between 1 and 100.');
        }

        return $value;
    }
}
