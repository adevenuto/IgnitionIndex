<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\SetUpGaugesNotification;
use App\Support\VehicleGauges;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A seeded schedule opens with every gauge uncalibrated, so nothing on it can
 * come due until the owner supplies a starting point. These hold the two things
 * that tell them so: a notice waiting in the inbox, and the prompt on the car.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function addVehicle(array $overrides = []): void
{
    test()->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 1000, ...$overrides,
    ])->assertSessionHasNoErrors();
}

test('adding the first vehicle leaves a set-up-gauges notice in the inbox', function () {
    Notification::fake();

    addVehicle();

    Notification::assertSentTo($this->user, SetUpGaugesNotification::class);
});

test('every vehicle gets its own notice, and each names its own car', function () {
    addVehicle();
    addVehicle(['make' => 'Honda', 'model' => 'Civic']);

    // This was once first-car-only. Every new vehicle shows the "gauges aren't
    // set" banner on its own page, so skipping the notice for later cars had
    // the two contradicting each other about the same vehicle.
    $notices = $this->user->notifications()->get();

    expect($notices)->toHaveCount(2)
        ->and($notices->pluck('data.vehicle_name')->sort()->values()->all())
        ->toBe(['Honda Civic', 'Toyota RAV4']);

    // Each points at the car it is about, not merely at the garage.
    $ids = Vehicle::pluck('id')->sort()->values();
    expect($notices->pluck('data.vehicle_id')->sort()->values()->all())
        ->toBe($ids->all());
});

test('the nudge stays in the app rather than going out by email', function () {
    $vehicle = Vehicle::factory()->for($this->user)->create();

    expect((new SetUpGaugesNotification($vehicle))->via($this->user))
        ->toBe(['database']);
});

test('the nudge arrives without a queue worker', function () {
    // The suite runs the sync queue, which hides ShouldQueue entirely: the
    // notice appeared in every test and never arrived in the real app, where
    // QUEUE_CONNECTION=database and nothing was draining `jobs`. Pointing the
    // queue at the database here is what tells the two apart.
    config(['queue.default' => 'database']);

    addVehicle();

    // The row is there and readable now, not after a worker runs. `jobs` is
    // deliberately not asserted empty: adding a car also queues the EPA/VIN
    // enrichment, which SHOULD be queued — it is an outbound HTTP call.
    expect($this->user->notifications()->count())->toBe(1);
});

test('the inbox renders the notice with its own title rather than falling back', function () {
    // Not faked: this one goes through the real database channel and out the
    // other side, which is the only way to catch the payload and the inbox
    // disagreeing about how a notice names itself.
    addVehicle(['year' => 2019]);

    $this->get(route('notifications'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications')
            ->where('notifications.data.0.kind', 'gauge_setup')
            // Falls back to "Update" if payload() cannot read a plain title.
            ->where('notifications.data.0.title', 'Welcome to the garage')
            ->where('notifications.data.0.vehicle_name', '2019 Toyota RAV4')
            ->where('unreadCount', 1));
});

test('the action button marks the notice read and follows it in one request', function () {
    addVehicle();

    $vehicle = Vehicle::firstOrFail();
    $notification = $this->user->notifications()->firstOrFail();

    // Marking read and navigating used to be two Inertia visits racing each
    // other, and the PATCH cancelled the navigation about half the time.
    $this->patch(route('notifications.update', $notification).'?go=1')
        ->assertRedirect(route('vehicles.show', $vehicle));

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('without go, marking read still just returns where it was', function () {
    addVehicle();

    $notification = $this->user->notifications()->firstOrFail();

    $this->from(route('notifications'))
        ->patch(route('notifications.update', $notification))
        ->assertRedirect(route('notifications'));

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

test('the prompt shows on a car with nothing set, and goes once one is', function () {
    addVehicle();

    $vehicle = Vehicle::firstOrFail();

    // Both the prompt and the garage card's amber read this one flag, so it is
    // the thing worth holding rather than either screen's rendering of it.
    $this->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehicle.awaiting_setup', true));

    $this->get(route('garage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehicles.0.awaiting_setup', true));

    $vehicle->intervals()->first()->forceFill([
        'last_done_at' => now()->subMonth()->toDateString(),
        'last_done_odometer' => 900,
    ])->save();

    $this->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehicle.awaiting_setup', false));

    $this->get(route('garage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('vehicles.0.awaiting_setup', false));
});

test('a vehicle with no schedule at all is not awaiting setup', function () {
    // That is our bug to fix, not something the owner can act on, so it must
    // not wear the amber or ask them to do anything.
    $vehicle = Vehicle::factory()->for($this->user)->create();

    expect(VehicleGauges::for($vehicle)->awaitingSetup())->toBeFalse();
});
