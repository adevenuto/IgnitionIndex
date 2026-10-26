<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Capture\CaptureFailed;
use App\Support\Capture\CaptureInput;
use App\Support\Capture\CaptureReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;

/**
 * Stands in for the paid reader: answers with whatever the test sets, and
 * remembers what it was asked so the request it would have sent can be checked.
 */
class FakeCaptureReader implements CaptureReader
{
    /** @var array<string, mixed>|CaptureFailed */
    public array|CaptureFailed $answer = [];

    /** @var list<array{instructions: string, schema: array<string, mixed>, input: CaptureInput}> */
    public array $calls = [];

    public function read(string $instructions, array $schema, CaptureInput $input): array
    {
        $this->calls[] = compact('instructions', 'schema', 'input');

        if ($this->answer instanceof CaptureFailed) {
            throw $this->answer;
        }

        return $this->answer;
    }
}

/** A complete fuel answer, every field null unless overridden. */
function fuelAnswer(array $overrides = []): array
{
    return [
        'odometer' => null,
        'gallons' => null,
        'total_cost' => null,
        'price_per_gallon' => null,
        'occurred_on' => null,
        'note' => null,
        ...$overrides,
    ];
}

beforeEach(function () {
    Date::setTestNow('2026-10-18');

    config([
        'services.anthropic.key' => 'test-key',
        'capture.allowed_emails' => ['driver@example.com'],
        'capture.daily_limit' => 40,
    ]);

    $this->reader = new FakeCaptureReader;
    $this->app->instance(CaptureReader::class, $this->reader);

    $this->user = User::factory()->create(['email' => 'driver@example.com']);
    $this->actingAs($this->user);

    $this->vehicle = Vehicle::factory()->for($this->user)->create();
    Event::factory()->for($this->vehicle)->on('2026-10-01', 62_000)->create();
    $this->vehicle->forceFill(['last_odometer' => 62_000, 'last_odometer_at' => '2026-10-01'])->save();
});

function capture(array $payload, string $lane = 'fuel')
{
    return test()->postJson(route('vehicles.captures.store', [test()->vehicle, $lane]), $payload);
}

test('a sentence is read into the fuel fields', function () {
    $this->reader->answer = fuelAnswer(['gallons' => 12.5, 'total_cost' => 48.12]);

    capture(['transcript' => 'twelve and a half gallons, forty eight twelve'])
        ->assertOk()
        ->assertExactJson([
            'fields' => [
                'odometer' => null,
                'gallons' => 12.5,
                'total_cost' => 48.12,
                'price_per_gallon' => null,
                'occurred_on' => null,
            ],
            'flags' => [],
            'note' => null,
        ]);

    expect($this->reader->calls[0]['input']->transcript)
        ->toBe('twelve and a half gallons, forty eight twelve');
});

test('a photo is re-encoded as jpeg before it is read, and nothing is saved', function () {
    $this->reader->answer = fuelAnswer(['gallons' => 11.2, 'total_cost' => 40.0, 'price_per_gallon' => 3.571]);

    capture(['image' => UploadedFile::fake()->image('pump.png', 3000, 2000)])->assertOk();

    $jpeg = $this->reader->calls[0]['input']->jpeg;

    expect(substr($jpeg, 0, 3))->toBe("\xFF\xD8\xFF")
        ->and(getimagesizefromstring($jpeg)[0])->toBe(1568)
        ->and(Event::count())->toBe(1);
});

test('every field is required and nullable, so a missing value is an explicit null', function () {
    $this->reader->answer = fuelAnswer();

    capture(['transcript' => '12 gallons'])->assertOk();

    $schema = $this->reader->calls[0]['schema'];

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toEqualCanonicalizing(array_keys($schema['properties']))
        ->and(collect($schema['properties'])->every(fn (array $p): bool => in_array('null', $p['type'], true)))->toBeTrue();
});

test('the reader is told what the car last read, so it can tell an odometer from a trip meter', function () {
    $this->reader->answer = fuelAnswer();

    capture(['transcript' => '62,410'])->assertOk();

    expect($this->reader->calls[0]['instructions'])
        ->toContain('62,000 mi on 2026-10-01')
        ->toContain('Today: 2026-10-18');
});

test('out-of-range and mistyped values come back null rather than wrong', function () {
    $this->reader->answer = fuelAnswer([
        'odometer' => -5,
        'gallons' => 900,
        'total_cost' => '48.12',
        'occurred_on' => '2026-12-25',
    ]);

    capture(['transcript' => 'nonsense'])
        ->assertOk()
        ->assertJsonPath('fields.odometer', null)
        ->assertJsonPath('fields.gallons', null)
        ->assertJsonPath('fields.total_cost', null)
        ->assertJsonPath('fields.occurred_on', null);
});

