/*
 * The colour of each kind of entry (design doc §2, lane hues): fuel orange,
 * service violet, expense emerald, miles sky. Used wherever an entry's type
 * is shown — the Log entry tabs, History rows, the review card — so a type
 * reads the same everywhere. Never for status; amber and rust keep that.
 *
 * Classes are spelled out in full rather than built from the key, because
 * Tailwind only generates utilities it can find written in the source.
 */
export type LaneType = 'fuel' | 'visit' | 'expense' | 'odometer';

export type LaneTone = {
    /** An icon tile: the lane tint with the lane's deep ink. */
    tile: string;
    /** Just the icon, in the lane's deep ink. */
    icon: string;
    /** A selected tab or chip: tint, deep ink, a border in the lane colour. */
    selected: string;
    /** A thin top edge on a card belonging to the lane. */
    edge: string;
};

export const LANE_TONES: Record<LaneType, LaneTone> = {
    fuel: {
        tile: 'bg-lane-fuel-bg text-lane-fuel-ink',
        icon: 'text-lane-fuel-ink',
        selected:
            'data-[state=on]:bg-lane-fuel-bg data-[state=on]:text-lane-fuel-ink data-[state=on]:border-lane-fuel',
        edge: 'border-t-lane-fuel',
    },
    visit: {
        tile: 'bg-lane-visit-bg text-lane-visit-ink',
        icon: 'text-lane-visit-ink',
        selected:
            'data-[state=on]:bg-lane-visit-bg data-[state=on]:text-lane-visit-ink data-[state=on]:border-lane-visit',
        edge: 'border-t-lane-visit',
    },
    expense: {
        tile: 'bg-lane-expense-bg text-lane-expense-ink',
        icon: 'text-lane-expense-ink',
        selected:
            'data-[state=on]:bg-lane-expense-bg data-[state=on]:text-lane-expense-ink data-[state=on]:border-lane-expense',
        edge: 'border-t-lane-expense',
    },
    odometer: {
        tile: 'bg-lane-odometer-bg text-lane-odometer-ink',
        icon: 'text-lane-odometer-ink',
        selected:
            'data-[state=on]:bg-lane-odometer-bg data-[state=on]:text-lane-odometer-ink data-[state=on]:border-lane-odometer',
        edge: 'border-t-lane-odometer',
    },
};

/** The tone for a type, or a neutral one for anything unexpected. */
export function laneTone(type: string): LaneTone {
    return (
        LANE_TONES[type as LaneType] ?? {
            tile: 'bg-(--tile-slate-bg) text-(--tile-slate-ink)',
            icon: 'text-muted-foreground',
            selected: '',
            edge: 'border-t-(--hairline-soft)',
        }
    );
}
