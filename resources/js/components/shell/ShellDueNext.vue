<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show as showVehicle } from '@/routes/vehicles';

/*
 * The rail's "Due next" list.
 *
 * The data costs nothing extra: GarageSummary already loads every vehicle's
 * intervals and builds the gauge cluster to produce its counts, so this is the
 * same pass, read rather than discarded.
 */
const page = usePage();

const items = computed(() => page.props.garage?.due_next ?? []);

// §2 reserves the saturated status colours for service state, which is exactly
// what these squares are.
function swatch(status: string): string {
    switch (status) {
        case 'due':
        case 'overdue':
            return 'var(--status-overdue)';
        case 'soon':
            return 'var(--status-due)';
        default:
            return 'var(--color-accent)';
    }
}
</script>

<template>
    <div class="flex flex-col gap-px">
        <Link
            v-for="item in items"
            :key="`${item.vehicle_id}-${item.service_name}`"
            :href="showVehicle(item.vehicle_id)"
            class="ease-standard flex items-center gap-(--space-3) rounded-md px-(--space-3) py-(--space-2) text-[13px] text-(--chrome-ink-secondary) transition-colors duration-[var(--dur-fast)] hover:bg-(--chrome-hover) hover:text-(--chrome-ink)"
        >
            <span
                class="size-[7px] flex-none rounded-full"
                :style="{ background: swatch(item.status) }"
                aria-hidden="true"
            />
            <span class="flex-1 truncate">{{ item.service_name }}</span>
            <span class="text-[11px] text-[var(--chrome-ink-eyebrow)]">
                {{ item.percent }}%
            </span>
            <span class="sr-only">
                on {{ item.vehicle_name }}, {{ item.status }}
            </span>
        </Link>
    </div>
</template>
