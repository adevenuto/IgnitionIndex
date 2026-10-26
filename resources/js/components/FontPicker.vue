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
 * A small panel for trying the candidate type faces on real screens. It lives
 * outside the Inertia app (mounted separately in app.ts) so it survives page
 * visits and works on the landing and auth pages too. Each name is set in its
 * own face so the list itself is the preview.
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
            class="bg-card text-foreground grid w-60 gap-1 rounded-lg border border-(--hairline-soft) p-2 shadow-lg"
            role="radiogroup"
            aria-label="Type face"
        >
            <div class="flex items-center justify-between px-2 pt-1 pb-1.5">
                <span class="ii-eyebrow">Type face</span>
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
                class="hover:bg-hover-surface flex cursor-pointer items-center justify-between rounded-md px-2.5 py-2 text-left text-[15px]"
                :class="active === font.id && 'bg-hover-surface font-semibold'"
                :style="{ fontFamily: `var(--font-${font.id})` }"
                @click="choose(font.id)"
            >
                {{ font.label }}
                <Check
                    v-if="active === font.id"
                    class="size-4"
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
