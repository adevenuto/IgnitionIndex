<?php

namespace App\Support\Capture;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Transcribes through OpenAI's audio endpoint.
 *
 * The clip is recorded on the phone and sent as is — m4a from iOS, webm from
 * Chrome and Android — since the endpoint takes both. The prompt nudges it to
 * write numbers as digits, which is all the local parser and the reader need.
 */
class OpenAiTranscriber implements Transcriber
{
    private const PROMPT = 'Odometer readings, gallons and dollar amounts. Write numbers as digits.';

    public function __construct(
        private string $key,
        private string $model,
        private float $timeout,
    ) {}

    public function transcribe(string $path, string $mimeType): string
    {
        $audio = @file_get_contents($path);

        if ($audio === false || $audio === '') {
            throw new CaptureFailed('The recording could not be read.');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->connectTimeout(5)
                ->withToken($this->key)
                ->acceptJson()
                ->attach('file', $audio, 'clip.'.$this->extension($mimeType), ['Content-Type' => $mimeType])
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $this->model,
                    'language' => 'en',
                    'response_format' => 'json',
                    'prompt' => self::PROMPT,
                ]);
        } catch (Throwable $e) {
            Log::warning('Transcription request failed', ['message' => $e->getMessage()]);

            throw new CaptureFailed('The transcriber could not be reached.', previous: $e);
        }

        if ($response->failed()) {
            Log::warning('Transcription request refused', ['status' => $response->status()]);

            throw new CaptureFailed("The transcriber answered {$response->status()}.");
        }

        $text = $response->json('text');

        if (! is_string($text) || trim($text) === '') {
            throw new CaptureFailed('Nothing was heard in the recording.');
        }

        return trim($text);
    }

    /** The endpoint goes by the file name's extension, not its content type. */
    private function extension(string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'webm') => 'webm',
            str_contains($mimeType, 'mpeg') => 'mp3',
            str_contains($mimeType, 'wav') => 'wav',
            default => 'm4a',
        };
    }
}
