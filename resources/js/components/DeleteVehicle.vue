<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import VehicleController from '@/actions/App/Http/Controllers/VehicleController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';

/*
 * Removing a car, from the edit page where the rest of its settings live.
 *
 * No password step, unlike DeleteUser: this is one vehicle, not the account, and
 * a password prompt on every removal trains people to type it without reading.
 * The dialog names the car instead, which is the mistake actually worth
 * catching — the wrong one selected in a multi-car garage.
 */
defineProps<{
    vehicle: { id: number };
    name: string;
}>();
</script>

<template>
    <section class="space-y-4">
        <Separator />

        <Heading
            variant="small"
            title="Remove vehicle"
            description="Take this car out of your garage"
        />

        <!--
            No tinted warning panel, matching DeleteUser: the destructive button
            carries the warning and the copy says plainly what is lost.
        -->
        <p class="text-[13px] text-(--color-neutral-700)">
            This cannot be undone. Every logged entry, photo and gauge for this
            car is permanently removed along with it. Your other vehicles are
            untouched.
        </p>

        <div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive" data-test="remove-vehicle">
                        <Trash2 :stroke-width="1.5" />
                        Remove vehicle
                    </Button>
                </DialogTrigger>

                <DialogContent>
                    <Form
                        v-bind="VehicleController.destroy.form(vehicle.id)"
                        class="space-y-6"
                        v-slot="{ processing }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Remove {{ name }}?</DialogTitle>
                            <DialogDescription>
                                This permanently removes {{ name }} and
                                everything logged against it — entries, photos
                                and gauge history. This cannot be undone.
                            </DialogDescription>
                        </DialogHeader>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                data-test="confirm-remove-vehicle"
                            >
                                Remove vehicle
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </section>
</template>
