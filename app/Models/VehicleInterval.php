<?php

namespace App\Models;

use App\Enums\IntervalSource;
use Database\Factories\VehicleIntervalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A vehicle's schedule row for one service type: the effective intervals, where
 * they came from, and when the job was last done.
 *
 * Phase 1 creates these and keeps last_done_* current. Computing progress and
 * the binding constraint across both axes is Phase 2.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property int $service_type_id
 * @property int|null $interval_months
 * @property int|null $interval_miles
 * @property IntervalSource $source
 * @property Carbon|null $last_done_at
 * @property int|null $last_done_odometer
 * @property int|null $last_done_event_id
 * @property bool $is_active
 * @property int $position
 * @property bool $is_pinned
 * @property string|null $last_reminded_status
 * @property Carbon|null $last_reminded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'service_type_id', 'interval_months', 'interval_miles', 'source',
    'last_done_at', 'last_done_odometer', 'last_done_event_id', 'is_active',
    'position', 'is_pinned',
    'last_reminded_status', 'last_reminded_at',
])]
class VehicleInterval extends Model
{
    /** @use HasFactory<VehicleIntervalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => IntervalSource::class,
            'last_done_at' => 'date',
            'last_reminded_at' => 'datetime',
            'is_active' => 'boolean',
            'is_pinned' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<ServiceType, $this>
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
}
