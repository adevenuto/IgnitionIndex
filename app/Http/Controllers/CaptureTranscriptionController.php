<?php

namespace App\Http\Controllers;

use App\Http\Requests\Capture\TranscribeCaptureRequest;
use App\Models\Vehicle;
use App\Support\Capture\CaptureFailed;
use App\Support\Capture\Transcriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Turns a clip said into the sheet into words, and nothing more.
 *
 * What the words mean is decided afterwards, the same way as before: on the
 * phone when the numbers are unambiguous, through CaptureController when not.
 * Keeping the two apart means a plain "62,410" never costs a reader call.
 *
 * The clip is never stored; the temporary upload is the only copy, and it is
 * gone when the request ends. The vehicle is only here for authorization.
 */
class CaptureTranscriptionController extends Controller
{
    public function __invoke(TranscribeCaptureRequest $request, Vehicle $vehicle, Transcriber $transcriber): JsonResponse
    {
        /** @var UploadedFile $audio */
        $audio = $request->file('audio');

        try {
            return response()->json([
                'transcript' => $transcriber->transcribe(
                    $audio->getRealPath(),
                    (string) $audio->getMimeType(),
                ),
            ]);
        } catch (CaptureFailed) {
            return response()->json(['message' => 'Could not hear that just now. Type it in instead.'], 503);
        }
    }
}
