<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { breadcrumbsFor, type CrumbProps } from '@/lib/breadcrumbs';

/*
 * Takes no props on purpose.
 *
 * The trail is a function of the page Inertia is currently showing, so it is read
 * from here rather than handed down. usePage() is reactive, which means every
 * visit — including a redirect back to the garage after a vehicle is deleted —
 * recomputes the trail instead of inheriting the previous page's.
 */
const page = usePage();

const breadcrumbs = computed(() =>
    breadcrumbsFor(page.component, page.props as unknown as CrumbProps),
);
</script>

<template>
    <Breadcrumb
        v-if="breadcrumbs.length"
        class="text-[12px] text-(--color-neutral-700)"
    >
        <BreadcrumbList class="gap-(--space-3) text-[12px] sm:gap-(--space-3)">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === breadcrumbs.length - 1">
                        <BreadcrumbPage
                            class="font-semibold text-(--color-text)"
                            >{{ item.title }}</BreadcrumbPage
                        >
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator
                    v-if="index !== breadcrumbs.length - 1"
                    class="text-(--color-neutral-400)"
                />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
