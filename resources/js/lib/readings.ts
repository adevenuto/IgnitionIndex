import { formatDate, formatMiles } from '@/lib/format';
import type { Reading } from '@/types/garage';

/*
 * The readings either side of a date, as MonotonicOdometer bounds them.
 *
 * Strictly before and strictly after: readings carry no time of day, so others
 * on the same date do not constrain each other. This mirrors the server rule
 * only so the edit sheet can show the range before saving — the server still
 * decides, and its message is what appears if the two ever disagree.
 */
export function boundsFor(
    readings: Reading[],
    ignoreId: number | null,
    date: string,
): { before: Reading | null; after: Reading | null } {
    let before: Reading | null = null;
    let after: Reading | null = null;

    for (const reading of readings) {
        if (reading.id === ignoreId) {
            continue;
        }

        // ISO dates compare correctly as strings.
        if (reading.occurred_on < date) {
            if (before === null || reading.odometer > before.odometer) {
                before = reading;
            }
        } else if (reading.occurred_on > date) {
            if (after === null || reading.odometer < after.odometer) {
                after = reading;
            }
        }
    }

    return { before, after };
}

/** "Between 15,000 mi (Aug 19, 2026) and 15,900 mi (Oct 8, 2026)", or one side of it. */
export function rangeHint(bounds: {
    before: Reading | null;
    after: Reading | null;
}): string | null {
    const say = (reading: Reading) =>
        `${formatMiles(reading.odometer)} (${formatDate(reading.occurred_on)})`;

    if (bounds.before && bounds.after) {
        return `Between ${say(bounds.before)} and ${say(bounds.after)}`;
    }

    if (bounds.before) {
        return `At least ${say(bounds.before)}`;
    }

    if (bounds.after) {
        return `Up to ${say(bounds.after)}`;
    }

    return null;
}
