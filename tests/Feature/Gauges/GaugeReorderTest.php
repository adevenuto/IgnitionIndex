<?php

use App\Actions\Vehicles\SeedVehicleIntervals;
use App\Enums\IntervalSource;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInterval;
use App\Support\VehicleGauges;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Rearranging the gauge wall.
 *
 * A swap exchanges `position` and `is_pinned` between exactly two rows, which is
 * what makes the wall's invariants structural rather than validated: a pin is
 * moved and never created, and the multiset of positions is preserved. These
 * hold the endpoint to that.
 *
 * Note GaugeCalibrationTest's "the pinned three are the named ones" asserts what
 * SEEDING does and never calls this endpoint, so a user re-pinning their own wall
 * does not contradict it. Do not "fix" that test against these.
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->vehicle = Vehicle::factory()->for($this->user)->create();
    app(SeedVehicleIntervals::class)->handle($this->vehicle);
});

/** An interval on the test vehicle, by service type key. */
function gauge(string $key, ?Vehicle $vehicle = null): VehicleInterval
{
    return ($vehicle ?? test()->vehicle)->intervals()
        ->whereHas('serviceType', fn ($q) => $q->where('key', $key))
        ->firstOrFail();
}

function swapGauges(int $source, int $target, ?Vehicle $vehicle = null)
{
    return test()->patch(
        route('vehicles.gauge-order.update', $vehicle ?? test()->vehicle),
        ['source_id' => $source, 'target_id' => $target],
    );
}

/** Everything the wall must never stop being true. */
function expectWallInvariants(Vehicle $vehicle): void
{
    $all = $vehicle->intervals()->get();

    expect($all->where('is_pinned', true))->toHaveCount(3)
        ->and($all->where('is_pinned', true)->every(fn (VehicleInterval $i): bool => $i->is_active))->toBeTrue()
        ->and($all->pluck('position')->unique())->toHaveCount($all->count());

    // Design §6: the wall reads in position order, never by urgency.
    $positions = collect(VehicleGauges::for($vehicle->fresh())->toArray())->pluck('position');
    expect($positions->all())->toBe($positions->sort()->values()->all());
}

test('a guest cannot rearrange a wall', function () {
    auth()->logout();

    swapGauges(gauge('oil-change')->id, gauge('battery')->id)
        ->assertRedirect(route('login'));
});

test('another user\'s vehicle is forbidden, not a validation error', function () {
    $other = Vehicle::factory()->create();
    app(SeedVehicleIntervals::class)->handle($other);

    // 403 rather than 422: Gate::authorize runs before validation, so naming
    // someone else's vehicle never gets far enough to talk about ids.
    swapGauges(gauge('oil-change', $other)->id, gauge('battery', $other)->id, $other)
        ->assertForbidden();
});

test('an interval from a different vehicle is rejected and nothing moves', function () {
    $mine = Vehicle::factory()->for($this->user)->create();
    app(SeedVehicleIntervals::class)->handle($mine);

    $before = $this->vehicle->intervals()->pluck('position', 'id');

    // Both vehicles are mine, so the Gate passes — this is the check that the
    // scoped Rule::exists is doing the real work.
    swapGauges(gauge('oil-change')->id, gauge('battery', $mine)->id)
        ->assertSessionHasErrors('target_id');

    expect($this->vehicle->intervals()->pluck('position', 'id')->all())->toBe($before->all());
});

test('an inactive interval cannot be swapped in', function () {
    $other = gauge('other');
    expect($other->is_active)->toBeFalse();

    swapGauges(gauge('oil-change')->id, $other->id)
        ->assertSessionHasErrors('target_id');
});

test('a gauge cannot be swapped with itself', function () {
    $oil = gauge('oil-change');

    swapGauges($oil->id, $oil->id)->assertSessionHasErrors('target_id');
});

