<script setup lang="ts">
import { Check, CircleAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { CaptureField, CaptureOutcome } from '@/lib/capture/lanes';
import { describeFieldValue } from '@/lib/capture/lanes';

/*
 * One line saying what the last capture did, so a value never just appears:
 * what was heard or read, and — when a check refused it — what it was and why
 * it was left out.
 *
 * Neutral ink with a steel icon, never amber or rust: those mean service state,
 * and this is a reading being checked, not a car needing work.
 */
const props = defineProps<{
    outcome: CaptureOutcome;
    fields: CaptureField[];
    values: Record<string, string>;
}>();

const byKey = computed(
    () =>
        Object.fromEntries(
            props.fields.map((field) => [field.key, field]),
        ) as Record<string, CaptureField>,
);

const verb = computed(() =>
    props.outcome.source === 'photo' ? 'Read from the photo:' : 'Heard',
);

const applied = computed(() =>
    props.outcome.applied
        .filter((key) => key in byKey.value && props.values[key] !== '')
        .map((key) =>
            describeFieldValue(byKey.value[key], Number(props.values[key])),
        )
        .join(', '),
);

const rejected = computed(() =>
    props.outcome.rejected
        .filter((item) => item.field in byKey.value)
        .map(
            (item) =>
                `${describeFieldValue(byKey.value[item.field], item.value)}. ${item.message}`,
        ),
);
</script>

<template>
    <div class="grid gap-(--space-2)" role="status" aria-live="polite">
        <p
            v-if="applied"
            class="text-foreground flex items-start gap-2 text-sm"
        >
            <Check
                class="text-accent-700 mt-0.5 size-4 shrink-0"
                :stroke-width="1.5"
            />
            <span>{{ verb }} {{ applied }}</span>
        </p>

        <p
            v-for="(line, index) in rejected"
            :key="index"
            class="text-foreground flex items-start gap-2 text-sm"
        >
            <CircleAlert
                class="text-accent-700 mt-0.5 size-4 shrink-0"
                :stroke-width="1.5"
            />
            <span>
                {{ verb }} {{ line }} Left out — try again, or type it.
            </span>
        </p>
    </div>
</template>
