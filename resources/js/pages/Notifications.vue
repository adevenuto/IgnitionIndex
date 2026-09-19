<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BellOff, ShieldAlert, Wrench } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { readAll, update } from '@/routes/notifications';

/*
 * Everything the app has told this user, newest first.
 *
 * The email is the nudge; this is the record — a reminder that arrives while
 * you're busy is otherwise gone, with nothing to come back to.
 */
type Item = {
    id: string;
    read: boolean;
    created_at: string | null;
    kind: string;
    vehicle_name: string | null;
    title: string;
    detail: string | null;
    url: string | null;
};

defineProps<{
    notifications: { data: Item[]; total: number };
    unreadCount: number;
}>();

function open(item: Item): void {
    if (!item.read) {
        router.patch(update.url(item.id), {}, { preserveScroll: true });
    }
}

function formatWhen(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
}
</script>

<template>
    <Head title="Notifications" />

    <EmptyState
        v-if="notifications.total === 0"
        :icon="BellOff"
        title="Nothing Yet"
        description="Reminders about services coming due, and any safety recalls on your vehicles, will show up here."
    />

    <div
        v-else
        class="mx-auto flex w-full max-w-[1400px] flex-col gap-(--space-4)"
    >
        <div class="flex items-center justify-between">
            <p class="text-muted-foreground text-sm">
                {{ unreadCount ? `${unreadCount} unread` : 'All caught up' }}
            </p>

            <Button
                v-if="unreadCount"
                variant="ghost"
                size="sm"
                class="text-muted-foreground hover:text-foreground"
                @click="
                    router.patch(readAll.url(), {}, { preserveScroll: true })
                "
            >
                Mark all read
            </Button>
        </div>

        <ul role="list" class="flex flex-col gap-3">
            <li v-for="item in notifications.data" :key="item.id">
                <component
                    :is="item.url ? Link : 'div'"
                    :href="item.url ?? undefined"
                    class="block"
                    @click="open(item)"
                >
                    <Card :class="item.read ? '' : 'ring-primary/30 ring-1'">
                        <CardContent class="flex items-start gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center border border-(--color-divider) bg-(--color-accent-100) text-(--color-accent-700)"
                            >
                                <ShieldAlert
                                    v-if="item.kind === 'recall'"
                                    class="size-5"
                                />
                                <Wrench v-else class="size-5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-title">{{ item.title }}</p>
                                    <Badge v-if="!item.read">New</Badge>
                                </div>

                                <p class="text-muted-foreground text-sm">
                                    <span v-if="item.vehicle_name">
                                        {{ item.vehicle_name }}
                                    </span>
                                    <template v-if="item.detail">
                                        &middot; {{ item.detail }}
                                    </template>
                                </p>

                                <p class="text-muted-foreground mt-1 text-xs">
                                    {{ formatWhen(item.created_at) }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </component>
            </li>
        </ul>
    </div>
</template>