test('swapping two list gauges exchanges their positions and pins neither', function () {
    $a = gauge('battery');
    $b = gauge('spark-plugs');
    [$posA, $posB] = [$a->position, $b->position];

    swapGauges($a->id, $b->id)->assertSessionHasNoErrors();

    expect($a->refresh()->position)->toBe($posB)
        ->and($b->refresh()->position)->toBe($posA)
        ->and($a->is_pinned)->toBeFalse()
        ->and($b->is_pinned)->toBeFalse();

    expectWallInvariants($this->vehicle);
});

test('swapping a pinned gauge with a list gauge trades the pin too', function () {
    $pinned = gauge('brake-pads');
    $listed = gauge('spark-plugs');

    expect($pinned->is_pinned)->toBeTrue()
        ->and($listed->is_pinned)->toBeFalse();

    [$posPinned, $posListed] = [$pinned->position, $listed->position];

    swapGauges($listed->id, $pinned->id)->assertSessionHasNoErrors();

    // They genuinely trade places: each lands exactly where the other was.
    expect($listed->refresh()->is_pinned)->toBeTrue()
        ->and($listed->position)->toBe($posPinned)
        ->and($pinned->refresh()->is_pinned)->toBeFalse()
        ->and($pinned->position)->toBe($posListed);

    expectWallInvariants($this->vehicle);
});

test('swapping two pinned gauges keeps both pinned', function () {
    $a = gauge('oil-change');
    $b = gauge('coolant');
    [$posA, $posB] = [$a->position, $b->position];

    swapGauges($a->id, $b->id)->assertSessionHasNoErrors();

    expect($a->refresh()->position)->toBe($posB)
        ->and($b->refresh()->position)->toBe($posA)
        ->and($a->is_pinned)->toBeTrue()
        ->and($b->is_pinned)->toBeTrue();

    expectWallInvariants($this->vehicle);
});

test('the inactive rows keep their positions through a swap', function () {
    // They hold positions but never render, so the client cannot account for
    // them — a renumbering endpoint would shuffle them silently.
    $before = $this->vehicle->intervals()->where('is_active', false)
        ->pluck('position', 'id');

    expect($before)->not->toBeEmpty();

    swapGauges(gauge('oil-change')->id, gauge('battery')->id)
        ->assertSessionHasNoErrors();

    expect($this->vehicle->intervals()->where('is_active', false)
        ->pluck('position', 'id')->all())->toBe($before->all());
});

test('colliding positions are repaired before the swap rather than no-opping', function () {
    // position defaults to 0 and its index is not unique, so rows can share one
    // — a factory-built vehicle has every row at 0. Exchanging two identical
    // values would report success and visibly do nothing.
    DB::table('vehicle_intervals')->where('vehicle_id', $this->vehicle->id)->update(['position' => 0]);

    $a = gauge('battery');
    $b = gauge('spark-plugs');

    swapGauges($a->id, $b->id)->assertSessionHasNoErrors();

    expect($a->refresh()->position)->not->toBe($b->refresh()->position);

    expectWallInvariants($this->vehicle);
});

test('a swap touches neither the schedule nor the history', function () {
    $a = gauge('battery');
    $a->forceFill([
        'last_done_at' => now()->subMonth()->toDateString(),
        'last_done_odometer' => 30_000,
        'source' => IntervalSource::UserOverride,
    ])->save();

    swapGauges($a->id, gauge('spark-plugs')->id)->assertSessionHasNoErrors();

    $a->refresh();

    expect($a->last_done_odometer)->toBe(30_000)
        ->and($a->last_done_at?->toDateString())->toBe(now()->subMonth()->toDateString())
        ->and($a->source)->toBe(IntervalSource::UserOverride);
});

test('the vehicle page serves the new order', function () {
    $listed = gauge('spark-plugs');

    swapGauges($listed->id, gauge('oil-change')->id)->assertSessionHasNoErrors();

    $this->get(route('vehicles.show', $this->vehicle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('gauges.0.name', 'Spark Plugs')
            ->where('gauges.0.is_pinned', true));
});
