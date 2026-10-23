<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/*
 * The fixtures are real half-second clips, not UploadedFile::fake() stand-ins:
 * the request sniffs content, and an empty fake would sniff as nothing.
 */
function clip(string $name = 'clip.m4a'): UploadedFile
{
    $path = base_path("tests/Fixtures/{$name}");

    return new UploadedFile($path, $name, null, null, true);
}

function transcribe(array $payload)
{
    return test()->post(
        route('vehicles.captures.transcriptions.store', test()->vehicle),
        $payload,
        ['Accept' => 'application/json'],
    );
}

beforeEach(function () {
    config([
        'services.anthropic.key' => 'test-key',
        'services.openai.key' => 'openai-key',
        'services.openai.transcription_model' => 'gpt-4o-mini-transcribe',
        'capture.allowed_emails' => ['driver@example.com'],
        'capture.daily_limit' => 40,
    ]);

    Http::preventStrayRequests();

    $this->user = User::factory()->create(['email' => 'driver@example.com']);
    $this->actingAs($this->user);
    $this->vehicle = Vehicle::factory()->for($this->user)->create();
});

test('a clip comes back as the words said in it', function () {
    Http::fake(['api.openai.com/*' => Http::response(['text' => ' 62,410 '])]);

    transcribe(['audio' => clip()])
        ->assertOk()
        ->assertExactJson(['transcript' => '62,410']);

    Http::assertSent(function (Request $request) {
        $parts = collect($request->data())->keyBy('name');

        return $request->url() === 'https://api.openai.com/v1/audio/transcriptions'
            && $request->hasHeader('Authorization', 'Bearer openai-key')
            && $parts['model']['contents'] === 'gpt-4o-mini-transcribe'
            && $parts['language']['contents'] === 'en'
            && $parts['file']['filename'] === 'clip.m4a';
    });
});

test('a webm clip from chrome is sent as webm', function () {
    Http::fake(['api.openai.com/*' => Http::response(['text' => '12.5 gallons'])]);

    transcribe(['audio' => clip('clip.webm')])->assertOk();

    Http::assertSent(fn (Request $request) => collect($request->data())
        ->firstWhere('name', 'file')['filename'] === 'clip.webm');
});

test('only the owner can have a clip heard for a vehicle', function () {
    $this->actingAs(User::factory()->create(['email' => 'driver2@example.com']));
    config(['capture.allowed_emails' => ['driver@example.com', 'driver2@example.com']]);

    transcribe(['audio' => clip()])->assertForbidden();
});

test('guests are turned away', function () {
    auth()->logout();

    transcribe(['audio' => clip()])->assertUnauthorized();
});

test('voice is off for anyone outside the allowlist', function () {
    config(['capture.allowed_emails' => ['someone@example.com']]);

    transcribe(['audio' => clip()])->assertForbidden();
});

test('voice is off without a transcription key, even with photo reads on', function () {
    config(['services.openai.key' => null]);

    transcribe(['audio' => clip()])->assertForbidden();

    $this->get(route('garage'))->assertInertia(fn ($page) => $page
        ->where('can.assistedCapture', true)
        ->where('can.voiceCapture', false));
});

test('a recording is required, and has to be audio', function () {
    transcribe([])->assertUnprocessable()->assertJsonValidationErrors('audio');

    transcribe(['audio' => UploadedFile::fake()->image('dash.jpg')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['audio' => 'could not be played']);
});

test('an overlong recording is refused', function () {
    config(['capture.audio.max_kilobytes' => 1]);

    transcribe(['audio' => clip()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['audio' => 'Say just the numbers']);
});

test('a transcriber that fails, times out or hears nothing means typing instead', function (Closure $answer) {
    Http::fake(['api.openai.com/*' => $answer]);

    transcribe(['audio' => clip()])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'Could not hear that just now. Type it in instead.']);
})->with([
    'server error' => fn () => fn () => Http::response(['error' => 'boom'], 500),
    'timeout' => fn () => fn () => throw new ConnectionException('timed out'),
    'silence' => fn () => fn () => Http::response(['text' => '  ']),
]);

test('voice has its own daily allowance, apart from photo reads', function () {
    config(['capture.daily_limit' => 1]);
    Http::fake(['api.openai.com/*' => Http::response(['text' => '62410'])]);

    transcribe(['audio' => clip()])->assertOk();
    transcribe(['audio' => clip()])
        ->assertTooManyRequests()
        ->assertJson(['message' => "That's today's voice allowance. Type it in instead."]);

    // The photo/sentence allowance is untouched: this one fails validation,
    // not the limiter.
    $this->postJson(route('vehicles.captures.store', [$this->vehicle, 'fuel']), [])
        ->assertUnprocessable();
});
