<?php

use App\Enums\EventType;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('the garage lists only the signed-in user vehicles', function () {
    Vehicle::factory()->for($this->user)->create(['make' => 'Toyota', 'model' => 'RAV4']);
    Vehicle::factory()->create(['make' => 'Someone', 'model' => 'Else']);

    $this->get(route('garage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Garage')
            ->has('vehicles', 1)
            ->where('vehicles.0.make', 'Toyota'));
});

test('adding a vehicle records its starting odometer as an event', function () {
    $response = $this->post(route('vehicles.store'), [
        'nickname' => 'Daily',
        'make' => 'Toyota',
        'model' => 'RAV4',
        'year' => 2019,
        'odometer' => 47_320,
    ]);

    $vehicle = Vehicle::firstOrFail();

    // Straight to the car itself, gauge wall and all.
    $response->assertRedirect(route('vehicles.show', $vehicle));

    expect($vehicle->user_id)->toBe($this->user->id)
        ->and($vehicle->last_odometer)->toBe(47_320)
        ->and($vehicle->events()->count())->toBe(1)
        ->and($vehicle->events()->first()->type)->toBe(EventType::Odometer);
});

test('the odometer is required when adding a vehicle', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota',
        'model' => 'RAV4',
    ])->assertSessionHasErrors('odometer');

    expect(Vehicle::count())->toBe(0);
});

test('an invalid vin is rejected', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota',
        'model' => 'RAV4',
        'odometer' => 1000,
        'vin' => 'IOQ00000000000000',
    ])->assertSessionHasErrors('vin');
});

test('a vin is stored uppercased', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota',
        'model' => 'RAV4',
        'odometer' => 1000,
        'vin' => '4t1bf1fk5cu123456',
    ]);

    expect(Vehicle::firstOrFail()->vin)->toBe('4T1BF1FK5CU123456');
});

test('a user cannot view another user vehicle', function () {
    $other = Vehicle::factory()->create();

    $this->get(route('vehicles.show', $other))->assertForbidden();
});

test('a user cannot delete another user vehicle', function () {
    $other = Vehicle::factory()->create();

    $this->delete(route('vehicles.destroy', $other))->assertForbidden();

    expect(Vehicle::whereKey($other->id)->exists())->toBeTrue();
});

test('a user can delete their own vehicle', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $this->delete(route('vehicles.destroy', $vehicle))->assertRedirect(route('garage'));

    expect(Vehicle::whereKey($vehicle->id)->exists())->toBeFalse();
});

test('the edit page names the vehicle for the breadcrumb and the remove dialog', function () {
    // Both read vehicle.name rather than reassembling one from the fields, so
    // the payload has to carry it. Without this the breadcrumb silently falls
    // back to "Vehicle" on a car that has a perfectly good name.
    $vehicle = Vehicle::factory()->for($this->user)->create([
        'nickname' => null, 'year' => 2019, 'make' => 'Toyota', 'model' => 'RAV4',
    ]);

    $this->get(route('vehicles.edit', $vehicle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vehicles/Edit')
            ->where('vehicle.name', '2019 Toyota RAV4'));
});

test('removing a vehicle takes its schedule and history with it', function () {
    // The confirm dialog on the edit page promises entries and gauges go too.
    // Both hang off cascading foreign keys, so nothing in the controller would
    // fail if that stopped being true.
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000,
    ])->assertSessionHasNoErrors();

    $vehicle = Vehicle::firstOrFail();

    expect($vehicle->intervals()->count())->toBeGreaterThan(0)
        ->and($vehicle->events()->count())->toBeGreaterThan(0);

    $this->delete(route('vehicles.destroy', $vehicle));

    expect(DB::table('vehicle_intervals')->where('vehicle_id', $vehicle->id)->count())->toBe(0)
        ->and(DB::table('events')->where('vehicle_id', $vehicle->id)->count())->toBe(0);
});

test('history lists events newest first', function () {
    // The vehicle page used to carry this list. It moved to History when the
    // detail screen was rebuilt to the design, which ends at the gauge wall —
    // but the ordering still matters, so the assertion moved with it.
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $this->post(route('vehicles.events.store', $vehicle), [
        'type' => 'odometer', 'odometer' => 10_000, 'occurred_on' => '2026-01-01',
    ]);
    $this->post(route('vehicles.events.store', $vehicle), [
        'type' => 'odometer', 'odometer' => 12_000, 'occurred_on' => '2026-06-01',
    ]);

    $this->get(route('history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('History')
            ->has('events.data', 2)
            ->where('events.data.0.odometer', 12_000)
            ->where('events.data.1.odometer', 10_000));
});

test('the vehicle page no longer carries the event list', function () {
    // The detail screen ends at the gauges, so the events query — which eager
    // loaded line items and their service types — would be pure cost.
    $vehicle = Vehicle::factory()->for($this->user)->create();

    $this->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('events'));
});

test('a blank year is stored as null rather than rejected', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota',
        'model' => 'RAV4',
        'year' => '',
        'odometer' => 1000,
    ])->assertSessionHasNoErrors();

    expect(Vehicle::firstOrFail()->year)->toBeNull();
});

test('a paint colour is stored and normalised to lowercase', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota',
        'model' => 'RAV4',
        'odometer' => 1000,
        'color' => '#1F4E9C',
    ])->assertSessionHasNoErrors();

    expect(Vehicle::firstOrFail()->color)->toBe('#1f4e9c');
});

test('an invalid colour is rejected', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota',
        'model' => 'RAV4',
        'odometer' => 1000,
        'color' => 'blue',
    ])->assertSessionHasErrors('color');
});

test('a vehicle can be saved without a colour', function () {
    $this->post(route('vehicles.store'), [
        'make' => 'Toyota',
        'model' => 'RAV4',
        'odometer' => 1000,
        'color' => '',
    ])->assertSessionHasNoErrors();

    expect(Vehicle::firstOrFail()->color)->toBeNull();
});

test('the garage exposes each vehicle colour for its dot', function () {
    Vehicle::factory()->for($this->user)->create(['color' => '#b31d26']);

    $this->get(route('garage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('vehicles.0.color', '#b31d26'));
});
