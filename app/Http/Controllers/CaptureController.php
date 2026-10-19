<?php

namespace App\Http\Controllers;

use App\Actions\Capture\PrepareCapturePhoto;
use App\Actions\Capture\ReadCapture;
use App\Enums\CaptureLane;
use App\Http\Requests\Capture\ReadCaptureRequest;
use App\Models\Vehicle;
use App\Support\Capture\CaptureFailed;
use App\Support\Capture\CaptureInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Image\ImageException;

/**
 * Reads a photo or a sentence into proposed values for one quick-add lane.
 *
 * JSON rather than an Inertia response: this is a question the open sheet asks
 * mid-entry, and its answer fills fields in place rather than changing page.
 * Ownership, the capture gate and the daily allowance are all route middleware,
 * so by the time this runs the only thing left to decide is the reading.
 *
 * Synchronous on purpose. The person is holding the phone, waiting, and a
 * queued read would sit in the jobs table with no worker to run it.
 */
class CaptureController extends Controller
{
    public function __invoke(
        ReadCaptureRequest $request,
        Vehicle $vehicle,
        CaptureLane $lane,
        PrepareCapturePhoto $preparePhoto,
        ReadCapture $readCapture,
    ): JsonResponse {
        $image = $request->file('image');

        try {
            $input = $image instanceof UploadedFile
                ? CaptureInput::photo($preparePhoto->handle($image))
                : CaptureInput::speech($request->string('transcript')->trim()->value());

            return response()->json($readCapture->handle($vehicle, $lane, $input));
        } catch (ImageException) {
            return response()->json(['message' => 'That photo could not be opened. Try another.'], 422);
        } catch (CaptureFailed) {
            return response()->json(['message' => 'Could not read that just now. Type it in instead.'], 503);
        }
    }
}
