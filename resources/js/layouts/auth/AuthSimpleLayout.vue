<script setup lang="ts">
import ShellBrand from '@/components/shell/ShellBrand.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 px-5 py-10"
    >
        <div class="w-full max-w-[var(--content-max)]">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-6">
                    <ShellBrand orientation="stacked" :href="home()" />
                    <div class="space-y-2 text-center">
                        <h1 class="font-display text-h1">{{ title }}</h1>
                        <p class="text-muted-foreground text-body text-center">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
