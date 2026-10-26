<?php

use App\Models\User;
use App\Models\Vehicle;

/*
 * The shell's Log button sends a person with one car straight to it, so the
 * shared garage summary names that car — and only when there is exactly one.
 */
test('the lone vehicle is shared so Log can go straight to it', function () {
    $user = User::factory()->create();
    $vehicle = Vehicle::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('history'))
        ->assertInertia(fn ($page) => $page
            ->where('garage.vehicle_count', 1)
            ->where('garage.only_vehicle_id', $vehicle->id));
});

test('with several vehicles there is no lone one to go to', function () {
    $user = User::factory()->create();
    Vehicle::factory()->for($user)->count(2)->create();

    $this->actingAs($user)
        ->get(route('history'))
        ->assertInertia(fn ($page) => $page
            ->where('garage.vehicle_count', 2)
            ->where('garage.only_vehicle_id', null));
});

test('with no vehicles there is none either', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('history'))
        ->assertInertia(fn ($page) => $page
            ->where('garage.vehicle_count', 0)
            ->where('garage.only_vehicle_id', null));
});
