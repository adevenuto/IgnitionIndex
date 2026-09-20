<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, BellOff, Gauge, ShieldAlert, Wrench } from '@lucide/vue';
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

/*
 * Which notices carry an explicit call to action, and what it says.
 *
 * A due-service reminder deep-links into a pre-filled log form, where the card
 * itself being the link is enough. A setup nudge is asking for something the
 * reader has not done yet and may not realise is outstanding, so it gets a
 * button that names the task.
 */
const ACTIONS: Record<string, string> = {
    gauge_setup: 'Set up gauges',
};

function actionFor(item: Item): string | undefined {
    return ACTIONS[item.kind];
}

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
                <!--
                    A notice with its own call to action is NOT a link itself:
                    an <a> inside an <a> is invalid, and browsers resolve it by
                    dropping the inner one — which would be the button.
                -->
                <component
                    :is="item.url && !actionFor(item) ? Link : 'div'"
                    :href="
                        actionFor(item) ? undefined : (item.url ?? undefined)
                    "
                    class="block"
                    @click="actionFor(item) ? undefined : open(item)"
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
                                <Gauge
                                    v-else-if="item.kind === 'gauge_setup'"
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

                            <!--
                                Spelled out rather than leaving the whole card
                                quietly clickable: this notice exists to get
                                someone to do one specific thing, and "there is
                                a next step here" should not be something you
                                have to discover by hovering.

                                On the row rather than under it, in the space
                                the card already leaves empty; self-center holds
                                it against the text block whose own items are
                                top-aligned.
                            -->
                            <!--
                                Wears the same amber as the card it sends you
                                to, so the notice and its destination read as
                                one thing.
                            -->
                            <Button
                                v-if="actionFor(item) && item.url"
                                as-child
                                variant="outline"
                                class="ii-awaiting-setup group ml-auto shrink-0 self-center"
                            >
                                <!--
                                    One PATCH that marks read and redirects
                                    onward. Marking read as a second visit
                                    alongside a plain link cancels the
                                    navigation about half the time.
                                -->
                                <Link
                                    :href="`${update.url(item.id)}?go=1`"
                                    method="patch"
                                    as="button"
                                >
                                    {{ actionFor(item) }}
                                    <ArrowRight class="ii-arrow-nudge" />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </component>
            </li>
        </ul>
    </div>
</template>
