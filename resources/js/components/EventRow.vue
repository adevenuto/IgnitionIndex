<script setup lang="ts">
import { DollarSign, Fuel, Gauge, Wrench } from '@lucide/vue';
import { computed } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { formatCents, formatDate, formatMiles } from '@/lib/format';
import type { VehicleEvent } from '@/types/garage';

/*
 * One row of history. Every event shows the same spine — date and odometer —
 * with the lane-specific detail underneath, so a mixed list still scans.
 *
 * The whole card is the button that opens the entry for editing: a row is
 * one thing, and on a phone the card is the only target big enough to hit
 * without aiming.
 */
const props = defineProps<{
    event: VehicleEvent;
    vehicleName?: string;
    /**
     * The vehicle's earliest reading, worked out from the data rather than a
     * note, so it stays right when entries are edited, backfilled or removed.
     */
    isFirst?: boolean;
}>();

defineEmits<{
    select: [];
}>();

const icons = {
    visit: Wrench,
    fuel: Fuel,
    expense: DollarSign,
    odometer: Gauge,
} as const;

const detail = computed(() => {
    const event = props.event;

    if (event.type === 'visit') {
        return (
            event.line_items.map((item) => item.name).join(', ') ||
            event.location ||
            'Service visit'
        );
    }

    if (event.type === 'fuel') {
        const parts = [];
        if (event.gallons !== null) parts.push(`${event.gallons} gal`);
        if (event.mpg !== null) parts.push(`${event.mpg} MPG`);

        return parts.join(' · ') || 'Fuel';
    }

    if (event.type === 'expense') {
        return event.category ?? 'Expense';
    }

    return event.notes ?? 'Odometer reading';
});

const accessibleName = computed(
    () =>
        `Edit ${props.event.type_label.toLowerCase()} entry, ${formatDate(props.event.occurred_on)}, ${formatMiles(props.event.odometer)}`,
);

const secondary = computed(() =>
    [props.event.location, props.event.notes].filter(Boolean).join(' — '),
);
</script>

<template>
    <li>
        <!--
            The hover tint sits on the Card, through group-hover, because the
            Card paints its own background over anything set on the button.
        -->
        <button
            type="button"
            class="group block w-full cursor-pointer text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent)"
            :aria-label="accessibleName"
            @click="$emit('select')"
        >
            <Card
                class="ease-standard group-hover:bg-hover-surface transition-colors duration-[var(--dur-fast)] group-hover:border-(--color-accent)"
            >
                <CardContent class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center border border-(--color-divider) bg-(--color-accent-100) text-(--color-accent-700)"
                    >
                        <component :is="icons[event.type]" class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="flex min-w-0 items-center gap-2">
                                <span class="text-title truncate">
                                    {{ detail }}
                                </span>
                                <span
                                    v-if="isFirst"
                                    class="ii-tag ii-tag-outline shrink-0 text-[10px] font-bold tracking-[0.12em] text-(--color-neutral-700) uppercase"
                                >
                                    First reading
                                </span>
                            </p>
                            <p
                                v-if="event.cost_cents !== null"
                                class="text-title shrink-0"
                            >
                                {{ formatCents(event.cost_cents) }}
                            </p>
                        </div>

                        <p class="text-muted-foreground mt-0.5 text-sm">
                            <span v-if="vehicleName">{{ vehicleName }} · </span>
                            {{ formatDate(event.occurred_on) }} ·
                            {{ formatMiles(event.odometer) }}
                        </p>

                        <p
                            v-if="secondary && event.type !== 'odometer'"
                            class="text-muted-foreground mt-1 truncate text-sm"
                        >
                            {{ secondary }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </button>
    </li>
</template>
