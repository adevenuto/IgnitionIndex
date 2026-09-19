<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import VehicleFields from '@/components/VehicleFields.vue';
import type { PaletteColor } from '@/components/VehicleColorPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/actions/App/Http/Controllers/VehicleController';

defineProps<{
    makes: Record<string, string[]>;
    colors: PaletteColor[];
    minYear: number;
}>();
</script>

<template>
    <Head title="Add a Vehicle" />

    <div
        class="mx-auto w-full max-w-[var(--content-max)] px-5 pb-8 md:max-w-2xl md:pb-0"
    >
        <h1 class="text-h1 mb-1">Add a Vehicle</h1>
        <p class="text-muted-foreground text-body mb-6">
            Only the current odometer is required. Everything else you can fill
            in later.
        </p>

        <Form
            v-bind="store.form()"
            class="flex flex-col gap-6"
            show-progress
            v-slot="{ errors, processing }"
        >
            <VehicleFields
                :makes="makes"
                :colors="colors"
                :min-year="minYear"
                :errors="errors"
            />

            <div class="grid gap-1.5">
                <Label for="odometer">Current odometer</Label>
                <Input
                    id="odometer"
                    name="odometer"
                    type="number"
                    inputmode="numeric"
                    required
                    placeholder="47320"
                />
                <p class="text-muted-foreground text-xs">
                    This becomes your first logged reading.
                </p>
                <InputError :message="errors.odometer" />
            </div>

            <!--
                Sticky on desktop only: on mobile the bottom nav and FAB already
                occupy that space, and a second fixed bar would collide.

                data-flush-bottom drops the scroll container's bottom padding
                (see AppShell) so the stuck bar reaches the bottom edge of the
                window.
            -->
            <div
                data-flush-bottom
                class="bg-background md:border-border -mx-5 flex justify-end gap-3 px-5 md:sticky md:bottom-0 md:z-10 md:border-t md:py-4"
            >
                <Button type="submit" size="lg" :disabled="processing">
                    Add Vehicle
                </Button>
            </div>
        </Form>
    </div>
</template>
