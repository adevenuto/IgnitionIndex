<script setup lang="ts">
import { ref, watch } from 'vue';
import { BlueprintFrame } from '@/components/ui/blueprint';
import { Spinner } from '@/components/ui/spinner';

/*
 * The photo being read, shown back while the read is in flight — a few
 * seconds that would otherwise be a spinner and a held breath.
 *
 * Drawn as the instrument would: the duotone treatment every photo gets, a
 * steel line passing over it the way a scanner's would, and a ruler down the
 * edge. The line moves by position only (§4 forbids deformation) and only for
 * those who allow motion; otherwise it rests across the middle.
 */
const props = defineProps<{ file: File }>();

const url = ref('');

// Revoked when the file changes and when the overlay goes, so no photo
// lingers in memory once its read is done.
watch(
    () => props.file,
    (file, _, onCleanup) => {
        const next = URL.createObjectURL(file);
        url.value = next;
        onCleanup(() => URL.revokeObjectURL(next));
    },
    { immediate: true },
);
</script>

<template>
    <div class="grid gap-(--space-3)" role="status">
        <BlueprintFrame class="p-0">
            <div class="ii-duotone relative aspect-[4/3] overflow-hidden">
                <img
                    :src="url"
                    alt="The photo being read"
                    class="size-full object-cover"
                />

                <!-- A ruler down the edge, every tenth of the height. -->
                <div
                    class="absolute inset-y-0 left-0 w-3 bg-[repeating-linear-gradient(to_bottom,rgb(245_245_248)_0_1px,transparent_1px_10%)] opacity-80"
                    aria-hidden="true"
                />

                <!--
                  The sweep: a layer the height of the photo with the line at
                  its top, moved down by its own height. Without motion it
                  rests halfway.
                -->
                <div
                    class="motion-safe:animate-capture-scan absolute inset-0 translate-y-1/2"
                    aria-hidden="true"
                >
                    <div
                        class="absolute inset-x-0 top-0 h-10 -translate-y-full bg-linear-to-b from-transparent to-(--color-accent)/30"
                    />
                    <div class="bg-accent absolute inset-x-0 top-0 h-0.5" />
                </div>
            </div>
        </BlueprintFrame>

        <p class="ii-eyebrow flex items-center gap-2">
            <Spinner class="size-3.5" />
            Reading the photo
        </p>
    </div>
</template>
