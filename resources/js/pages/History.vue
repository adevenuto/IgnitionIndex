<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import EventRow from '@/components/EventRow.vue';
import { Button } from '@/components/ui/button';
import type { HistoryEvent } from '@/types/garage';

type Paginated = {
    data: HistoryEvent[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

defineProps<{
    events: Paginated;
}>();
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
</template>
