<?php

namespace App\Http\Requests\Capture;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * A photo or a transcript — exactly one — to read for one lane.
 *
 * The photo rules mirror the vehicle-photo ones (HandlesVehiclePhotos): the
 * mimetypes rule sniffs content, and dimensions opens the bytes, which doubles
 * as "is this genuinely a decodable image" before anything else touches it.
 */
class ReadCaptureRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $dimension = Config::integer('capture.photo.max_dimension');

        return [
            'image' => [
                'required_without:transcript',
                'prohibits:transcript',
                'file',
                'mimetypes:image/jpeg,image/png',
                'max:'.Config::integer('capture.photo.max_kilobytes'),
                "dimensions:max_width={$dimension},max_height={$dimension}",
            ],
            'transcript' => ['required_without:image', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required_without' => 'Take a photo or say the numbers.',
            'image.mimetypes' => 'Photos need to be JPEG or PNG.',
            'image.max' => 'That photo is over 10 MB.',
            'image.dimensions' => 'That photo is bigger than 6000 × 6000 pixels.',
            'transcript.max' => 'That was a lot to take in. Say just the numbers.',
        ];
    }
}
