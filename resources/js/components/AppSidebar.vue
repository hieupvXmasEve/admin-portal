<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { usePermission } from '@/composables/usePermission';
import { mainNavItems } from '@/constants/menu-sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, HelpCircle, Settings, Plus, BarChart3 } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const footerNavItems: NavItem[] = [
    {
        title: 'Settings',
        href: '/settings',
        icon: Settings,
    },
    {
        title: 'Help & Support',
        href: '/help',
        icon: HelpCircle,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

const { can } = usePermission();
console.log('permissions', can('view_user'));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
        <SidebarHeader class="border-b border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="hover:bg-gray-100 dark:hover:bg-gray-800">
                        <Link :href="route('dashboard')" class="flex items-center gap-3 px-3 py-4">
                            <div class="bg-primary text-primary-foreground flex h-8 w-8 items-center justify-center rounded-lg">
                                <AppLogo class="h-5 w-5" />
                            </div>
                            <div class="flex flex-col group-data-[collapsible=icon]:hidden">
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">Swinx</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Admin Panel</span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-2 py-4">
            <!-- Main Navigation Section -->
            <div class="mb-6">
                <div class="mb-2 px-3 group-data-[collapsible=icon]:hidden">
                    <h3 class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Main Menu</h3>
                </div>
                <NavMain :items="mainNavItems" />
            </div>

            <SidebarSeparator class="mx-3 bg-gray-200 dark:bg-gray-700 group-data-[collapsible=icon]:hidden" />

            <!-- Quick Actions Section -->
            <div class="mt-6">
                <div class="mb-2 px-3 group-data-[collapsible=icon]:hidden">
                    <h3 class="text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">Quick Actions</h3>
                </div>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child class="mx-1 hover:bg-blue-50 hover:text-blue-700 dark:hover:bg-blue-950 dark:hover:text-blue-300">
                            <Link href="/users/create" class="flex items-center gap-3 px-3 py-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2">
                                <div class="flex h-6 w-6 items-center justify-center rounded bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400 group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:text-current">
                                    <Plus class="h-4 w-4" />
                                </div>
                                <span class="text-sm group-data-[collapsible=icon]:hidden">Add User</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            class="mx-1 hover:bg-green-50 hover:text-green-700 dark:hover:bg-green-950 dark:hover:text-green-300"
                        >
                            <Link href="/reports" class="flex items-center gap-3 px-3 py-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2">
                                <div class="flex h-6 w-6 items-center justify-center rounded bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400 group-data-[collapsible=icon]:bg-transparent group-data-[collapsible=icon]:text-current">
                                    <BarChart3 class="h-4 w-4" />
                                </div>
                                <span class="text-sm group-data-[collapsible=icon]:hidden">View Reports</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </div>
        </SidebarContent>

        <SidebarFooter class="border-t border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-900/50">
            <div class="p-2">
                <NavFooter :items="footerNavItems" />
                <SidebarSeparator class="my-2 bg-gray-200 dark:bg-gray-700 group-data-[collapsible=icon]:hidden" />
                <NavUser />
            </div>
        </SidebarFooter>
    </Sidebar>
    <slot />
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
