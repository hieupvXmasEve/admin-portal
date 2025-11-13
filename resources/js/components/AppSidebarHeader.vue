<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import StudentSearchDropdown from '@/components/StudentSearchDropdown.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { LucideBell } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="bg-background/80 supports-[backdrop-filter]:bg-background/60 border-sidebar-border/70 sticky top-0 z-10 flex h-16 shrink-0 items-center gap-2 border-b px-6 backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex w-full items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <SidebarTrigger class="-ml-1" />
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
                <StudentSearchDropdown />
            </div>
            <LucideBell class="text-muted-foreground hover:text-foreground size-5 cursor-pointer transition-colors" />
        </div>
    </header>
</template>
