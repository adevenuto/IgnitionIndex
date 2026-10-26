<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/*
 * Settings shell: a quiet nav card beside a content card.
 *
 * Both sit on white, because the design system's whole surface language is
 * white cards on the grey canvas — form fields floating directly on the canvas
 * had nothing to belong to.
 *
 * The sub-nav is deliberately colourless. The sidebar already marks "Settings"
 * with the brand pill, so repeating crimson here (a red pill AND red labels on
 * every item) meant four different reds competing on one screen. Brand colour
 * now appears once per view: on the primary action.
 */
const sidebarNavItems: NavItem[] = [
    { title: 'Profile', href: editProfile() },
    { title: 'Security', href: editSecurity() },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="mx-auto w-full max-w-[1400px] px-5 py-6">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
            <aside class="lg:w-56 lg:shrink-0">
                <Card class="py-2">
                    <CardContent class="px-2">
                        <nav class="flex flex-col gap-1" aria-label="Settings">
                            <Button
                                v-for="item in sidebarNavItems"
                                :key="toUrl(item.href)"
                                variant="ghost"
                                as-child
                                :class="[
                                    'h-11 w-full justify-start rounded-md px-3 text-[14px]',
                                    // The same near-black pill as the rail's active item.
                                    isCurrentOrParentUrl(item.href)
                                        ? 'bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground'
                                        : 'text-muted-foreground hover:bg-hover-surface hover:text-foreground',
                                ]"
                            >
                                <Link
                                    :href="item.href"
                                    :aria-current="
                                        isCurrentOrParentUrl(item.href)
                                            ? 'page'
                                            : undefined
                                    "
                                >
                                    <component :is="item.icon" class="size-4" />
                                    {{ item.title }}
                                </Link>
                            </Button>
                        </nav>
                    </CardContent>
                </Card>
            </aside>

            <div class="min-w-0 flex-1">
                <Card>
                    <CardContent class="max-w-xl space-y-10">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
