<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { mainNavItems } from '@/constants/menu-sidebar';
import { Link } from '@inertiajs/vue3';
import { GraduationCap } from 'lucide-vue-next';
import NavUser from './NavUser.vue';
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <div
                                class="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg"
                            >
                                <GraduationCap class="size-4" />
                            </div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">Swinx</span>
                                <span class="truncate text-xs">Admin Panel</span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
            <div class="p-2">
                <SidebarSeparator class="my-2 bg-gray-200 group-data-[collapsible=icon]:hidden dark:bg-gray-700" />
                <NavUser />
            </div>
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
