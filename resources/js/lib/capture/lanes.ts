import type { SpokenUnit } from '@/lib/capture/spokenNumbers';

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
};

export type CaptureLane = {
    id: CaptureLaneId;
    fields: CaptureField[];
    steps: CaptureStep[];
};

/** What the server proposes for a lane: every field, null where unread. */
export type CaptureReading = {
    fields: Record<string, number | string | null>;
    flags: { field: string; message: string }[];
    note: string | null;
};

/** A field's value as the inputs hold it: a string, '' when empty. */
export function formatFieldValue(field: CaptureField, value: number): string {
    return field.precision === 0
        ? String(Math.round(value))
        : String(Number(value.toFixed(field.precision)));
}
