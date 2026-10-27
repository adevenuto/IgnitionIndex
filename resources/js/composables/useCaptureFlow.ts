import { computed, reactive, ref } from 'vue';
import type {
    CaptureContext,
    CaptureLane,
    CaptureOutcome,
    CaptureReading,
    Provenance,
} from '@/lib/capture/lanes';
import { formatFieldValue } from '@/lib/capture/lanes';

/*
 * The state of one lane's capture flow: field values, where each came from,
 * what deserves a second look, and which step is showing. It knows nothing
 * about any particular lane — the lane's config says what the fields and steps
 * are — so every lane's flow behaves the same way.
 *
 * The step after the last is review. A capture stays on its step long enough
 * to be seen — the outcome says what was filled and what was refused — and
 * Next then skips every step the capture already completed, which is how one
 * pump photo, or one sentence, still takes someone straight to review.
 *
 * The lane's checks run on every change. A captured value a check rejects is
 * never filled in; a typed one is only flagged, since typing a lower odometer
 * is how a past mistake gets corrected.
 */
export function useCaptureFlow(
    lane: CaptureLane,
    context: () => CaptureContext,
) {
    const keys = lane.fields.map((field) => field.key);
    const empty = <T>(value: T) =>
        Object.fromEntries(keys.map((key) => [key, value])) as Record<
            string,
            T
        >;

    const values = reactive(empty(''));
    const provenance = reactive(empty<Provenance | null>(null));
    const flags = reactive(empty<string | null>(null));
    const note = ref<string | null>(null);
    const outcome = ref<CaptureOutcome | null>(null);
    const stepIndex = ref(0);
    /** The reader's own flags, kept until their field changes. */
    const serverFlags = empty<string | null>(null);

    const atReview = computed(() => stepIndex.value >= lane.steps.length);
    const step = computed(() => lane.steps[stepIndex.value] ?? null);

    function isComplete(index: number): boolean {
        return lane.steps[index].fields.every((key) => values[key] !== '');
    }

    /** The first unfinished step after `index`, or review. */
    function nextUnfinished(index: number): number {
        for (let next = index + 1; next < lane.steps.length; next++) {
            if (!isComplete(next)) {
                return next;
            }
        }

        return lane.steps.length;
    }

    /** Every check's verdict on a set of values, by field; the first one wins. */
    function verdicts(candidate: Record<string, string>) {
        const found: Record<
            string,
            {
                message: string;
                reject: boolean;
                headline?: string;
                detail?: string;
            }
        > = {};

        for (const check of lane.checks) {
            const result = check(candidate, context());

            if (result && !(result.field in found)) {
                found[result.field] = {
                    message: result.message,
                    reject: result.reject ?? false,
                    headline: result.headline,
                    detail: result.detail,
                };
            }
        }

        return found;
    }

    /** Flags from the checks first, then whatever the reader noticed. */
    function refreshFlags(): void {
        const found = verdicts(values);

        for (const key of keys) {
            flags[key] =
                values[key] === ''
                    ? null
                    : (found[key]?.message ?? serverFlags[key]);
        }
    }

    /** A change by hand, or by any one source: the reader's flag no longer applies. */
    function set(key: string, value: string, source: Provenance): void {
        values[key] = value;
        provenance[key] = value === '' ? null : source;
        serverFlags[key] = null;
        refreshFlags();
    }

    /**
     * Take what a read proposed. Only the values it actually found are taken —
     * a null never clears something already entered — and only those no check
     * rejects. What happened is kept in `outcome` for the confirmation.
     */
    function apply(reading: CaptureReading, source: Provenance): void {
        const proposed: Record<string, number> = {};

        for (const field of lane.fields) {
            const value = reading.fields[field.key];

            if (typeof value === 'number') {
                proposed[field.key] = value;
            }
        }

        const candidate = { ...values };

        for (const field of lane.fields) {
            if (field.key in proposed) {
                candidate[field.key] = formatFieldValue(
                    field,
                    proposed[field.key],
                );
            }
        }

        const found = verdicts(candidate);
        const applied: string[] = [];
        const rejected: CaptureOutcome['rejected'] = [];

        for (const field of lane.fields) {
            if (!(field.key in proposed)) {
                continue;
            }

            if (found[field.key]?.reject) {
                rejected.push({
                    field: field.key,
                    value: proposed[field.key],
                    message: found[field.key].message,
                    headline: found[field.key].headline,
                    detail: found[field.key].detail,
                });

                continue;
            }

            values[field.key] = candidate[field.key];
            provenance[field.key] = source;
            serverFlags[field.key] = null;
            applied.push(field.key);
        }

        for (const flag of reading.flags) {
            if (applied.includes(flag.field)) {
                serverFlags[flag.field] = flag.message;
            }
        }

        refreshFlags();
        note.value = reading.note;
        outcome.value = { at: performance.now(), source, applied, rejected };
    }

    /** On to the first step not already filled, or review. */
    /**
     * A typed value a check would refuse — an odometer below the last
     * reading — is kept in its field (the person may be mid-correction) but
     * cannot be carried past its step: Next stops and says why, the same way
     * a refused capture does.
     */
    function refusedOnStep(): CaptureOutcome['rejected'] {
        const found = verdicts(values);

        return (step.value?.fields ?? [])
            .filter((key) => values[key] !== '' && found[key]?.reject)
            .map((key) => ({
                field: key,
                value: Number(values[key]),
                message: found[key].message,
                headline: found[key].headline,
                detail: found[key].detail,
            }));
    }

    function next(): void {
        const refused = atReview.value ? [] : refusedOnStep();

        if (refused.length > 0) {
            outcome.value = {
                at: performance.now(),
                source: 'typed',
                applied: [],
                rejected: refused,
            };

            return;
        }

        stepIndex.value = atReview.value
            ? stepIndex.value
            : nextUnfinished(stepIndex.value);
        note.value = null;
        outcome.value = null;
    }

    function back(): void {
        stepIndex.value = Math.max(stepIndex.value - 1, 0);
        note.value = null;
        outcome.value = null;
    }

    /** Back to the step that owns a field — what tapping a review row does. */
    function edit(key: string): void {
        const index = lane.steps.findIndex((candidate) =>
            candidate.fields.includes(key),
        );

        if (index !== -1) {
            stepIndex.value = index;
            note.value = null;
            outcome.value = null;
        }
    }

    return {
        values,
        provenance,
        flags,
        note,
        outcome,
        stepIndex,
        step,
        atReview,
        set,
        apply,
        next,
        back,
        edit,
    };
}

export type CaptureFlow = ReturnType<typeof useCaptureFlow>;
