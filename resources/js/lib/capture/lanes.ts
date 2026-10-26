import type { SpokenUnit } from '@/lib/capture/spokenNumbers';
import { formatNumber } from '@/lib/format';

/*
 * The shape every assisted-capture lane is written in.
 *
 * A lane is configuration: which fields it captures, how they are grouped into
 * steps, and how each one is typed, spoken and shown. The stepper, the capture
 * buttons, the review card and the reader are shared, so a new lane is a new
 * file like fuel.ts and a server-side lane definition — nothing else.
 */

export type CaptureLaneId = 'fuel';

/** Where a value came from, shown on review so nothing arrives unexplained. */
export type Provenance = 'photo' | 'voice' | 'estimate' | 'typed';

export type CaptureField = {
    /** The key the reader returns it under. */
    key: string;
    /** The form field it posts as. */
    name: string;
    label: string;
    unit: SpokenUnit;
    /** Decimal places the input accepts; 0 for whole numbers. */
    precision: number;
    /**
     * Never a plausible value: at this size a realistic number in the empty
     * field reads as already filled in.
     */
    placeholder: string;
};

export type CaptureStep = {
    id: string;
    title: string;
    /** One line under the title saying what a photo of it looks like. */
    prompt: string;
    /** Keys of the fields this step owns, in display order. */
    fields: string[];
    /** The line under each method card's title, said for this step. */
    cards: { photo: string; voice: string; type: string };
    /** A known value shown beside the title to check the new one against. */
    reference?: 'lastOdometer';
};

/** What is already known about the vehicle, for checks and references. */
export type CaptureContext = {
    lastOdometer: number | null;
    projectedOdometer: number | null;
};

/**
 * One plausibility rule over a lane's values. A rejection keeps a captured
 * value out of the field entirely; anything else is a flag beside it. Typed
 * values are only ever flagged — the person may be correcting a mistake.
 */
export type CaptureCheck = (
    values: Record<string, string>,
    context: CaptureContext,
) => { field: string; message: string; reject?: boolean } | null;

export type CaptureLane = {
    id: CaptureLaneId;
    fields: CaptureField[];
    steps: CaptureStep[];
    checks: CaptureCheck[];
};

/** What the server proposes for a lane: every field, null where unread. */
export type CaptureReading = {
    fields: Record<string, number | string | null>;
    flags: { field: string; message: string }[];
    note: string | null;
};

/** What one capture did, for the confirmation under the fields. */
export type CaptureOutcome = {
    /** Distinguishes one capture from the next, even when they fill the same field. */
    at: number;
    source: Provenance;
    /** Keys filled by it, in lane order. */
    applied: string[];
    /** Values it proposed that a check refused. */
    rejected: { field: string; value: number; message: string }[];
};

/** A field's value as the inputs hold it: a string, '' when empty. */
export function formatFieldValue(field: CaptureField, value: number): string {
    return field.precision === 0
        ? String(Math.round(value))
        : String(Number(value.toFixed(field.precision)));
}

/** A value as people read it: "62,410 mi", "12.5 gal", "$48.12". */
export function describeFieldValue(field: CaptureField, value: number): string {
    switch (field.unit) {
        case 'usd':
            return `$${value.toFixed(2)}`;
        case 'gal':
            return `${Number(value.toFixed(field.precision))} gal`;
        case 'mi':
            return `${formatNumber(Math.round(value))} mi`;
    }
}
