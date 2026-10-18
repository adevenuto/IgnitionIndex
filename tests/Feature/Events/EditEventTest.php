<?php

use App\Models\Event;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInterval;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Support\Carbon;

/*
 * Correcting and removing entries, and everything an entry feeds: the car's
 * odometer and rate, MPG, and the gauges a service visit reset.
 */

beforeEach(function () {
    $this->seed(ServiceTypeSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->post(route('vehicles.store'), [
        'make' => 'Toyota', 'model' => 'RAV4', 'odometer' => 15_000,
    ])->assertSessionHasNoErrors();

    $this->vehicle = Vehicle::firstOrFail();

    // The reading taken when the car was added, moved back so the tests have
    // room to log entries after it.
    $this->vehicle->events()->firstOrFail()
        ->forceFill(['occurred_on' => daysAgo(60)])->save();

    $this->oil = ServiceType::where('key', 'oil-change')->firstOrFail();
    $this->tires = ServiceType::where('key', 'tire-rotation')->firstOrFail();
});

function daysAgo(int $days): string
{
    return Carbon::today()->subDays($days)->toDateString();
}

function logEntry(Vehicle $vehicle, array $payload): Event
{
    test()->post(route('vehicles.events.store', $vehicle), [
        'confirm_odometer' => true,
        ...$payload,
    ])->assertSessionHasNoErrors();

    return $vehicle->events()->latest('id')->firstOrFail();
}

function intervalFor(Vehicle $vehicle, ServiceType $type): VehicleInterval
{
    return $vehicle->intervals()->where('service_type_id', $type->id)->firstOrFail();
}

test('fixing a typo lets the next reading through, and the car follows the fix', function () {
    $typo = logEntry($this->vehicle, ['type' => 'odometer', 'odometer' => 51_000, 'occurred_on' => daysAgo(20)]);

    // Blocked: lower than the typo.
    $this->post(route('vehicles.events.store', $this->vehicle), [
        'type' => 'odometer', 'odometer' => 15_900, 'occurred_on' => daysAgo(10),
    ])->assertSessionHasErrors('odometer');

    $this->patch(route('events.update', $typo), [
        'type' => 'odometer', 'odometer' => 15_800, 'occurred_on' => daysAgo(20),
    ])->assertSessionHasNoErrors();

    logEntry($this->vehicle, ['type' => 'odometer', 'odometer' => 15_900, 'occurred_on' => daysAgo(10)]);

    $vehicle = $this->vehicle->fresh();

    // 900 mi over the 50 days since the car was added.
    expect($vehicle->last_odometer)->toBe(15_900)
        ->and((float) $vehicle->avg_miles_per_day)->toBe(18.0);
});

test('an entry can be logged the same day the car was added', function () {
    $this->vehicle->events()->firstOrFail()
        ->forceFill(['occurred_on' => Carbon::today()->toDateString()])->save();

    $this->post(route('vehicles.events.store', $this->vehicle), [
        'type' => 'fuel', 'odometer' => 15_040, 'occurred_on' => Carbon::today()->toDateString(),
        'gallons' => 10, 'cost' => '40', 'full_tank' => true,
    ])->assertSessionHasNoErrors();

    expect($this->vehicle->fresh()->last_odometer)->toBe(15_040);
});

test('an entry is checked against its neighbours, not against itself', function () {
    $entry = logEntry($this->vehicle, ['type' => 'odometer', 'odometer' => 16_000, 'occurred_on' => daysAgo(20)]);

    $this->patch(route('events.update', $entry), [
        'type' => 'odometer', 'odometer' => 15_990, 'occurred_on' => daysAgo(20),
    ])->assertSessionHasNoErrors();

    // Still bounded by the car's starting reading before it.
    $this->patch(route('events.update', $entry), [
        'type' => 'odometer', 'odometer' => 14_000, 'occurred_on' => daysAgo(20),
    ])->assertSessionHasErrors('odometer');
});

test('editing a visit moves the gauge it reset', function () {
    $visit = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 16_000, 'occurred_on' => daysAgo(30),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ]);

    $this->patch(route('events.update', $visit), [
        'type' => 'visit', 'odometer' => 16_200, 'occurred_on' => daysAgo(25),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ])->assertSessionHasNoErrors();

    $oil = intervalFor($this->vehicle, $this->oil);

    expect($oil->last_done_at->toDateString())->toBe(daysAgo(25))
        ->and($oil->last_done_odometer)->toBe(16_200)
        ->and($oil->last_done_event_id)->toBe($visit->id);
});

