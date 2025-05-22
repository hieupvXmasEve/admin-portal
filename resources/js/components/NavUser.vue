<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type SharedData, type User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from 'lucide-vue-next';
import UserMenuContent from './UserMenuContent.vue';

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
const { isMobile, state } = useSidebar();
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="mx-1 rounded-lg border border-gray-200 bg-white transition-all duration-200 hover:bg-gray-50 hover:shadow-sm data-[state=open]:bg-gray-50 data-[state=open]:shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800 dark:data-[state=open]:bg-gray-800 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2"
                    >
                        <UserInfo :user="user" :show-email="false" class="group-data-[collapsible=icon]:justify-center" />
                        <ChevronsUpDown class="ml-auto h-4 w-4 text-gray-400 group-data-[collapsible=icon]:hidden" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                    :side="isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'bottom'"
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
