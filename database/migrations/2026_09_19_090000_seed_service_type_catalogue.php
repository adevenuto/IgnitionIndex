<?php

use App\Actions\Vehicles\SeedVehicleIntervals;
use App\Models\Vehicle;
use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The service catalogue is reference data, so it has to arrive by migration.
 *
 * It only ever existed in ServiceTypeSeeder, and Cloud's deploy command is
 * `php artisan migrate --force` with no `db:seed` (docs/LARAVEL_CLOUD_DEPLOYMENT.md).
 * So production ran with an empty `service_types` table: SeedVehicleIntervals
 * looped over nothing, every new vehicle got zero interval rows, and the screen
 * after "add a car" had no gauges on it at all. Seeding from a seeder that only
 * runs locally is the bug, not the empty screen.
 *
 * Idempotent on `key`, so it is safe on an environment that HAS been seeded and
 * safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ServiceTypeSeeder)->run();

        $this->backfillVehicles();
        $this->repin();
    }

    /**
     * Deliberately empty.
     *
     * Rolling back would delete the catalogue every vehicle's intervals point
     * at, taking their schedules and history with it. Reference data that other
     * rows depend on does not get un-seeded.
     */
    public function down(): void
    {
        //
    }

    /**
     * Give the vehicles that were added while the catalogue was empty the
     * schedule they should have had.
     *
     * Uses the action rather than raw SQL so a vehicle backfilled here is
     * identical to one added after this deploy — there is one definition of
     * what a seeded schedule looks like, and it stays that way.
     */
    private function backfillVehicles(): void
    {
        $seed = new SeedVehicleIntervals;

        Vehicle::query()
            ->whereDoesntHave('intervals')
            ->each(fn (Vehicle $vehicle) => $seed->handle($vehicle));
    }

    /**
     * Move existing vehicles onto the named pinned set.
     *
     * Vehicles seeded before this pinned whichever three came first in the
     * catalogue (oil, tire rotation, engine air filter). Left alone they would
     * show a different gauge wall from every vehicle added afterwards.
     */
    private function repin(): void
    {
        $pinnedIds = DB::table('service_types')
            ->whereIn('key', SeedVehicleIntervals::PINNED_KEYS)
            ->pluck('id');

        DB::table('vehicle_intervals')
            ->where('is_pinned', true)
            ->whereNotIn('service_type_id', $pinnedIds)
            ->update(['is_pinned' => false]);

        DB::table('vehicle_intervals')
            ->whereIn('service_type_id', $pinnedIds)
            ->where('is_active', true)
            ->update(['is_pinned' => true]);
    }
};
