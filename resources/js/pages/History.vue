<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import EventRow from '@/components/EventRow.vue';
import QuickAddSheet from '@/components/QuickAddSheet.vue';
import { Button } from '@/components/ui/button';
import type {
    HistoryEvent,
    QuickAddVehicle,
    Reading,
    ServiceTypeOption,
} from '@/types/garage';

type Paginated = {
    data: HistoryEvent[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

const props = defineProps<{
    events: Paginated;
    /** Every reading per vehicle id, oldest first. */
    readings: Record<number, Reading[]>;
    serviceTypes: ServiceTypeOption[];
    expenseCategories: string[];
}>();

/*
 * Opening an entry edits it in the sheet it was logged with. The selection
 * outlives the sheet closing so its exit animation still has content to show.
 */
const editOpen = ref(false);
const selected = ref<HistoryEvent | null>(null);

function edit(event: HistoryEvent): void {
    selected.value = event;
    editOpen.value = true;
}

const firstIds = computed(
    () =>
        new Set(
            Object.values(props.readings)
                .map((list) => list[0]?.id)
                .filter((id): id is number => id !== undefined),
        ),
);

// The form wants a vehicle list; when editing, it is only ever the entry's own.
const selectedVehicle = computed<QuickAddVehicle[]>(() =>
    selected.value
        ? [
              {
                  id: selected.value.vehicle_id,
                  name: selected.value.vehicle_name,
                  last_odometer: null,
              },
          ]
        : [],
);
</script>

<template>
    <Head title="History" />

    <EmptyState
        v-if="events.total === 0"
        :icon="ClipboardList"
        title="Nothing Logged Yet"
        description="Every service, fuel-up and expense you record shows up here, building the service record you can hand to a buyer."
    />

    <div
        v-else
        class="mx-auto flex w-full max-w-[1400px] flex-col gap-(--space-4)"
    >
        <p class="ii-eyebrow">
            {{ events.total }} {{ events.total === 1 ? 'entry' : 'entries' }}
        </p>

        <ul class="flex flex-col gap-3">
            <EventRow
                v-for="event in events.data"
                :key="event.id"
                :event="event"
                :vehicle-name="event.vehicle_name"
                :is-first="firstIds.has(event.id)"
                @select="edit(event)"
            />
        </ul>

        <nav v-if="events.links.length > 3" class="flex flex-wrap gap-2 pt-2">
            <template v-for="link in events.links" :key="link.label">
                <Button
                    v-if="link.url"
                    as-child
                    size="sm"
                    :variant="link.active ? 'default' : 'surface'"
                >
                    <Link :href="link.url" v-html="link.label" />
                </Button>
            </template>
        </nav>
    </div>

    <!--
        Keyed on the entry so opening a different one starts a fresh form
        rather than keeping the last one's half-edited fields.
    -->
    <QuickAddSheet
        v-if="selected"
        :key="selected.id"
        v-model:open="editOpen"
        :vehicles="selectedVehicle"
        :service-types="serviceTypes"
        :expense-categories="expenseCategories"
        :event="selected"
        :readings="readings[selected.vehicle_id] ?? []"
    />
</template>
