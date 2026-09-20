import type { Gauge, MileageSummary } from '@/types/gauges';

/**
 * Shapes the garage and vehicle screens receive from Inertia. These mirror the
 * arrays built in VehicleController and HistoryController.
 */

export type GarageVehicle = {
    id: number;
    name: string;
    year: number | null;
    make: string | null;
    model: string | null;
    trim: string | null;
    color: string | null;
    photo_thumb_url: string | null;
    photo_color: string | null;
    last_odometer: number | null;
    last_odometer_at: string | null;
    events_count: number;
    worst: Gauge | null;
    due_count: number;
    open_recall_count: number;
    uncalibrated_count: number;
    /** Every gauge still without a starting point; see VehicleGauges::awaitingSetup(). */
    awaiting_setup: boolean;
    mileage: MileageSummary;
};

export type EventLineItem = {
    id: number;
    name: string;
    cost_cents: number | null;
};

export type VehicleEvent = {
    id: number;
    type: 'visit' | 'fuel' | 'expense' | 'odometer';
    type_label: string;
    odometer: number;
    occurred_on: string;
    cost_cents: number | null;
    notes: string | null;
    location: string | null;
    gallons: number | null;
    full_tank?: boolean | null;
    mpg: number | null;
    category: string | null;
    line_items: EventLineItem[];
};

export type HistoryEvent = VehicleEvent & {
    vehicle_id: number;
    vehicle_name: string;
};

export type VehicleDetail = {
    id: number;
    name: string;
    nickname: string | null;
    vin: string | null;
    year: number | null;
    make: string | null;
    model: string | null;
    trim: string | null;
    engine: string | null;
    color: string | null;
    photo_url: string | null;
    photo_color: string | null;
    last_odometer: number | null;
    last_odometer_at: string | null;
    avg_miles_per_month: number | null;
    /** Every gauge still without a starting point; see VehicleGauges::awaitingSetup(). */
    awaiting_setup: boolean;
    services_due_count: number;
    services_overdue_count: number;
};

export type VehicleChip = {
    id: number;
    name: string;
    color: string | null;
    spec: string;
    is_active: boolean;
};

export type ServiceTypeOption = {
    id: number;
    name: string;
    category: string | null;
};

/**
 * The minimum the quick-add sheet needs about a vehicle: which one it is, what
 * to call it, and the reading to pre-fill.
 */
export type QuickAddVehicle = {
    id: number;
    name: string;
    last_odometer: number | null;
};

/**
 * An already-stored photo, as the picker receives it on the edit form.
 */
export type VehiclePhotoSummary = {
    id: number;
    url: string;
    color: string | null;
};
