<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref } from 'vue';
import AppearanceController from '@/actions/App/Http/Controllers/Settings/AppearanceController';
import Heading from '@/components/Heading.vue';
import type { ThemeOption } from '@/lib/theme';
import { applyTheme } from '@/lib/theme';

/*
 * Settings → Appearance: the colour theme.
 *
 * A choice applies the moment it is made — the whole app recolours behind the
 * page — and is saved to the account in the background, so it follows the
 * person to their other devices. Each option is drawn as a small picture of
 * the app in that theme, from the theme's own swatches, so the list is the
 * preview.
 */
const props = defineProps<{
    themes: ThemeOption[];
    current: string;
    default: string;
}>();

const selected = ref(props.current);
const saving = ref(false);

function choose(theme: ThemeOption): void {
    if (theme.value === selected.value) {
        return;
    }

    selected.value = theme.value;
    applyTheme(theme);
    saving.value = true;

    router.patch(
        AppearanceController.update.url(),
        { theme: theme.value },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (saving.value = false),
        },
    );
}
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Theme"
            description="Choose how Ignition Index looks. It's saved to your account, on every device."
        />

        <div
            class="grid gap-(--space-4) sm:grid-cols-2 xl:grid-cols-3"
            role="radiogroup"
            aria-label="Theme"
        >
            <button
                v-for="theme in themes"
                :key="theme.value"
                type="button"
                role="radio"
                :aria-checked="selected === theme.value"
                class="group focus-visible:ring-ring/50 grid cursor-pointer gap-(--space-3) rounded-lg border p-(--space-3) text-left transition-colors duration-[var(--dur-fast)] outline-none focus-visible:ring-[3px]"
                :class="
                    selected === theme.value
                        ? 'border-accent bg-accent-100'
                        : 'hover:border-accent bg-card border-(--hairline-soft)'
                "
                @click="choose(theme)"
            >
                <!-- A small picture of the app in this theme: rail, a card, the main button. -->
                <span
                    class="flex h-24 overflow-hidden rounded-md ring-1 ring-(--hairline-soft)"
                    :style="{ background: theme.swatches.ground }"
                    aria-hidden="true"
                >
                    <span
                        class="flex w-1/4 flex-col gap-1.5 p-2"
                        :style="{ background: theme.swatches.card }"
                    >
                        <span
                            class="h-2 w-full rounded-sm"
                            :style="{ background: theme.swatches.accent }"
                        />
                        <span
                            class="h-1.5 w-3/4 rounded-sm opacity-40"
                            :style="{ background: theme.swatches.muted }"
                        />
                        <span
                            class="h-1.5 w-2/3 rounded-sm opacity-40"
                            :style="{ background: theme.swatches.muted }"
                        />
                    </span>
                    <span class="flex flex-1 flex-col gap-2 p-2.5">
                        <span
                            class="flex flex-1 flex-col gap-1.5 rounded-md p-2"
                            :style="{ background: theme.swatches.card }"
                        >
                            <span
                                class="h-2 w-1/2 rounded-sm"
                                :style="{ background: theme.swatches.text }"
                            />
                            <span
                                class="h-1.5 w-3/4 rounded-sm opacity-50"
                                :style="{ background: theme.swatches.muted }"
                            />
                        </span>
                        <span
                            class="h-4 w-16 self-end rounded-sm"
                            :style="{ background: theme.swatches.accent }"
                        />
                    </span>
                </span>

                <span class="flex items-start justify-between gap-2">
                    <span class="grid gap-0.5">
                        <span class="flex items-center gap-2">
                            <span
                                class="font-display text-[15px] font-semibold"
                            >
                                {{ theme.label }}
                            </span>
                            <span
                                v-if="theme.value === props.default"
                                class="ii-eyebrow bg-muted rounded-full px-2 py-0.5"
                                >Default</span
                            >
                        </span>
                        <span class="text-sm text-(--color-neutral-700)">
                            {{ theme.description }}
                        </span>
                    </span>
                    <Check
                        v-if="selected === theme.value"
                        class="text-accent-700 mt-0.5 size-4 shrink-0"
                        :stroke-width="2"
                    />
                </span>
            </button>
        </div>

        <p class="text-sm text-(--color-neutral-700)" aria-live="polite">
            {{ saving ? 'Saving…' : 'Saved to your account.' }}
        </p>
    </div>
</template>
