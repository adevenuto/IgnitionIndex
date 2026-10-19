import { computed, reactive, ref } from 'vue';
import type {
    CaptureLane,
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
 * The step after the last is review. A capture that fills more than the step
 * it was taken on skips ahead past every step it completed, which is how one
 * pump photo, or one sentence, can take someone straight to review.
 */
export function useCaptureFlow(lane: CaptureLane) {
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
    const stepIndex = ref(0);

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

    /** A change by hand, or by any one source: the flag no longer applies. */
    function set(key: string, value: string, source: Provenance): void {
        values[key] = value;
        provenance[key] = value === '' ? null : source;
        flags[key] = null;
    }

    /**
     * Take what a read proposed. Only the values it actually found are taken;
     * a null never clears something already entered.
     */
    function apply(reading: CaptureReading, source: Provenance): void {
        for (const field of lane.fields) {
            const value = reading.fields[field.key];

            if (typeof value === 'number') {
                set(field.key, formatFieldValue(field, value), source);
            }
        }

        for (const flag of reading.flags) {
            if (flag.field in flags) {
                flags[flag.field] = flag.message;
            }
        }

        note.value = reading.note;

        if (!atReview.value && isComplete(stepIndex.value)) {
            stepIndex.value = nextUnfinished(stepIndex.value);
        }
    }

    function next(): void {
        stepIndex.value = Math.min(stepIndex.value + 1, lane.steps.length);
        note.value = null;
    }

    function back(): void {
        stepIndex.value = Math.max(stepIndex.value - 1, 0);
        note.value = null;
    }

    /** Back to the step that owns a field — what tapping a review row does. */
    function edit(key: string): void {
        const index = lane.steps.findIndex((candidate) =>
            candidate.fields.includes(key),
        );

        if (index !== -1) {
            stepIndex.value = index;
            note.value = null;
        }
    }

    return {
        values,
        provenance,
        flags,
        note,
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
