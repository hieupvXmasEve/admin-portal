<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu class="space-y-1">
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        class="mx-1 rounded-lg text-gray-600 transition-all duration-200 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2"
                        as-child
                    >
                        <component
                            :is="item.href.startsWith('http') ? 'a' : Link"
                            :href="item.href"
                            :target="item.href.startsWith('http') ? '_blank' : undefined"
                            :rel="item.href.startsWith('http') ? 'noopener noreferrer' : undefined"
                            class="flex items-center gap-3 px-3 py-2 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2"
                        >
                            <div class="flex h-4 w-4 items-center justify-center">
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <span class="text-sm group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                            <svg
                                v-if="item.href.startsWith('http')"
                                class="ml-auto h-3 w-3 opacity-50 group-data-[collapsible=icon]:hidden"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </component>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