test('taking a job off a visit rewinds its gauge to the previous visit', function () {
    $earlier = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 15_500, 'occurred_on' => daysAgo(50),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ]);
    $later = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 16_500, 'occurred_on' => daysAgo(20),
        'line_items' => [['service_type_id' => $this->oil->id], ['service_type_id' => $this->tires->id]],
    ]);

    $this->patch(route('events.update', $later), [
        'type' => 'visit', 'odometer' => 16_500, 'occurred_on' => daysAgo(20),
        'line_items' => [['service_type_id' => $this->tires->id]],
    ])->assertSessionHasNoErrors();

    $oil = intervalFor($this->vehicle, $this->oil);

    expect($oil->last_done_event_id)->toBe($earlier->id)
        ->and($oil->last_done_odometer)->toBe(15_500)
        ->and(intervalFor($this->vehicle, $this->tires)->last_done_event_id)->toBe($later->id);
});

test('taking the only job of its kind off a visit unsets that gauge', function () {
    $visit = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 16_000, 'occurred_on' => daysAgo(30),
        'line_items' => [['service_type_id' => $this->oil->id], ['service_type_id' => $this->tires->id]],
    ]);

    $this->patch(route('events.update', $visit), [
        'type' => 'visit', 'odometer' => 16_000, 'occurred_on' => daysAgo(30),
        'line_items' => [['service_type_id' => $this->tires->id]],
    ])->assertSessionHasNoErrors();

    $oil = intervalFor($this->vehicle, $this->oil);

    expect($oil->last_done_at)->toBeNull()
        ->and($oil->last_done_odometer)->toBeNull()
        ->and($oil->last_done_event_id)->toBeNull();
});

test('adding a job to a visit by editing it resets that gauge', function () {
    $visit = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 16_000, 'occurred_on' => daysAgo(30),
        'line_items' => [['service_type_id' => $this->tires->id]],
    ]);

    $this->patch(route('events.update', $visit), [
        'type' => 'visit', 'odometer' => 16_000, 'occurred_on' => daysAgo(30),
        'line_items' => [['service_type_id' => $this->tires->id], ['service_type_id' => $this->oil->id]],
    ])->assertSessionHasNoErrors();

    expect(intervalFor($this->vehicle, $this->oil)->last_done_event_id)->toBe($visit->id);
});

test('deleting a visit rewinds the gauges it reset', function () {
    $earlier = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 15_500, 'occurred_on' => daysAgo(50),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ]);
    $later = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 16_500, 'occurred_on' => daysAgo(20),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ]);

    $this->delete(route('events.destroy', $later))->assertSessionHasNoErrors();

    $oil = intervalFor($this->vehicle, $this->oil);

    expect($oil->last_done_event_id)->toBe($earlier->id)
        ->and($oil->last_done_at->toDateString())->toBe(daysAgo(50));

    $this->delete(route('events.destroy', $earlier))->assertSessionHasNoErrors();

    expect(intervalFor($this->vehicle, $this->oil)->last_done_at)->toBeNull();
});

