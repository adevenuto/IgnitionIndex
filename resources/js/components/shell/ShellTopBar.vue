<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Menu, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import ShellBrand from '@/components/shell/ShellBrand.vue';
import ShellUserMenu from '@/components/shell/ShellUserMenu.vue';
import { Button } from '@/components/ui/button';
import { notifications } from '@/routes';

/*
 * The top bar, 66px: near-white with a hairline under it (light since 2026-10).
 *
 * Design doc §5: the app's one primary action lives here, in the chrome, "not
 * floating in the content" — which is why the previous centre FAB is gone
 * rather than restyled.
 *
 * Below md the 194px brand column collapses to the logo alone, Log entry
 * becomes an icon button, and search collapses to a magnifier.
 */
defineEmits<{ add: []; openNav: [] }>();

const page = usePage();
const unread = computed(() => page.props.unreadNotifications ?? 0);

const search = ref('');
</script>

<template>
    <header
        class="flex h-[66px] flex-none items-center gap-[18px] border-b border-(--chrome-hairline) bg-(--chrome-bar) px-[22px]"
    >
        <button
            type="button"
            class="-ml-1 flex size-9 flex-none items-center justify-center md:hidden"
            :style="{ color: 'var(--chrome-ink)' }"
            @click="$emit('openNav')"
        >
            <Menu class="size-5" :stroke-width="1.6" />
            <span class="sr-only">Open navigation</span>
        </button>

        <ShellBrand class="hidden md:flex" />
        <ShellBrand compact class="md:hidden" />

        <!-- The app's one primary action, in the chrome (§5): the near-black primary. -->
        <Button
            class="h-10 flex-none gap-2 px-5 max-md:w-10 max-md:px-0 max-md:[&>span]:sr-only"
            @click="$emit('add')"
        >
            <Plus class="size-4" :stroke-width="1.8" />
            <span>Log entry</span>
        </Button>

        <!--
          A real field, but not yet wired to anything: there is no search
          endpoint. It submits nowhere and says so rather than appearing to
          work, which is why Enter is swallowed instead of reloading the page.
        -->
        <form
            class="ease-standard focus-within:border-accent bg-background hidden h-10 min-w-[250px] items-center gap-[9px] rounded-md border border-(--chrome-field-border) px-[14px] transition-colors duration-[var(--dur-fast)] lg:flex"
            role="search"
            @submit.prevent
        >
            <Search
                class="size-[15px] flex-none"
                :stroke-width="1.5"
                :style="{ color: 'var(--chrome-ink-meta)' }"
            />
            <input
                v-model="search"
                type="search"
                placeholder="Search services, parts, records"
                aria-label="Search services, parts, records"
                class="w-full bg-transparent text-[13px] outline-none placeholder:text-(--chrome-ink-meta)"
                :style="{ color: 'var(--chrome-ink)' }"
            />
        </form>

        <div class="flex-1" />

        <div class="flex items-center gap-(--space-6)">
            <Link :href="notifications()" class="relative flex">
                <Bell
                    class="size-[19px]"
                    :stroke-width="1.5"
                    :style="{ color: 'var(--chrome-ink)' }"
                />
                <span
                    v-if="unread"
                    class="absolute -top-[5px] -right-[7px] h-4 min-w-4 rounded-full px-1 text-center text-[10px]/[16px] font-bold text-white"
                    :style="{ background: 'var(--status-overdue)' }"
                >
                    {{ unread > 9 ? '9+' : unread }}
                </span>
                <span class="sr-only">
                    Notifications{{ unread ? `, ${unread} unread` : '' }}
                </span>
            </Link>

            <div
                class="hidden h-[26px] w-px md:block"
                :style="{ background: 'var(--chrome-hairline)' }"
                aria-hidden="true"
            />

            <ShellUserMenu />
        </div>
    </header>
</template>
