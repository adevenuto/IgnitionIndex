<?php

namespace App\Support\Capture;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Illuminate\Support\Facades\Log;

/**
 * Reads through Claude, with the schema enforced by structured outputs so the
 * answer is always parseable JSON of the right shape.
 *
 * Effort is low: this is transcription, not reasoning, and the person is
 * waiting. Server-side fallback is on, so a policy decline on the primary
 * model is retried on another inside the same call rather than failing.
 */
class ClaudeCaptureReader implements CaptureReader
{
    public function __construct(
        private Client $client,
        private string $model,
        private float $timeout,
    ) {}

    public function read(string $instructions, array $schema, CaptureInput $input): array
    {
        $content = [];

        if ($input->jpeg !== null) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'image/jpeg',
                    'data' => base64_encode($input->jpeg),
                ],
            ];
            $content[] = ['type' => 'text', 'text' => 'Read this photo.'];
        } else {
            $content[] = [
                'type' => 'text',
                'text' => "The person said:\n<speech>{$input->transcript}</speech>",
            ];
        }

        try {
            $message = $this->client->beta->messages->create(
                maxTokens: 4096,
                messages: [['role' => 'user', 'content' => $content]],
                model: $this->model,
                system: $instructions,
                outputConfig: [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
                requestOptions: ['timeout' => $this->timeout, 'maxRetries' => 1],
            );
        } catch (APIException $exception) {
            Log::warning('Capture read failed', ['error' => $exception->getMessage()]);

            throw new CaptureFailed('The reader could not be reached.', previous: $exception);
        }

        if ($message->stopReason !== 'end_turn') {
            Log::warning('Capture read stopped early', ['stop_reason' => $message->stopReason]);

            throw new CaptureFailed("The reader stopped early ({$message->stopReason}).");
        }

        foreach ($message->content as $block) {
            if ($block instanceof BetaTextBlock) {
                $decoded = json_decode($block->text, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        throw new CaptureFailed('The reader answered without a reading.');
    }
}
