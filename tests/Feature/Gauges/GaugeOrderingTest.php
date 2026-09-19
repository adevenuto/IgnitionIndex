<?php

use App\Actions\Vehicles\SeedVehicleIntervals;
use App\Enums\GaugeStatus;
use App\Models\ServiceType;
use App\Models\Vehicle;
use App\Models\VehicleInterval;
use App\Support\VehicleGauges;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Support\Carbon;

/*
 * Design doc §6: the gauge wall has a FIXED order and a pinned subset.
 *
 * The order used to be by urgency, which meant a gauge moved position as its
 * status changed — under the pointer, on a wall people are meant to learn the
 * shape of. These lock the new behaviour in.
 */

test('gauges keep their position order rather than sorting by urgency', function () {
    $vehicle = Vehicle::factory()->create([
        'last_odometer' => 50_000,
        'last_odometer_at' => Carbon::today()->toDateString(),
    ]);

    // Position 0 is healthy, position 1 is long overdue.
    VehicleInterval::factory()->for($vehicle)->for(ServiceType::factory())->create([
        'position' => 0,
        'interval_miles' => 100_000,
        'interval_months' => null,
        'last_done_at' => Carbon::today()->toDateString(),
        'last_done_odometer' => 49_000,
    ]);
    VehicleInterval::factory()->for($vehicle)->for(ServiceType::factory())->create([
        'position' => 1,
        'interval_miles' => 1_000,
        'interval_months' => null,
        'last_done_at' => Carbon::today()->subYear()->toDateString(),
        'last_done_odometer' => 10_000,
    ]);

    $gauges = VehicleGauges::for($vehicle->fresh())->toArray();

    expect($gauges[0]['position'])->toBe(0)
        ->and($gauges[0]['display_status'])->toBe('ok')
        ->and($gauges[1]['position'])->toBe(1)
        ->and($gauges[1]['display_status'])->toBe('overdue');
});

test('seeding a vehicle pins exactly three active intervals', function () {
    // Named explicitly rather than leaning on the catalogue migration: the
    // pinned set is chosen by key now, and factory types carry random keys and
    // would pin nothing at all.
    $this->seed(ServiceTypeSeeder::class);

    $vehicle = Vehicle::factory()->create();

    app(SeedVehicleIntervals::class)->handle($vehicle);

    $intervals = $vehicle->fresh()->intervals;

    expect($intervals->where('is_pinned', true))->toHaveCount(3)
        ->and($intervals->where('is_pinned', true)->every(
            fn (VehicleInterval $i): bool => $i->is_active,
        ))->toBeTrue()
        ->and($intervals->pluck('position')->unique())->toHaveCount($intervals->count());
});

test('re-seeding does not collide with positions already in use', function () {
    ServiceType::factory()->count(6)->create();

    $vehicle = Vehicle::factory()->create();

    app(SeedVehicleIntervals::class)->handle($vehicle);
    $vehicle->fresh()->intervals()->limit(3)->delete();
    app(SeedVehicleIntervals::class)->handle($vehicle->fresh());

    $positions = $vehicle->fresh()->intervals->pluck('position');

    expect($positions->unique())->toHaveCount($positions->count());
});

test('every status maps to one of the three the interface draws', function () {
    expect(GaugeStatus::Uncalibrated->display())->toBe('unknown')
        ->and(GaugeStatus::Healthy->display())->toBe('ok')
        ->and(GaugeStatus::Soon->display())->toBe('due')
        ->and(GaugeStatus::Due->display())->toBe('overdue')
        ->and(GaugeStatus::Overdue->display())->toBe('overdue');
});