test('a gauge set by hand is left alone when an older visit changes', function () {
    $visit = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 15_500, 'occurred_on' => daysAgo(50),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ]);

    $oil = intervalFor($this->vehicle, $this->oil);

    $this->patch(route('vehicle-intervals.update', $oil), [
        'last_done_at' => daysAgo(40),
        'last_done_odometer' => 15_700,
    ])->assertSessionHasNoErrors();

    expect($oil->refresh()->last_done_event_id)->toBeNull();

    $this->patch(route('events.update', $visit), [
        'type' => 'visit', 'odometer' => 15_400, 'occurred_on' => daysAgo(55),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ])->assertSessionHasNoErrors();

    $this->delete(route('events.destroy', $visit))->assertSessionHasNoErrors();

    expect($oil->refresh()->last_done_at->toDateString())->toBe(daysAgo(40))
        ->and($oil->last_done_odometer)->toBe(15_700);
});

test('editing only a visit\'s notes keeps its gauge\'s reminder history', function () {
    $visit = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 15_500, 'occurred_on' => daysAgo(50),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ]);

    intervalFor($this->vehicle, $this->oil)
        ->forceFill(['last_reminded_status' => 'due', 'last_reminded_at' => now()])->save();

    $this->patch(route('events.update', $visit), [
        'type' => 'visit', 'odometer' => 15_500, 'occurred_on' => daysAgo(50),
        'notes' => 'Synthetic 0W-20',
        'line_items' => [['service_type_id' => $this->oil->id]],
    ])->assertSessionHasNoErrors();

    expect(intervalFor($this->vehicle, $this->oil)->last_reminded_status)->toBe('due');
});

test('editing a fill-up recomputes its MPG and the next one\'s', function () {
    logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_100, 'occurred_on' => daysAgo(40), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);
    $middle = logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_400, 'occurred_on' => daysAgo(30), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);
    $last = logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_700, 'occurred_on' => daysAgo(20), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);

    expect((float) $middle->fresh()->mpg)->toBe(30.0)
        ->and((float) $last->fresh()->mpg)->toBe(30.0);

    $this->patch(route('events.update', $middle), [
        'type' => 'fuel', 'odometer' => 15_500, 'occurred_on' => daysAgo(30), 'gallons' => 10, 'cost' => '40', 'full_tank' => true,
    ])->assertSessionHasNoErrors();

    expect((float) $middle->fresh()->mpg)->toBe(40.0)
        ->and((float) $last->fresh()->mpg)->toBe(20.0);
});

test('a backfilled fill-up corrects the MPG of the one after it', function () {
    logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_100, 'occurred_on' => daysAgo(40), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);
    $last = logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_700, 'occurred_on' => daysAgo(20), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);

    expect((float) $last->fresh()->mpg)->toBe(60.0);

    logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_400, 'occurred_on' => daysAgo(30), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);

    expect((float) $last->fresh()->mpg)->toBe(30.0);
});

test('deleting a fill-up recomputes the MPG of the one after it', function () {
    logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_100, 'occurred_on' => daysAgo(40), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);
    $middle = logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_400, 'occurred_on' => daysAgo(30), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);
    $last = logEntry($this->vehicle, ['type' => 'fuel', 'odometer' => 15_700, 'occurred_on' => daysAgo(20), 'gallons' => 10, 'cost' => '40', 'full_tank' => true]);

    $this->delete(route('events.destroy', $middle))->assertSessionHasNoErrors();

    expect((float) $last->fresh()->mpg)->toBe(60.0);
});

test('an entry\'s type cannot be changed', function () {
    $entry = logEntry($this->vehicle, ['type' => 'odometer', 'odometer' => 16_000, 'occurred_on' => daysAgo(20)]);

    $this->patch(route('events.update', $entry), [
        'type' => 'expense', 'odometer' => 16_000, 'occurred_on' => daysAgo(20), 'category' => 'Insurance',
    ])->assertSessionHasErrors('type');
});

test('someone else\'s entry can be neither edited nor deleted', function () {
    $entry = logEntry($this->vehicle, ['type' => 'odometer', 'odometer' => 16_000, 'occurred_on' => daysAgo(20)]);

    $this->actingAs(User::factory()->create());

    $this->patch(route('events.update', $entry), [
        'type' => 'odometer', 'odometer' => 16_001, 'occurred_on' => daysAgo(20),
    ])->assertForbidden();

    $this->delete(route('events.destroy', $entry))->assertForbidden();

    expect($entry->fresh()->odometer)->toBe(16_000);
});

