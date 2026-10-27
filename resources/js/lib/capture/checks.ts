import type { CaptureCheck } from '@/lib/capture/lanes';
import { formatNumber } from '@/lib/format';

/*
 * The plausibility rules a lane lists, built rather than hand-written, so the
 * miles lane can reuse the odometer's and expenses can reuse a range.
 *
 * Each one is silent about an empty field and about anything it cannot judge
 * (no last reading yet, no gallons to divide by). The messages match the
 * server's own flags in FuelLane.php, so a value reads the same whichever side
 * noticed it.
 */

function number(values: Record<string, string>, key: string): number | null {
    const raw = values[key];

    if (raw === undefined || raw === '') {
        return null;
    }

    const value = Number(raw);

    return Number.isFinite(value) ? value : null;
}

/** An odometer never runs backwards: a capture below the last reading is refused. */
export function notBelowLast(field: string): CaptureCheck {
    return (values, context) => {
        const value = number(values, field);
        const last = context.lastOdometer;

        return value !== null && last !== null && value < last
            ? {
                  field,
                  message: `Lower than your last reading of ${formatNumber(last)} mi.`,
                  reject: true,
                  headline: `${formatNumber(value)} mi is lower than your current mileage`,
                  detail: `Your last reading was ${formatNumber(last)} mi, and an odometer only counts up.`,
              }
            : null;
    };
}

/** Far from where the car should be by now — usually a trip meter or a misheard digit. */
export function nearEstimate(field: string, tolerance = 3000): CaptureCheck {
    return (values, context) => {
        const value = number(values, field);
        const projected = context.projectedOdometer;

        return value !== null &&
            projected !== null &&
            context.lastOdometer !== null &&
            Math.abs(value - projected) > tolerance
            ? {
                  field,
                  message: `Far from the estimate of ≈${formatNumber(projected)} mi. Check it is the odometer and not a trip meter.`,
              }
            : null;
    };
}

/** A value outside what one entry could plausibly be. */
export function within(
    field: string,
    min: number,
    max: number,
    message: (value: number) => string,
): CaptureCheck {
    return (values) => {
        const value = number(values, field);

        return value !== null && (value < min || value > max)
            ? { field, message: message(value) }
            : null;
    };
}

/** One value divided by another, outside a plausible range — a price per gallon, say. */
export function ratioWithin(
    field: string,
    divisor: string,
    min: number,
    max: number,
    message: (ratio: number) => string,
): CaptureCheck {
    return (values) => {
        const value = number(values, field);
        const by = number(values, divisor);

        if (value === null || by === null || by <= 0) {
            return null;
        }

        const ratio = value / by;

        return ratio < min || ratio > max
            ? { field, message: message(ratio) }
            : null;
    };
}
