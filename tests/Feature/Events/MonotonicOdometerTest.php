<?php

use App\Models\Event;
use App\Models\Vehicle;
use App\Rules\MonotonicOdometer;
use Illuminate\Support\Facades\Validator;

function validateOdometer(Vehicle $vehicle, int $odometer, string $on, bool $confirmed = false): array
{
    $validator = Validator::make(
        ['odometer' => $odometer],
        ['odometer' => [new MonotonicOdometer($vehicle, $on, $confirmed)]],
    );

    return $validator->errors()->get('odometer');
}

beforeEach(function () {
    $this->vehicle = Vehicle::factory()->create();
});

test('a reading lower than the previous one is rejected', function () {
    Event::factory()->for($this->vehicle)->on('2026-08-01', 50_000)->create();

    $errors = validateOdometer($this->vehicle, 49_000, '2026-09-01');

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('lower than the 50,000 mi');
});

test('a reading equal to the previous one is allowed', function () {
    Event::factory()->for($this->vehicle)->on('2026-08-01', 50_000)->create();

    expect(validateOdometer($this->vehicle, 50_000, '2026-09-01'))->toBeEmpty();
});

test('a backfilled reading between two existing readings is allowed', function () {
    Event::factory()->for($this->vehicle)->on('2025-01-01', 30_000)->create();
    Event::factory()->for($this->vehicle)->on('2026-08-01', 50_000)->create();

    expect(validateOdometer($this->vehicle, 40_000, '2025-09-01'))->toBeEmpty();
});

test('a backfilled reading higher than a later one is rejected', function () {
    Event::factory()->for($this->vehicle)->on('2026-08-01', 50_000)->create();

    $errors = validateOdometer($this->vehicle, 60_000, '2025-01-01');

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('higher than the 50,000 mi');
});

test('an implausible jump asks for confirmation rather than being rejected outright', function () {
    Event::factory()->for($this->vehicle)->on('2026-08-01', 50_000)->create();

    // 20,000 miles in 10 days.
    $errors = validateOdometer($this->vehicle, 70_000, '2026-08-11');

    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('Confirm the reading');

    // The same reading passes once the user confirms it.
    expect(validateOdometer($this->vehicle, 70_000, '2026-08-11', confirmed: true))->toBeEmpty();
});

test('an ordinary jump is not questioned', function () {
    Event::factory()->for($this->vehicle)->on('2026-08-01', 50_000)->create();

    expect(validateOdometer($this->vehicle, 51_200, '2026-09-01'))->toBeEmpty();
});

test('the first ever reading is always accepted', function () {
    expect(validateOdometer($this->vehicle, 123_456, '2026-09-01'))->toBeEmpty();
});

test('a second reading on the same day may be higher', function () {
    Event::factory()->for($this->vehicle)->on('2026-09-01', 15_380)->create();

    expect(validateOdometer($this->vehicle, 15_420, '2026-09-01'))->toBeEmpty();
});

test('a second reading on the same day may be lower', function () {
    Event::factory()->for($this->vehicle)->on('2026-09-01', 15_380)->create();

    expect(validateOdometer($this->vehicle, 15_350, '2026-09-01'))->toBeEmpty();
});

test('a same day reading is still bounded by the days either side', function () {
    Event::factory()->for($this->vehicle)->on('2026-08-01', 15_000)->create();
    Event::factory()->for($this->vehicle)->on('2026-09-01', 15_380)->create();
    Event::factory()->for($this->vehicle)->on('2026-10-01', 16_000)->create();

    expect(validateOdometer($this->vehicle, 14_900, '2026-09-01'))->not->toBeEmpty()
        ->and(validateOdometer($this->vehicle, 16_100, '2026-09-01'))->not->toBeEmpty();
});
