<?php

use App\Models\Event;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInterval;
use App\Support\VehicleGauges;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Support\Carbon;

/*
 * The mileage side of a gauge, end to end: calibrated from the edit modal,
 * driven by an ordinary logged entry, and guarded against the reading that
 * silently switched it off — an "odometer then" above the car's own odometer.
 */

beforeEach(function () {
    $this->seed(ServiceTypeSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 15_380,
    ])->assertSessionHasNoErrors();

    $this->vehicle = Vehicle::firstOrFail();
    $this->oil = VehicleInterval::query()
        ->whereBelongsTo($this->vehicle)
        ->whereRelation('serviceType', 'key', 'oil-change')
        ->firstOrFail();
});

function oilGauge(Vehicle $vehicle, VehicleInterval $oil): array
{
    $gauge = VehicleGauges::for($vehicle->fresh())
        ->gauges
        ->firstWhere(fn ($g) => $g->interval->id === $oil->id);

    return VehicleGauges::gaugeToArray($gauge);
}

test('a logged reading moves the mileage side of a calibrated gauge', function () {
    $this->vehicle->events()->firstOrFail()
        ->forceFill(['occurred_on' => Carbon::today()->subDays(3)])->save();

    $this->patch(route('vehicle-intervals.update', $this->oil), [
        'last_done_at' => Carbon::today()->subMonth()->toDateString(),
        'last_done_odometer' => 15_000,
        'interval_miles' => 5_000,
        'interval_months' => 6,
    ])->assertSessionHasNoErrors();

    $this->post(route('vehicles.events.store', $this->vehicle), [
        'type' => 'fuel',
        'odometer' => 19_500,
        'occurred_on' => Carbon::today()->toDateString(),
        'gallons' => 12.4,
        'cost' => '44.99',
        'full_tank' => true,
        'confirm_odometer' => true,
    ])->assertSessionHasNoErrors();

    $gauge = oilGauge($this->vehicle, $this->oil);

    expect($gauge['axis'])->toBe('mileage')
        ->and($gauge['mile_progress'])->toBe(0.9)
        ->and($gauge['time_progress'])->toBeLessThan(0.9)
        ->and($gauge['label'])->toBe('500 mi to go');
});

test('without an odometer then, the mileage side does not count', function () {
    $this->patch(route('vehicle-intervals.update', $this->oil), [
        'last_done_at' => Carbon::today()->subMonths(2)->toDateString(),
        'last_done_odometer' => '',
    ])->assertSessionHasNoErrors();

    $gauge = oilGauge($this->vehicle, $this->oil);

    expect($gauge['axis'])->toBe('time')
        ->and($gauge['mile_progress'])->toBeNull()
        ->and($gauge['time_progress'])->not->toBeNull();
});

test('an odometer then above a later reading is rejected', function () {
    // Done in June at 19,500, while the car read 15,380 when it was added.
    $this->patch(route('vehicle-intervals.update', $this->oil), [
        'last_done_at' => Carbon::today()->subMonths(4)->toDateString(),
        'last_done_odometer' => 19_500,
    ])->assertSessionHasErrors('last_done_odometer');

    expect($this->oil->refresh()->last_done_odometer)->toBeNull();
});

test('an odometer then below a reading taken before that service is rejected', function () {
    Event::factory()->for($this->vehicle)
        ->on(Carbon::today()->subMonths(6)->toDateString(), 14_000)
        ->create();

    $this->patch(route('vehicle-intervals.update', $this->oil), [
        'last_done_at' => Carbon::today()->subMonths(2)->toDateString(),
        'last_done_odometer' => 13_000,
    ])->assertSessionHasErrors('last_done_odometer');
});

test('a service done the same day as a reading may sit below it', function () {
    $this->patch(route('vehicle-intervals.update', $this->oil), [
        'last_done_at' => Carbon::today()->toDateString(),
        'last_done_odometer' => 15_300,
    ])->assertSessionHasNoErrors();
});

test('with no date, an odometer then above the current reading is rejected', function () {
    $this->patch(route('vehicle-intervals.update', $this->oil), [
        'last_done_at' => '',
        'last_done_odometer' => 16_000,
    ])->assertSessionHasErrors('last_done_odometer');
});

test('never done still saves as odometer zero with no date', function () {
    $this->patch(route('vehicle-intervals.update', $this->oil), [
        'last_done_at' => '',
        'last_done_odometer' => 0,
    ])->assertSessionHasNoErrors();

    expect($this->oil->refresh()->last_done_odometer)->toBe(0);
});

test('deleting a reading takes it out of the estimate', function () {
    $added = $this->vehicle->events()->firstOrFail();
    $added->forceFill(['occurred_on' => Carbon::today()->subDays(20)])->save();

    $this->post(route('vehicles.events.store', $this->vehicle), [
        'type' => 'odometer',
        'odometer' => 15_780,
        'occurred_on' => Carbon::today()->subDays(10)->toDateString(),
    ])->assertSessionHasNoErrors();

    $this->post(route('vehicles.events.store', $this->vehicle), [
        'type' => 'odometer',
        'odometer' => 19_500,
        'occurred_on' => Carbon::today()->subDay()->toDateString(),
        'confirm_odometer' => true,
    ])->assertSessionHasNoErrors();

    expect($this->vehicle->fresh()->last_odometer)->toBe(19_500);

    $mistake = $this->vehicle->events()->where('odometer', 19_500)->firstOrFail();
    $this->delete(route('events.destroy', $mistake))->assertRedirect();

    $vehicle = $this->vehicle->fresh();

    expect($vehicle->last_odometer)->toBe(15_780)
        ->and($vehicle->last_odometer_at->toDateString())
        ->toBe(Carbon::today()->subDays(10)->toDateString())
        ->and((float) $vehicle->avg_miles_per_day)->toBe(40.0);
});

test('the last remaining reading cannot be deleted', function () {
    $only = $this->vehicle->events()->firstOrFail();

    $this->delete(route('events.destroy', $only))->assertSessionHasErrors('event');

    expect($this->vehicle->events()->count())->toBe(1)
        ->and($this->vehicle->fresh()->last_odometer)->toBe(15_380);
});
