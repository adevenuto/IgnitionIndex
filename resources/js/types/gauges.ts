/**
 * Gauge shapes as the server computes them. Progress and the binding axis are
 * worked out in PHP so they can be tested; nothing here recomputes them.
 */

export type GaugeStatus =
    | 'uncalibrated'
    | 'healthy'
    | 'soon'
    | 'due'
    | 'overdue';

/**
 * The three states the interface draws, plus the null one. The backend keeps
 * five GaugeStatus cases because Due and Overdue drive different reminder
 * cadences; they collapse here, where the distinction stops mattering.
 */
export type GaugeDisplayStatus = 'ok' | 'due' | 'overdue' | 'unknown';

export type BindingAxis = 'time' | 'mileage' | 'none';

export type Gauge = {
    id: number;
    service_type_id: number;
    name: string;
    status: GaugeStatus;
    axis: BindingAxis;
    /** Capped 0..1 for drawing. */
    progress: number;
    /** Uncapped — can exceed 1 when something is long overdue. */
    raw_progress: number;
    /** Uncapped whole percent, as the dials print it: 112 is legal. */
    percent: number;
    /** What the binding axis measures against: "5,000 mi" or "12 mo". */
    basis: string | null;
    display_status: GaugeDisplayStatus;
    is_pinned: boolean;
    position: number;
    label: string;
    miles_remaining: number | null;
    days_remaining: number | null;
    /** Each axis on its own, uncapped; null where that axis is not running. */
    time_progress: number | null;
    mile_progress: number | null;
    interval_months: number | null;
    interval_miles: number | null;
    last_done_at: string | null;
    last_done_odometer: number | null;
    source: 'default' | 'vin' | 'user_override';
};

export type MileageSummary = {
    last_odometer: number | null;
    last_odometer_at: string | null;
    projected_odometer: number;
    miles_per_day: number | null;
    days_since_reading: number;
    is_projected: boolean;
    needs_reading: boolean;
};

export type VehicleRecall = {
    id: number;
    campaign_number: string;
    component: string | null;
    summary: string | null;
    remedy: string | null;
    reported_on: string | null;
};

export type FuelBenchmark = {
    actual: number | null;
    sticker: number | null;
    city: number | null;
    highway: number | null;
    reading_count: number;
    /** Percent difference from the sticker; positive is better than rated. */
    delta_percent: number | null;
};
