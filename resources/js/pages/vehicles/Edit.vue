<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import DeleteVehicle from '@/components/DeleteVehicle.vue';
import VehicleFields from '@/components/VehicleFields.vue';
import type { PaletteColor } from '@/components/VehicleColorPicker.vue';
import type { VehiclePhotoSummary } from '@/types/garage';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/vehicles';
import { update as updateAction } from '@/actions/App/Http/Controllers/VehicleController';

defineProps<{
    vehicle: {
        id: number;
        /** Vehicle::displayName() — what to call this car in prose. */
        name: string;
        nickname: string | null;
        vin: string | null;
        year: number | null;
        make: string | null;
        model: string | null;
        trim: string | null;
        engine: string | null;
        color: string | null;
        photos: VehiclePhotoSummary[];
    };
    makes: Record<string, string[]>;
    colors: PaletteColor[];
    minYear: number;
}>();
</script>

<template>
    <Head title="Edit Vehicle" />

    <div
        class="mx-auto w-full max-w-[var(--content-max)] px-5 pb-8 md:max-w-2xl md:pb-0"
    >
        <h1 class="text-h1 mb-6">Edit Vehicle</h1>

        <Form
            v-bind="updateAction.form(vehicle.id)"
            class="flex flex-col gap-6"
            show-progress
            v-slot="{ errors, processing }"
        >
            <VehicleFields
                :makes="makes"
                :colors="colors"
                :min-year="minYear"
                :errors="errors"
                :vehicle="vehicle"
            />

            <p class="text-muted-foreground text-xs">
                Odometer readings are only ever changed by logging an entry, so
                the running total always matches your history.
            </p>

            <!--
                Inside the edit form, which is safe: DialogContent renders
                through reka-ui's DialogPortal, so the confirm dialog's own form
                lands on document.body rather than nested inside this one.
            -->
            <DeleteVehicle
                class="mt-4"
                :vehicle="vehicle"
                :name="vehicle.name"
            />

            <!--
                Sticky on desktop only: on mobile the bottom nav and FAB already
                occupy that space, and a second fixed bar would collide.

                data-flush-bottom drops the scroll container's bottom padding
                (see AppShell) so the stuck bar reaches the bottom edge of the
                window. Actions stay the last thing on the page, below the
                danger zone.
            -->
            <div
                data-flush-bottom
                class="bg-background md:border-border -mx-5 flex justify-end gap-3 px-5 md:sticky md:bottom-0 md:z-10 md:border-t md:py-4"
            >
                <!--
                    Grey rather than the design system's brand-tinted ghost:
                    the primary action should be the only coloured thing here,
                    so cancel does not compete with save for attention.
                -->
                <Button
                    as-child
                    variant="ghost"
                    size="lg"
                    class="text-muted-foreground hover:text-foreground"
                >
                    <Link :href="show(vehicle.id)">Cancel</Link>
                </Button>
                <Button type="submit" size="lg" :disabled="processing">
                    Save Changes
                </Button>
            </div>
        </Form>
    </div>
</template>
