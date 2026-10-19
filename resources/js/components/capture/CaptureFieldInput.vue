<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed } from 'vue';
import CaptureProvenance from '@/components/capture/CaptureProvenance.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CaptureField, Provenance } from '@/lib/capture/lanes';

/*
 * One captured value, large enough to check at arm's length at a pump.
 *
 * It carries no `name`: the flow posts its values through hidden inputs, so a
 * field can be on screen, or not, without changing what is submitted. Enter
 * moves the flow on rather than submitting the sheet from the middle of it.
 */
const props = defineProps<{
    field: CaptureField;
    provenance: Provenance | null;
    flag: string | null;
}>();

const value = defineModel<string>({ required: true });

const emit = defineEmits<{
    enter: [];
}>();

const id = computed(() => `capture-${props.field.key}`);
const step = computed(() =>
    props.field.precision === 0 ? '1' : String(10 ** -props.field.precision),
);
const units = { mi: 'mi', gal: 'gal', usd: '$' } as const;
</script>

<template>
    <div class="grid gap-(--space-2)">
        <div class="flex items-baseline justify-between gap-3">
            <Label :for="id" class="ii-eyebrow">{{ field.label }}</Label>
            <CaptureProvenance :provenance="provenance" />
        </div>

        <div class="flex items-center gap-2">
            <span
                v-if="field.unit === 'usd'"
                class="font-display text-h3 text-(--color-neutral-700)"
                aria-hidden="true"
                >$</span
            >
            <Input
                :id="id"
                v-model="value"
                type="number"
                :step="step"
                min="0"
                :inputmode="field.precision === 0 ? 'numeric' : 'decimal'"
                :placeholder="field.placeholder"
                :aria-describedby="flag ? `${id}-flag` : undefined"
                class="font-display text-h2 md:text-h2 h-14 tabular-nums"
                @keydown.enter.prevent="emit('enter')"
            />
            <span
                v-if="field.unit !== 'usd'"
                class="text-body text-(--color-neutral-700)"
                aria-hidden="true"
                >{{ units[field.unit] }}</span
            >
        </div>

        <!-- Neutral, not amber or rust: those mean service state, and this is a question. -->
        <p
            v-if="flag"
            :id="`${id}-flag`"
            class="text-foreground flex items-start gap-1.5 text-sm"
        >
            <CircleAlert
                class="text-accent-700 mt-0.5 size-4 shrink-0"
                :stroke-width="1.5"
            />
            {{ flag }}
        </p>
    </div>
</template>
