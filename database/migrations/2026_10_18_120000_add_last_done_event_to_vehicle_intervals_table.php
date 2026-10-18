<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Which service visit last reset a gauge.
 *
 * Entries can now be edited and deleted, and a gauge reset by a visit has to
 * follow that visit when it changes. A gauge set by hand in the edit dialog
 * must not — so the row needs to know which of the two it is. Null means set
 * by hand (or never set).
 *
 * Inferring it from a matching date and odometer would work most of the time
 * and fail exactly when two visits share a day, so it is recorded instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_intervals', function (Blueprint $table): void {
            $table->foreignId('last_done_event_id')
                ->nullable()
                ->after('last_done_odometer')
                ->constrained('events')
                ->nullOnDelete();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('vehicle_intervals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_done_event_id');
        });
    }

    /**
     * Link existing rows to the visit that matches them exactly: same vehicle,
     * a line item for this service, same date and odometer. Anything without a
     * match stays null and is treated as set by hand, which is the safe side —
     * an edit then leaves it alone rather than rewinding it.
     */
    private function backfill(): void
    {
        $intervals = DB::table('vehicle_intervals')
            ->whereNotNull('last_done_at')
            ->get(['id', 'vehicle_id', 'service_type_id', 'last_done_at', 'last_done_odometer']);

        foreach ($intervals as $interval) {
            $eventId = DB::table('events')
                ->join('line_items', 'line_items.event_id', '=', 'events.id')
                ->where('events.vehicle_id', $interval->vehicle_id)
                ->where('events.type', 'visit')
                ->where('line_items.service_type_id', $interval->service_type_id)
                ->whereDate('events.occurred_on', substr((string) $interval->last_done_at, 0, 10))
                ->where('events.odometer', $interval->last_done_odometer)
                ->orderByDesc('events.id')
                ->value('events.id');

            if ($eventId !== null) {
                DB::table('vehicle_intervals')
                    ->where('id', $interval->id)
                    ->update(['last_done_event_id' => $eventId]);
            }
        }
    }
};
