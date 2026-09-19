import type { GaugeDisplayStatus } from '@/types/gauges';

/*
 * How a gauge's state is drawn.
 *
 * The artboard derives colour from the percentage, re-deriving the 75% and 100%
 * thresholds in the view. We derive it from `status` instead, which the backend
 * already computes from config('vehicles.thresholds') — so the threshold lives
 * in exactly one place and the interface cannot disagree with the reminder that
 * was sent about the same interval.
 *
 * Design doc §6 draws three states. The backend keeps five, because Due and
 * Overdue drive different reminder cadences; they collapse here, at the
 * presentation boundary, which is the only place the distinction does not
 * matter.
 */

/** §2: the bright step — fills, arcs and numerals at 20px and above. */
export function gaugeColor(status: GaugeDisplayStatus): string {
    switch (status) {
        case 'overdue':
            return 'var(--status-overdue)';
        case 'due':
            return 'var(--status-due)';
        case 'ok':
            return 'var(--color-accent)';
        default:
            // Not a fourth status colour — the absence of one.
            return 'var(--color-neutral-500)';
    }
}

/** §2: the deep step — anything at text size, where the bright step fails AA. */
export function gaugeInk(status: GaugeDisplayStatus): string {
    switch (status) {
        case 'overdue':
            return 'var(--status-overdue-ink)';
        case 'due':
            return 'var(--status-due-ink)';
        case 'ok':
            return 'var(--status-ok-ink)';
        default:
            return 'var(--color-neutral-700)';
    }
}

/** §9's vocabulary. "Not set" is unchanged from the existing label(). */
export function gaugeStatusLabel(status: GaugeDisplayStatus): string {
    switch (status) {
        case 'overdue':
            return 'Overdue';
        case 'due':
            return 'Due soon';
        case 'ok':
            return 'On interval';
        default:
            return 'Not set';
    }
}

/**
 * The readout. An uncalibrated gauge shows an em dash, never 0% — 0% means
 * "just serviced", which is the opposite of "we have no idea".
 */
export function gaugeReadout(
    status: GaugeDisplayStatus,
    percent: number,
): string {
    return status === 'unknown' ? '—' : `${Math.round(percent)}%`;
}
