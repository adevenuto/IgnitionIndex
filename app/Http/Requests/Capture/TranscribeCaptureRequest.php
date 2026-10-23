<?php

namespace App\Http\Requests\Capture;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * A short clip recorded on the phone, to be turned into words.
 *
 * The mimetypes rule sniffs content rather than trusting the name, and iOS
 * clips sniff as video/mp4 as often as audio/mp4, hence both families.
 */
class TranscribeCaptureRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'mimetypes:audio/mp4,audio/x-m4a,audio/aac,video/mp4,audio/webm,video/webm,audio/ogg,audio/mpeg,audio/wav,audio/x-wav',
                'max:'.Config::integer('capture.audio.max_kilobytes'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.required' => 'Nothing was recorded. Try again, or type it.',
            'audio.file' => 'Nothing was recorded. Try again, or type it.',
            'audio.mimetypes' => 'That recording could not be played. Type it in instead.',
            'audio.max' => 'That was a long recording. Say just the numbers.',
        ];
    }
}
