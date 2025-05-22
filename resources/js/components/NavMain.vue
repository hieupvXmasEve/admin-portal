<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();
// State để lưu menu đang mở
const openItems = ref<Record<string, boolean>>({});

// Helper function to get base URL without query parameters
function getBaseUrl(url: string): string {
    return url.split('?')[0];
}

function toggle(item: NavItem) {
    openItems.value[item.title] = !openItems.value[item.title];
}

function hasActiveChild(item: NavItem): boolean {
    if (!item.children) return false;
    return item.children.some((child) => {
        if (child.href && child.href !== '#' && getBaseUrl(child.href) === getBaseUrl(page.url)) return true;
        return hasActiveChild(child);
    });
}

function isOpen(item: NavItem) {
    // Chỉ mở nếu đã được toggle mở (true)
    return !!openItems.value[item.title];
}

function isActive(item: NavItem): boolean {
    if (!item.href || item.href === '#') return false;
    return getBaseUrl(item.href) === getBaseUrl(page.url);
}

// Tự động mở menu cha khi route thay đổi
import { watch } from 'vue';

watch(
    () => page.url,
    () => {
        // Reset trạng thái open
        openItems.value = {};

        function autoOpen(items: NavItem[]) {
            items.forEach((item) => {
                if (item.children && hasActiveChild(item)) {
                    openItems.value[item.title] = true;
                    autoOpen(item.children);
                }
            });
        }

        autoOpen(props.items);
    },
    { immediate: true },
);
</script>

<template>
    <SidebarGroup class="px-0 py-0">
        <SidebarMenu class="space-y-1">
            <template v-for="item in items" :key="item.title">
                <SidebarMenuItem>
                    <!-- Single menu item without children -->
                    <SidebarMenuButton
                        v-if="!item.children"
                        as-child
                        :is-active="isActive(item)"
                        class="mx-1 rounded-lg transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-800 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2"
                        :class="{
                            'bg-primary/10 text-primary border-r-2 border-primary font-medium': isActive(item),
                            'text-gray-700 dark:text-gray-300': !isActive(item)
                        }"
                    >
                        <Link :href="item.href" class="flex items-center gap-3 px-3 py-2.5 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2">
                            <div class="flex h-5 w-5 items-center justify-center shrink-0">
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <span class="text-sm font-medium group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>

                    <!-- Parent menu item with children -->
                    <SidebarMenuButton
                        v-else
                        class="mx-1 rounded-lg transition-all duration-200 hover:bg-gray-100 dark:hover:bg-gray-800 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2"
                        :class="{
                            'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-100': isOpen(item) || hasActiveChild(item),
                            'text-gray-700 dark:text-gray-300': !isOpen(item) && !hasActiveChild(item)
                        }"
                        @click="toggle(item)"
                    >
                        <div class="flex w-full items-center gap-3 px-3 py-2.5 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2">
                            <div class="flex h-5 w-5 items-center justify-center shrink-0">
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <span class="flex-1 text-sm font-medium group-data-[collapsible=icon]:hidden">{{ item.title }}</span>
                            <ChevronRight
                                class="h-4 w-4 transition-transform duration-200 shrink-0 group-data-[collapsible=icon]:hidden"
                                :class="{ 'rotate-90': isOpen(item) }"
                            />
                        </div>
                    </SidebarMenuButton>

                    <!-- Submenu - Hidden when collapsed -->
                    <transition
                        name="submenu"
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-96"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-96"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <SidebarMenuSub v-if="item.children && isOpen(item)" class="mt-1 overflow-hidden group-data-[collapsible=icon]:hidden">
                            <template v-for="subItem in item.children" :key="subItem.title">
                                <SidebarMenuSubItem>
                                    <!-- Submenu item without children (direct link) -->
                                    <SidebarMenuSubButton
                                        v-if="!subItem.children"
                                        as-child
                                        :is-active="isActive(subItem)"
                                        class="mx-1 rounded-md transition-all duration-200 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                        :class="{
                                            'bg-primary/5 text-primary font-medium': isActive(subItem),
                                            'text-gray-600 dark:text-gray-400': !isActive(subItem)
                                        }"
                                    >
                                        <Link :href="subItem.href" class="flex items-center py-2 pl-11 pr-3">
                                            <span class="text-sm">{{ subItem.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>

                                    <!-- Submenu item with children (expandable dropdown) -->
                                    <SidebarMenuSubButton
                                        v-else
                                        class="mx-1 rounded-md transition-all duration-200 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                        :class="{
                                            'bg-gray-50 text-gray-900 dark:bg-gray-800/50 dark:text-gray-100': isOpen(subItem) || hasActiveChild(subItem),
                                            'text-gray-600 dark:text-gray-400': !isOpen(subItem) && !hasActiveChild(subItem)
                                        }"
                                        @click="toggle(subItem)"
                                    >
                                        <div class="flex w-full items-center py-2 pl-11 pr-3">
                                            <span class="flex-1 text-sm">{{ subItem.title }}</span>
                                            <ChevronRight
                                                class="h-3 w-3 transition-transform duration-200 shrink-0"
                                                :class="{ 'rotate-90': isOpen(subItem) }"
                                            />
                                        </div>
                                    </SidebarMenuSubButton>

                                    <!-- Nested submenu for third level -->
                                    <transition
                                        name="submenu"
                                        enter-active-class="transition-all duration-200 ease-out"
                                        enter-from-class="opacity-0 max-h-0"
                                        enter-to-class="opacity-100 max-h-96"
                                        leave-active-class="transition-all duration-200 ease-in"
                                        leave-from-class="opacity-100 max-h-96"
                                        leave-to-class="opacity-0 max-h-0"
                                    >
                                        <div v-if="subItem.children && isOpen(subItem)" class="mt-1 overflow-hidden">
                                            <template v-for="nestedItem in subItem.children" :key="nestedItem.title">
                                                <SidebarMenuSubButton
                                                    as-child
                                                    :is-active="isActive(nestedItem)"
                                                    class="mx-1 mb-1 rounded-md transition-all duration-200 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                                    :class="{
                                                        'bg-primary/5 text-primary font-medium': isActive(nestedItem),
                                                        'text-gray-600 dark:text-gray-400': !isActive(nestedItem)
                                                    }"
                                                >
                                                    <Link :href="nestedItem.href" class="flex items-center py-1.5 pl-16 pr-3">
                                                        <span class="text-xs">{{ nestedItem.title }}</span>
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </template>
                                        </div>
                                    </transition>
                                </SidebarMenuSubItem>
                            </template>
                        </SidebarMenuSub>
                    </transition>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>

<style scoped>
/* Smooth transitions for submenu */
.submenu-enter-active,
.submenu-leave-active {
    transition: all 0.2s ease;
}

.submenu-enter-from,
.submenu-leave-to {
    opacity: 0;
    max-height: 0;
}

.submenu-enter-to,
.submenu-leave-from {
    opacity: 1;
    max-height: 200px;
}
</style>
