<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import FinanceCommandPalette from '@/components/finance/FinanceCommandPalette.vue';
import SemesterSwitcher from '@/components/finance/SemesterSwitcher.vue';
import NotificationPopper from '@/components/NotificationPopper.vue';
import StudentSearchDropdown from '@/components/StudentSearchDropdown.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import type { BreadcrumbItemType } from '@/types';
import { computed } from 'vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { canAny } = usePermissions();
const showFinanceShell = computed(() => canAny(['view_finance_student_overview', 'view_finance_audit_workspace', 'view_finance_operations_dashboard']));
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
            <div class="flex items-center gap-2">
                <template v-if="showFinanceShell">
                    <FinanceCommandPalette />
                    <SemesterSwitcher />
                </template>
                <NotificationPopper />
            </div>
        </div>
    </header>
</template>
