<?php

use App\Actions\Vehicles\SeedVehicleIntervals;
use App\Enums\GaugeStatus;
use App\Enums\IntervalSource;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInterval;
use App\Support\VehicleGauges;
use Database\Seeders\ServiceTypeSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(ServiceTypeSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('adding a vehicle seeds its whole schedule', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
    ])->assertSessionHasNoErrors();

    $vehicle = Vehicle::firstOrFail();

    // Every catalogue item that can actually come due gets a row.
    $schedulable = ServiceType::where(fn ($q) => $q
        ->whereNotNull('default_interval_months')
        ->orWhereNotNull('default_interval_miles'))->count();

    expect($vehicle->intervals()->count())->toBe(ServiceType::count())
        ->and($vehicle->intervals()->where('is_active', true)->count())->toBe($schedulable);
});

test('a freshly added vehicle is uncalibrated, not overdue', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
    ]);

    $vehicle = Vehicle::firstOrFail();
    $gauges = VehicleGauges::for($vehicle);

    expect($gauges->worst())->toBeNull()
        ->and($gauges->needingAttention())->toHaveCount(0)
        ->and($gauges->uncalibratedCount())->toBeGreaterThan(0);
});

test('adding a vehicle lands on it with the whole gauge wall showing', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
    ])->assertRedirect(route('vehicles.show', Vehicle::firstOrFail()));

    $this->get(route('vehicles.show', Vehicle::firstOrFail()))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehicles/Show')
            // Not a subset: the point of landing here is seeing everything the
            // car has, with the pinned three drawn large.
            ->has('gauges', ServiceType::whereNotNull('default_interval_months')
                ->orWhereNotNull('default_interval_miles')
                ->count()));
});

test('the pinned three are the named ones, not the first three in the catalogue', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();
    app(SeedVehicleIntervals::class)->handle($vehicle);

    $pinned = $vehicle->intervals()
        ->where('is_pinned', true)
        ->with('serviceType')
        ->get()
        ->pluck('serviceType.key');

    expect($pinned->sort()->values()->all())
        ->toBe(['brake-pads', 'coolant', 'oil-change']);
});

test('every gauge on a new vehicle starts unset', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();
    app(SeedVehicleIntervals::class)->handle($vehicle);

    // Nothing is guessed at setup, so the wall opens as grey rings and the owner
    // sets each one by tapping it.
    expect($vehicle->intervals()->whereNotNull('last_done_at')->count())->toBe(0)
        ->and($vehicle->intervals()->whereNotNull('last_done_odometer')->count())->toBe(0);
});

test('changing an interval marks it as a user override', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();
    $interval = VehicleInterval::factory()->for($vehicle)
        ->for(ServiceType::factory())
        ->create(['interval_miles' => 5000, 'source' => IntervalSource::Default]);

    $this->patch(route('vehicle-intervals.update', $interval), [
        'interval_miles' => 7500,
    ])->assertSessionHasNoErrors();

    $interval->refresh();

    // The override flag is what stops a later default refresh overwriting it.
    expect($interval->interval_miles)->toBe(7500)
        ->and($interval->source)->toBe(IntervalSource::UserOverride);
});

test('setting only a last done date does not mark an override', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();
    $interval = VehicleInterval::factory()->for($vehicle)
        ->for(ServiceType::factory())
        ->create(['source' => IntervalSource::Default]);

    $this->patch(route('vehicle-intervals.update', $interval), [
        'last_done_at' => now()->subMonths(2)->toDateString(),
        'last_done_odometer' => 30_000,
    ]);

    expect($interval->refresh()->source)->toBe(IntervalSource::Default);
});

test('resubmitting the same interval does not mark an override', function () {
    // The edit dialog no longer hides the interval fields behind a toggle, so
    // every save now posts them whether or not they were touched. Only a real
    // change may promote the row to a user override — otherwise setting a
    // last-done date would quietly opt the gauge out of future default refreshes.
    $vehicle = Vehicle::factory()->for($this->user)->create();
    $interval = VehicleInterval::factory()->for($vehicle)
        ->for(ServiceType::factory())
        ->create([
            'interval_months' => 60,
            'interval_miles' => 60_000,
            'source' => IntervalSource::Default,
        ]);

    $this->patch(route('vehicle-intervals.update', $interval), [
        'last_done_at' => now()->subMonths(2)->toDateString(),
        // 5 years and no months, as the split fields recombine it.
        'interval_months' => 60,
        'interval_miles' => 60_000,
    ]);

    expect($interval->refresh()->source)->toBe(IntervalSource::Default)
        ->and($interval->interval_months)->toBe(60);
});

test('a user cannot change an interval on another user vehicle', function () {
    $interval = VehicleInterval::factory()->for(Vehicle::factory())
        ->for(ServiceType::factory())->create();

    $this->patch(route('vehicle-intervals.update', $interval), [
        'interval_miles' => 9999,
    ])->assertForbidden();
});

test('logging a service visit calibrates that gauge', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();
    app(SeedVehicleIntervals::class)->handle($vehicle);

    $oil = ServiceType::where('key', 'oil-change')->firstOrFail();

    $this->post(route('vehicles.events.store', $vehicle), [
        'type' => 'visit',
        'odometer' => 50_000,
        'occurred_on' => now()->toDateString(),
        'line_items' => [['service_type_id' => $oil->id]],
    ])->assertSessionHasNoErrors();

    $gauge = VehicleGauges::for($vehicle->fresh())
        ->gauges
        ->firstWhere(fn ($g) => $g->interval->service_type_id === $oil->id);

    expect($gauge->status)->not->toBe(GaugeStatus::Uncalibrated)
        ->and($gauge->interval->last_done_odometer)->toBe(50_000);
});

test('a gauge can be set and cleared from the edit modal endpoint', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();
    $interval = VehicleInterval::factory()->for($vehicle)
        ->for(ServiceType::factory())->create();

    $this->patch(route('vehicle-intervals.update', $interval), [
        'last_done_at' => now()->subMonths(4)->toDateString(),
        'last_done_odometer' => 38_000,
    ])->assertSessionHasNoErrors();

    expect($interval->refresh()->last_done_odometer)->toBe(38_000);

    // Clearing the date sends an empty string, which the global
    // ConvertEmptyStringsToNull middleware turns into a real null.
    $this->patch(route('vehicle-intervals.update', $interval), [
        'last_done_at' => '',
        'last_done_odometer' => '',
    ])->assertSessionHasNoErrors();

    $interval->refresh();

    expect($interval->last_done_at)->toBeNull()
        ->and($interval->last_done_odometer)->toBeNull();
});

test('a future last done date is rejected', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();
    $interval = VehicleInterval::factory()->for($vehicle)
        ->for(ServiceType::factory())->create();

    $this->patch(route('vehicle-intervals.update', $interval), [
        'last_done_at' => now()->addWeek()->toDateString(),
    ])->assertSessionHasErrors('last_done_at');
});
