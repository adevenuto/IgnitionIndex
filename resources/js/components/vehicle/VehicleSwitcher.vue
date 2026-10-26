<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { create, show } from '@/routes/vehicles';

/*
 * The garage, as a row of chips above the vehicle.
 *
 * §7 calls these "blueprint chips", but the artboard draws them WITHOUT corner
 * marks and we follow the artboard: at 150x56px four registration marks read as
 * noise rather than structure. "Blueprint" here means the square hairline frame.
 */
defineProps<{
    vehicles: {
        id: number;
        name: string;
        color: string | null;
        spec: string;
        is_active: boolean;
    }[];
}>();
</script>

<template>
    <div class="flex gap-(--space-3) overflow-x-auto pb-1">
        <Link
            v-for="vehicle in vehicles"
            :key="vehicle.id"
            :href="show(vehicle.id)"
            class="ease-standard flex w-[150px] flex-none flex-col gap-[5px] rounded-md border px-[13px] py-[11px] transition-colors duration-[var(--dur-fast)]"
            :class="
                vehicle.is_active
                    ? 'border-accent bg-accent-100'
                    : 'hover:border-accent bg-card border-(--hairline-soft)'
            "
            :style="{
                '--vehicle': vehicle.color ?? 'var(--color-neutral-400)',
            }"
            :aria-current="vehicle.is_active ? 'page' : undefined"
        >
            <span class="flex items-center gap-(--space-2)">
                <!-- §2 vehicle colour: a round swatch, ringed so white and silver still show. -->
                <span
                    class="size-2.5 flex-none rounded-full bg-(--vehicle) ring-1 ring-(--color-divider)"
                    aria-hidden="true"
                />
                <span
                    class="font-display truncate text-[17px]/none font-semibold text-(--color-text)"
                >
                    {{ vehicle.name }}
                </span>
            </span>
            <span class="truncate text-[11px] text-(--color-neutral-700)">
                {{ vehicle.spec }}
            </span>
        </Link>

        <Link
            :href="create()"
            class="ease-standard hover:border-accent hover:text-accent flex w-[54px] flex-none items-center justify-center rounded-md border border-dashed border-(--color-neutral-400) text-(--color-neutral-700) transition-colors duration-[var(--dur-fast)]"
        >
            <Plus class="size-[18px]" :stroke-width="1.5" />
            <span class="sr-only">Add a vehicle</span>
        </Link>
    </div>
</template>
