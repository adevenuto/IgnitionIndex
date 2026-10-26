<script setup lang="ts">
import { Check, Type, X } from '@lucide/vue';
import { ref } from 'vue';
import {
    applyFont,
    currentFont,
    FONT_CHOICES,
    FONT_PICKER_KEY,
} from '@/lib/fonts';

/*
 * A small panel for trying the candidate type pairings on real screens,
 * shown on the homepage only (Welcome.vue). Each name is set
 * in its heading face and its description in its body face, so the list
 * itself is the preview. TEMPORARY — goes once a pairing is chosen.
 */
const active = ref(currentFont());
const open = ref(true);

function choose(id: string): void {
    applyFont(id);
    active.value = id;
}

function dismiss(): void {
    try {
        localStorage.removeItem(FONT_PICKER_KEY);
    } catch {
        // Nothing to forget.
    }

    open.value = false;
}
</script>

<template>
    <div
        class="fixed bottom-4 left-4 z-[100] font-sans"
        style="padding-bottom: env(safe-area-inset-bottom)"
    >
        <div
            v-if="open"
            class="bg-card text-foreground grid max-h-[min(70vh,560px)] w-72 gap-1 overflow-y-auto overscroll-contain rounded-lg border border-(--hairline-soft) p-2 shadow-lg"
            role="radiogroup"
            aria-label="Type pairing"
        >
            <div
                class="bg-card sticky -top-2 z-10 -mx-2 -mt-2 flex items-center justify-between px-4 pt-3 pb-1.5"
            >
                <span class="ii-eyebrow">Type pairing</span>
                <button
                    type="button"
                    class="hover:bg-hover-surface -mr-1 grid size-7 cursor-pointer place-items-center rounded-sm"
                    aria-label="Hide the type face picker"
                    title="Hide (bring back with ?fonts)"
                    @click="dismiss"
                >
                    <X class="size-4" :stroke-width="1.5" />
                </button>
            </div>

            <button
                v-for="font in FONT_CHOICES"
                :key="font.id"
                type="button"
                role="radio"
                :aria-checked="active === font.id"
                class="hover:bg-hover-surface flex cursor-pointer items-center justify-between gap-2 rounded-md px-2.5 py-2 text-left"
                :class="active === font.id && 'bg-hover-surface'"
                @click="choose(font.id)"
            >
                <span class="grid gap-0.5">
                    <span
                        class="text-[18px]/tight font-semibold"
                        :style="{ fontFamily: `var(${font.display})` }"
                        >{{ font.label }}</span
                    >
                    <span
                        class="text-[12px]/snug text-(--color-neutral-700)"
                        :style="{ fontFamily: `var(${font.body})` }"
                        >{{ font.kind }}</span
                    >
                </span>
                <Check
                    v-if="active === font.id"
                    class="size-4 shrink-0"
                    :stroke-width="1.5"
                />
            </button>
        </div>

        <button
            v-else
            type="button"
            class="bg-card grid size-10 cursor-pointer place-items-center rounded-full border border-(--hairline-soft) shadow-lg"
            aria-label="Show the type face picker"
            @click="open = true"
        >
            <Type class="size-4" :stroke-width="1.5" />
        </button>
    </div>
</template>
