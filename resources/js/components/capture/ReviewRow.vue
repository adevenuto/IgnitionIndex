<script setup lang="ts">
import { CircleAlert, Pencil } from '@lucide/vue';
import CaptureProvenance from '@/components/capture/CaptureProvenance.vue';
import type { Provenance } from '@/lib/capture/lanes';

/*
 * One value on the review card. The whole row is the edit control: tapping it
 * goes back to the step that owns the value, rather than opening a second way
 * to change the same number.
 *
 * The hover is an opaque step from the row's own fill. The shared
 * hover-surface token is translucent ink, and here it would composite over the
 * card's divider-coloured ruling instead — a far heavier grey than intended.
 */
defineProps<{
    label: string;
    /** Already formatted for display; null shows as not entered. */
    value: string | null;
    provenance?: Provenance | null;
    flag?: string | null;
}>();

const emit = defineEmits<{ edit: [] }>();
</script>

<template>
    <button
        type="button"
        class="focus-visible:outline-accent grid w-full cursor-pointer gap-1 px-(--space-6) py-(--space-4) text-left transition-colors duration-[var(--dur-fast)] hover:bg-[color-mix(in_srgb,var(--surface-card)_94%,var(--color-text))] focus-visible:outline-2 focus-visible:outline-offset-2"
        @click="emit('edit')"
    >
        <span class="flex items-baseline justify-between gap-3">
            <span class="ii-eyebrow">{{ label }}</span>
            <CaptureProvenance :provenance="provenance ?? null" />
        </span>
        <span class="flex items-center justify-between gap-3">
            <span
                class="font-display text-h3 tabular-nums"
                :class="value === null && 'text-(--color-neutral-700)'"
            >
                {{ value ?? 'Not entered' }}
            </span>
            <Pencil
                class="size-4 shrink-0 text-(--color-neutral-700)"
                :stroke-width="1.5"
                aria-hidden="true"
            />
        </span>
        <span
            v-if="flag"
            class="text-foreground flex items-start gap-1.5 text-sm"
        >
            <CircleAlert
                class="text-accent-700 mt-0.5 size-4 shrink-0"
                :stroke-width="1.5"
            />
            {{ flag }}
        </span>
    </button>
</template>
