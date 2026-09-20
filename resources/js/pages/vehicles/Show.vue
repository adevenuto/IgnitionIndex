<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import GaugeGallery from '@/components/vehicle/GaugeGallery.vue';
import GaugeSetupPrompt from '@/components/vehicle/GaugeSetupPrompt.vue';
import VehicleHeaderCard from '@/components/vehicle/VehicleHeaderCard.vue';
import VehicleSwitcher from '@/components/vehicle/VehicleSwitcher.vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import RecallAlert from '@/components/RecallAlert.vue';
import GaugeEditDialog from '@/components/GaugeEditDialog.vue';
import GaugeDial from '@/components/GaugeDial.vue';
import QuickAddSheet from '@/components/QuickAddSheet.vue';
import { useQuickAdd } from '@/composables/useQuickAdd';
import { garage } from '@/routes';
import type {
    FuelBenchmark,
    Gauge,
    MileageSummary,
    VehicleRecall,
} from '@/types/gauges';
import type {
    ServiceTypeOption,
    VehicleChip,
    VehicleDetail,
} from '@/types/garage';

const props = defineProps<{
    vehicles: VehicleChip[];
    vehicle: VehicleDetail;
    gauges: Gauge[];
    recalls: VehicleRecall[];
    fuel: FuelBenchmark | null;
    mileage: MileageSummary;
    serviceTypes: ServiceTypeOption[];
    expenseCategories: string[];
}>();

/*
 * Reminder emails deep-link here as ?log=visit, so the loop is
 * "Oil due -> tap -> Save" rather than landing someone on a page where they
 * still have to find the right button.
 */
const deepLinkLane = new URLSearchParams(window.location.search).get('log');
const validLanes = ['fuel', 'visit', 'expense', 'odometer'] as const;
type Lane = (typeof validLanes)[number];

const initialLane = validLanes.includes(deepLinkLane as Lane)
    ? (deepLinkLane as Lane)
    : undefined;

const quickAddOpen = ref(initialLane !== undefined);
const gaugeOpen = ref(false);
const selectedGauge = ref<Gauge | null>(null);

function editGauge(gauge: Gauge): void {
    selectedGauge.value = gauge;
    gaugeOpen.value = true;
}
const { claimQuickAdd } = useQuickAdd();

claimQuickAdd(() => {
    quickAddOpen.value = true;
});
</script>

<template>
    <Head :title="vehicle.name" />

    <div class="mx-auto flex w-full max-w-[1400px] flex-col gap-(--space-6)">
        <VehicleSwitcher :vehicles="vehicles" />

        <VehicleHeaderCard :vehicle="vehicle" :mileage="mileage" :fuel="fuel" />

        <!-- An open safety recall outranks everything else on the page. -->
        <RecallAlert v-if="recalls.length" :recalls="recalls" />

        <!--
            Below the recall, above the wall it is about. A recall is a safety
            notice and outranks housekeeping, however new the car is.
        -->
        <GaugeSetupPrompt
            v-if="vehicle.awaiting_setup"
            :count="gauges.length"
        />

        <!--
            Every vehicle is seeded with the full catalogue, so an empty wall
            means the catalogue itself is missing rather than anything the owner
            can fix. Saying so beats a bare heading.
        -->
        <BlueprintFrame
            v-if="!gauges.length"
            class="flex flex-col items-center gap-3 p-(--space-6) text-center"
        >
            <GaugeDial class="h-16 w-[84px]" :percent="0" status="unknown" />
            <div>
                <p class="font-display text-title">No gauges yet</p>
                <p class="text-[13px] text-(--color-neutral-700)">
                    This car has no service schedule attached. That is on us —
                    try again shortly.
                </p>
            </div>
        </BlueprintFrame>

        <GaugeGallery
            v-else
            :gauges="gauges"
            :vehicle-id="vehicle.id"
            @select="editGauge"
        />
    </div>

    <GaugeEditDialog
        v-model:open="gaugeOpen"
        :gauge="selectedGauge"
        :projected-odometer="mileage.projected_odometer"
    />

    <QuickAddSheet
        v-model:open="quickAddOpen"
        :vehicles="[vehicle]"
        :service-types="serviceTypes"
        :expense-categories="expenseCategories"
        :initial-lane="initialLane"
    />
</template>
