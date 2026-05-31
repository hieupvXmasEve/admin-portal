<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarRail } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { useSystemConfig } from '@/composables/useSystemConfig';
import { mainNavGroups } from '@/constants/menu-sidebar';
import { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import NavUser from './NavUser.vue';

const { filterMenuGroups } = usePermissions();
const { get } = useSystemConfig();
// Filter menu items based on user permissions
const filteredMenuGroups = computed(() => filterMenuGroups(mainNavGroups));
const page = usePage<SharedData>();
const campus = page.props.auth.current_campus;
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <div class="bg-background text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                                <AppLogo />
                            </div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ get('app_name') }}</span>
                                <span class="truncate text-xs">Campus: {{ campus.name }}</span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="filteredMenuGroups" />
        </SidebarContent>

        <SidebarFooter class="border-t border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
            <NavUser />
        </SidebarFooter>
        <SidebarRail />
    </Sidebar>
</template>

<style scoped>
/* Custom scrollbar for sidebar content */
:deep(.sidebar-content) {
    scrollbar-width: thin;
    scrollbar-color: rgb(203 213 225) transparent;
}

:deep(.sidebar-content::-webkit-scrollbar) {
    width: 4px;
}

:deep(.sidebar-content::-webkit-scrollbar-track) {
    background: transparent;
}

:deep(.sidebar-content::-webkit-scrollbar-thumb) {
    background-color: rgb(203 213 225);
    border-radius: 2px;
}

:deep(.sidebar-content::-webkit-scrollbar-thumb:hover) {
    background-color: rgb(148 163 184);
}

/* Dark mode scrollbar */
:deep(.dark .sidebar-content::-webkit-scrollbar-thumb) {
    background-color: rgb(71 85 105);
}

:deep(.dark .sidebar-content::-webkit-scrollbar-thumb:hover) {
    background-color: rgb(100 116 139);
}
</style>