test('a reading below the last one is flagged, not corrected', function () {
    $this->reader->answer = fuelAnswer(['odometer' => 61_500]);

    capture(['transcript' => '61,500'])
        ->assertOk()
        ->assertJsonPath('fields.odometer', 61_500)
        ->assertJsonPath('flags.0.field', 'odometer')
        ->assertJsonPath('flags.0.message', 'Lower than your last reading of 62,000 mi.');
});

test('a reading far from the estimate asks whether it was a trip meter', function () {
    $this->reader->answer = fuelAnswer(['odometer' => 70_000]);

    capture(['transcript' => '70,000'])
        ->assertJsonPath('flags.0.field', 'odometer')
        ->assertJsonPath('flags.0.message', fn (string $message) => str_contains($message, 'trip meter'));
});

test('pump numbers that do not add up are flagged', function () {
    $this->reader->answer = fuelAnswer(['gallons' => 12.5, 'total_cost' => 52.0, 'price_per_gallon' => 3.859]);

    capture(['transcript' => 'pump'])
        ->assertJsonPath('flags.0.field', 'total_cost')
        ->assertJsonPath('flags.0.message', 'Gallons × $3.859 comes to $48.24, not $52.00.');
});

test('an implausible price per gallon is flagged', function () {
    $this->reader->answer = fuelAnswer(['gallons' => 1.25, 'total_cost' => 48.12]);

    capture(['transcript' => 'pump'])
        ->assertJsonPath('flags.0.message', 'That works out to $38.50 a gallon.');
});

test('gallons no tank could take, or too few for a fill-up, are flagged', function (float $gallons, string $message) {
    $this->reader->answer = fuelAnswer(['gallons' => $gallons]);

    capture(['transcript' => 'pump'])
        ->assertJsonPath('flags.0.field', 'gallons')
        ->assertJsonPath('flags.0.message', $message);
})->with([
    'too many' => [125.0, '125 gallons is more than a tank holds.'],
    'too few' => [0.2, '0.2 gallons is too little for a fill-up.'],
]);

test('pump numbers that agree raise nothing', function () {
    $this->reader->answer = fuelAnswer(['odometer' => 62_400, 'gallons' => 12.5, 'total_cost' => 48.24, 'price_per_gallon' => 3.859]);

    capture(['transcript' => 'pump'])->assertJsonPath('flags', []);
});

test('the reader note reaches the person', function () {
    $this->reader->answer = fuelAnswer(['note' => 'Glare covers the gallons.']);

    capture(['transcript' => 'pump'])->assertJsonPath('note', 'Glare covers the gallons.');
});

test('a failed read says to type it instead', function () {
    $this->reader->answer = new CaptureFailed('down');

    capture(['transcript' => '12 gallons'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Could not read that just now. Type it in instead.');
});

test('it needs a photo or words, and not both', function () {
    capture([])->assertUnprocessable()->assertJsonValidationErrors('image');

    capture([
        'transcript' => '12 gallons',
        'image' => UploadedFile::fake()->image('pump.jpg'),
    ])->assertUnprocessable()->assertJsonValidationErrors('image');

    expect($this->reader->calls)->toBeEmpty();
});

test('a file that is not a jpeg or png is refused', function () {
    capture(['image' => UploadedFile::fake()->create('pump.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

test('a lane capture does not read yet is not found', function () {
    capture(['transcript' => '12 gallons'], 'visit')->assertNotFound();
});

test('someone not on the allowlist cannot capture', function () {
    $this->user->forceFill(['email' => 'someone@example.com'])->save();

    capture(['transcript' => '12 gallons'])->assertForbidden();

    expect($this->reader->calls)->toBeEmpty();
});

test('capture is off for everyone without an api key', function () {
    config(['services.anthropic.key' => null]);

    capture(['transcript' => '12 gallons'])->assertForbidden();
});

test('only the owner can capture for a vehicle', function () {
    $this->vehicle = Vehicle::factory()->create();

    capture(['transcript' => '12 gallons'])->assertForbidden();

    expect($this->reader->calls)->toBeEmpty();
});

test('each person has a daily allowance', function () {
    config(['capture.daily_limit' => 2]);
    $this->reader->answer = fuelAnswer();

    capture(['transcript' => 'one'])->assertOk();
    capture(['transcript' => 'two'])->assertOk();
    capture(['transcript' => 'three'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', "That's today's photo and voice allowance. Type it in instead.");
});

test('pages tell the client whether capture is available', function () {
    $this->get(route('garage'))
        ->assertInertia(fn ($page) => $page->where('can.assistedCapture', true));

    $this->user->forceFill(['email' => 'someone@example.com'])->save();

    $this->get(route('garage'))
        ->assertInertia(fn ($page) => $page->where('can.assistedCapture', false));
});
