<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Gauge, Plus, Warehouse, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import QuickAddSheet from '@/components/QuickAddSheet.vue';
import VehicleCard from '@/components/VehicleCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useQuickAdd } from '@/composables/useQuickAdd';
import { create, show } from '@/routes/vehicles';
import type { GarageVehicle, ServiceTypeOption } from '@/types/garage';

const props = defineProps<{
    vehicles: GarageVehicle[];
    serviceTypes: ServiceTypeOption[];
    expenseCategories: string[];
}>();

const staleVehicles = computed(() =>
    props.vehicles.filter((vehicle) => vehicle.mileage.needs_reading),
);

const quickAddOpen = ref(false);
const { claimQuickAdd } = useQuickAdd();

/*
 * Pick mode: the shell's Log button, pressed with several cars and none in
 * view, lands here as ?pick. The cards then choose which car to log for —
 * a tap opens the log sheet for that car instead of opening its page.
 */
const picking = ref(new URLSearchParams(window.location.search).has('pick'));
const picked = ref<GarageVehicle | null>(null);

function stopPicking(): void {
    picking.value = false;

    const url = new URL(window.location.href);
    url.searchParams.delete('pick');
    window.history.replaceState(window.history.state, '', url);
}

function logFor(vehicle: GarageVehicle): void {
    picked.value = vehicle;
    quickAddOpen.value = true;
    stopPicking();
}

// The Log button, pressed here: nothing to log against yet means "add your
// first car"; one car means that car's page with its sheet open; several
// means pick one.
function onQuickAdd() {
    if (props.vehicles.length === 0) {
        router.visit(create());
    } else if (props.vehicles.length === 1) {
        router.visit(show(props.vehicles[0].id, { query: { log: 'open' } }));
    } else {
        picking.value = true;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

claimQuickAdd(onQuickAdd);
</script>

<template>
    <Head title="Garage" />

    <EmptyState
        v-if="vehicles.length === 0"
        :icon="Warehouse"
        title="Your Garage Is Empty"
        description="Add your first vehicle and Ignition Index starts tracking what it needs next — by both mileage and time, whichever comes first."
    >
        <Button as-child size="lg">
            <Link :href="create()">
                <Plus />
                Add a Vehicle
            </Link>
        </Button>
    </EmptyState>

    <div
        v-else
        class="mx-auto flex w-full max-w-[1400px] flex-col gap-(--space-6)"
    >
        <!--
            The odometer prompt used to live only on a vehicle's own page, so a
            car you never opened was never asked about — and the projection
            behind every one of its gauges quietly drifted.
        -->
        <Card v-if="staleVehicles.length">
            <CardContent class="flex flex-wrap items-center gap-3">
                <Gauge class="text-accent-700 size-5 shrink-0" />
                <p class="text-body min-w-0 flex-1">
                    <template v-if="staleVehicles.length === 1">
                        It's been a while since
                        {{ staleVehicles[0].name }} had a mileage update.
                    </template>
                    <template v-else>
                        {{ staleVehicles.length }} vehicles need a mileage
                        update to keep their gauges accurate.
                    </template>
                </p>
                <Button
                    v-if="staleVehicles.length === 1"
                    as-child
                    size="sm"
                    class="shrink-0"
                >
                    <Link :href="show(staleVehicles[0].id)">Update</Link>
                </Button>
            </CardContent>
        </Card>

        <div class="flex items-center justify-between">
            <p class="ii-eyebrow">
                {{ vehicles.length }}
                {{ vehicles.length === 1 ? 'vehicle' : 'vehicles' }}
            </p>

            <!-- The page's main action: the near-black primary at full size. -->
            <Button as-child>
                <Link :href="create()">
                    <Plus />
                    Add Vehicle
                </Link>
            </Button>
        </div>

        <!-- Pick mode's prompt, in the accent so it reads as the next step. -->
        <div
            v-if="picking"
            class="border-accent bg-accent-100 flex items-center gap-3 rounded-lg border px-(--space-4) py-(--space-3)"
            role="status"
        >
            <Plus class="text-accent-700 size-5 shrink-0" :stroke-width="1.5" />
            <p
                class="text-body min-w-0 flex-1 font-semibold text-(--color-text)"
            >
                Which vehicle are you logging for?
            </p>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="shrink-0"
                @click="stopPicking"
            >
                <X :stroke-width="1.5" />
                Cancel
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <!--
                In pick mode a tap is caught on the way down (capture) and
                stopped there, so the card's own link never navigates.
            -->
            <div
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                :class="
                    picking &&
                    'ring-accent/60 cursor-pointer rounded-lg ring-2 ring-offset-2 ring-offset-(--color-bg)'
                "
                @click.capture="
                    (event: MouseEvent) => {
                        if (picking) {
                            event.preventDefault();
                            event.stopPropagation();
                            logFor(vehicle);
                        }
                    }
                "
            >
                <VehicleCard :vehicle="vehicle" />
            </div>
        </div>
    </div>

    <QuickAddSheet
        v-if="vehicles.length > 0"
        v-model:open="quickAddOpen"
        :vehicles="picked ? [picked] : vehicles"
        :service-types="serviceTypes"
        :expense-categories="expenseCategories"
    />
</template>
