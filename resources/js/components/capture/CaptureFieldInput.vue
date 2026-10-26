<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CaptureProvenance from '@/components/capture/CaptureProvenance.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CaptureField, Provenance } from '@/lib/capture/lanes';

/*
 * One captured value, set as a readout: large enough to check at arm's length
 * at a pump, with its unit inside the field rather than trailing after it.
 *
 * It carries no `name`: the flow posts its values through hidden inputs, so a
 * field can be on screen, or not, without changing what is submitted. Enter
 * moves the flow on rather than submitting the sheet from the middle of it.
 *
 * A value a capture just filled takes the steel border for a moment, so the
 * eye goes to what changed. Colour only — nothing in the system moves to say so.
 */
const props = defineProps<{
    field: CaptureField;
    provenance: Provenance | null;
    flag: string | null;
    /** When a capture last filled this field, to mark it as just filled. */
    filledAt: number | null;
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

const fresh = ref(false);
let fade = 0;

watch(
    () => props.filledAt,
    (at) => {
        window.clearTimeout(fade);
        fresh.value = at !== null;

        if (at !== null) {
            fade = window.setTimeout(() => (fresh.value = false), 1600);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => window.clearTimeout(fade));
</script>

<template>
    <div class="grid gap-(--space-2)">
        <div class="flex items-baseline justify-between gap-3">
            <Label :for="id" class="ii-eyebrow">{{ field.label }}</Label>
            <CaptureProvenance :provenance="provenance" />
        </div>

        <div class="relative">
            <span
                v-if="field.unit === 'usd'"
                class="font-display text-h2 pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-(--color-neutral-700)"
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
                class="font-display text-readout md:text-readout h-18 [appearance:textfield] tabular-nums transition-[color,border-color,box-shadow] duration-[var(--dur-slow)] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                :class="[
                    field.unit === 'usd' ? 'pl-10' : 'pr-16',
                    fresh && 'border-accent',
                ]"
                @keydown.enter.prevent="emit('enter')"
            />
            <span
                v-if="field.unit !== 'usd'"
                class="font-display text-title pointer-events-none absolute top-1/2 right-5 -translate-y-1/2 text-(--color-neutral-700)"
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
