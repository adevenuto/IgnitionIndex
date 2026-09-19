<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Gauge, Plus, Warehouse } from '@lucide/vue';
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

// The shell's FAB routes to whatever makes sense here: with no vehicles there
// is nothing to log against yet, so it becomes "add your first car".
function onQuickAdd() {
    if (props.vehicles.length === 0) {
        router.visit(create());

        return;
    }

    quickAddOpen.value = true;
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

            <Button as-child variant="outline" size="sm">
                <Link :href="create()">
                    <Plus />
                    Add Vehicle
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <VehicleCard
                v-for="vehicle in vehicles"
                :key="vehicle.id"
                :vehicle="vehicle"
            />
        </div>
    </div>

    <QuickAddSheet
        v-if="vehicles.length > 0"
        v-model:open="quickAddOpen"
        :vehicles="vehicles"
        :service-types="serviceTypes"
        :expense-categories="expenseCategories"
    />
</template>