test('an edit that would put a hand-set gauge out of order is refused', function () {
    $reading = logEntry($this->vehicle, ['type' => 'odometer', 'odometer' => 16_000, 'occurred_on' => daysAgo(20)]);

    $this->patch(route('vehicle-intervals.update', intervalFor($this->vehicle, $this->oil)), [
        'last_done_at' => daysAgo(30),
        'last_done_odometer' => 15_800,
    ])->assertSessionHasNoErrors();

    // The reading after that service would now sit below it.
    $this->patch(route('events.update', $reading), [
        'type' => 'odometer', 'odometer' => 15_700, 'occurred_on' => daysAgo(20),
    ])->assertSessionHasErrors(['odometer' => 'Oil & Filter Change is set as last done at 15,800 mi on '.Carbon::parse(daysAgo(30))->format('M j, Y').', and this change would put your readings out of order with it. Update that gauge first.']);

    expect($reading->fresh()->odometer)->toBe(16_000);
});

test('a delete that would put a hand-set gauge out of order is refused', function () {
    $reading = logEntry($this->vehicle, ['type' => 'odometer', 'odometer' => 16_000, 'occurred_on' => daysAgo(20)]);

    // No date: all we know is it happened by the highest reading.
    $this->patch(route('vehicle-intervals.update', intervalFor($this->vehicle, $this->oil)), [
        'last_done_at' => '',
        'last_done_odometer' => 15_800,
    ])->assertSessionHasNoErrors();

    $this->delete(route('events.destroy', $reading))->assertSessionHasErrors('event');

    expect($reading->fresh())->not->toBeNull()
        ->and($this->vehicle->fresh()->last_odometer)->toBe(16_000);
});

test('a gauge already out of order does not block an unrelated edit', function () {
    $reading = logEntry($this->vehicle, ['type' => 'odometer', 'odometer' => 16_000, 'occurred_on' => daysAgo(20)]);

    // Data from before the check existed.
    intervalFor($this->vehicle, $this->oil)->forceFill([
        'last_done_at' => daysAgo(30),
        'last_done_odometer' => 19_500,
        'last_done_event_id' => null,
    ])->save();

    $this->patch(route('events.update', $reading), [
        'type' => 'odometer', 'odometer' => 16_100, 'occurred_on' => daysAgo(20),
    ])->assertSessionHasNoErrors();
});

test('a gauge set by a visit never blocks an edit', function () {
    $visit = logEntry($this->vehicle, [
        'type' => 'visit', 'odometer' => 16_000, 'occurred_on' => daysAgo(30),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ]);

    $this->patch(route('events.update', $visit), [
        'type' => 'visit', 'odometer' => 15_100, 'occurred_on' => daysAgo(30),
        'line_items' => [['service_type_id' => $this->oil->id]],
    ])->assertSessionHasNoErrors();

    expect(intervalFor($this->vehicle, $this->oil)->last_done_odometer)->toBe(15_100);
});

test('history sends every reading per vehicle, oldest first, beyond the page', function () {
    foreach (range(1, 35) as $i) {
        Event::factory()->for($this->vehicle)->on(daysAgo(59 - $i), 15_000 + $i * 10)->create(['type' => 'odometer']);
    }

    $this->get(route('history'))
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 30)
            ->has("readings.{$this->vehicle->id}", 36)
            ->where("readings.{$this->vehicle->id}.0.odometer", 15_000)
            ->where("readings.{$this->vehicle->id}.0.occurred_on", daysAgo(60))
            ->has('serviceTypes')
            ->has('expenseCategories'));
});

test('a new car\'s first reading carries no note', function () {
    expect($this->vehicle->events()->firstOrFail()->notes)->toBeNull();
});
