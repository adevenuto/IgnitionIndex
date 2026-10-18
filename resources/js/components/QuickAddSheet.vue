<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import QuickAddForm from '@/components/QuickAddForm.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type {
    HistoryEvent,
    QuickAddVehicle,
    Reading,
    ServiceTypeOption,
} from '@/types/garage';

/*
 * Chrome for the quick-add flow: a bottom sheet on mobile, a centred dialog on
 * desktop. QuickAddForm is the shared body.
 *
 * Two wrappers rather than one restyled sheet: SheetContent hardcodes
 * `inset-x-0 bottom-0` plus a slide-from-bottom animation for side="bottom",
 * and centring needs a translate on both axes — the two fight, and the result
 * is a modal that jumps as it opens. DialogContent already centres and zooms.
 *
 * useMediaQuery is safe here in a way it would not be in the app shell: this
 * only renders after a deliberate tap, long past the first tick, so there is no
 * flash of the wrong variant. The breakpoint pairs exactly with Tailwind's `md`
 * (and with the sidebar's 767.98px cutoff) so there is no width where neither
 * matches.
 */
const props = defineProps<{
    vehicles: QuickAddVehicle[];
    serviceTypes: ServiceTypeOption[];
    expenseCategories: string[];
    initialLane?: 'fuel' | 'visit' | 'expense' | 'odometer';
    /** Opens the sheet on an existing entry, to edit or delete it. */
    event?: HistoryEvent | null;
    /** The entry's vehicle's readings, so the edit form can show its range. */
    readings?: Reading[];
}>();

const open = defineModel<boolean>('open', { required: true });

const isDesktop = useMediaQuery('(min-width: 768px)');

const title = () => (props.event ? 'Edit entry' : 'Log an entry');

const subtitle = () =>
    props.event
        ? props.event.vehicle_name
        : props.vehicles.length > 1
          ? 'Choose a vehicle below'
          : (props.vehicles[0]?.name ?? '');
</script>

<template>
    <Dialog v-if="isDesktop" v-model:open="open">
        <DialogContent
            class="max-h-[85vh] gap-0 overflow-y-auto p-0 sm:max-w-2xl"
        >
            <DialogHeader class="px-4 pt-5 pb-0 text-left">
                <DialogTitle class="text-h2">{{ title() }}</DialogTitle>
                <DialogDescription class="text-body">
                    {{ subtitle() }}
                </DialogDescription>
            </DialogHeader>

            <QuickAddForm
                :vehicles="vehicles"
                :service-types="serviceTypes"
                :expense-categories="expenseCategories"
                :initial-lane="initialLane"
                :event="event"
                :readings="readings"
                @saved="open = false"
            />
        </DialogContent>
    </Dialog>

    <Sheet v-else v-model:open="open">
        <SheetContent
            side="bottom"
            class="max-h-[90svh] overflow-y-auto rounded-t-xl"
        >
            <SheetHeader class="pb-0">
                <SheetTitle class="text-h2">{{ title() }}</SheetTitle>
                <SheetDescription class="text-body">
                    {{ subtitle() }}
                </SheetDescription>
            </SheetHeader>

            <QuickAddForm
                :vehicles="vehicles"
                :service-types="serviceTypes"
                :expense-categories="expenseCategories"
                :initial-lane="initialLane"
                :event="event"
                :readings="readings"
                @saved="open = false"
            />
        </SheetContent>
    </Sheet>
</template>
