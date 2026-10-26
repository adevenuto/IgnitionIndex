<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

/*
 * The user block, far right of the navy bar. Per design doc §5 this is also the
 * disclosure for Settings — there is no Settings item in the rail.
 *
 * Billing is named in the doc but has no route yet, so it is not rendered; a
 * disabled row that never becomes enabled is just noise.
 */
const page = usePage();

const user = computed(() => page.props.auth.user);

const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="flex cursor-pointer items-center gap-[11px] border-0 bg-transparent p-0 text-left"
        >
            <span
                class="font-display bg-accent flex size-[34px] flex-none items-center justify-center rounded-full text-[15px] font-semibold"
                :style="{ color: 'var(--shell-active-ink)' }"
                aria-hidden="true"
            >
                {{ initials }}
            </span>
            <span class="hidden leading-[1.2] md:block">
                <span
                    class="block text-[13px] font-medium"
                    :style="{ color: 'var(--shell-ink)' }"
                >
                    {{ user?.name }}
                </span>
                <span
                    class="block text-[11px]"
                    :style="{ color: 'var(--shell-ink-meta)' }"
                >
                    Personal garage
                </span>
            </span>
            <ChevronDown
                class="hidden size-[14px] md:block"
                :stroke-width="1.6"
                :style="{ color: 'var(--shell-ink-meta)' }"
            />
            <span class="sr-only">Account menu</span>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-56">
            <DropdownMenuItem as-child>
                <Link :href="editProfile()">
                    <Settings />
                    Settings
                </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link :href="logout()" method="post" as="button">
                    <LogOut />
                    Log out
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
