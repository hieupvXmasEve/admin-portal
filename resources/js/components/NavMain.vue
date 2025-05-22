<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

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
        if (child.href && getBaseUrl(child.href) === getBaseUrl(page.url)) return true;
        return hasActiveChild(child);
    });
}

function isOpen(item: NavItem) {
    // Chỉ mở nếu đã được toggle mở (true)
    return !!openItems.value[item.title];
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
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <SidebarMenuItem>
                    <SidebarMenuButton as-child :is-active="!item.children && getBaseUrl(item.href) === getBaseUrl(page.url)" :tooltip="item.title">
                        <Link v-if="!item.children" :href="item.href" :class="{ 'submenu-active': getBaseUrl(item.href) === getBaseUrl(page.url) }">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                        <div v-else class="flex cursor-pointer items-center" @click="toggle(item)">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                            <svg
                                class="ml-auto h-3 w-3 transition-transform"
                                :class="{ 'rotate-90': isOpen(item) }"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </SidebarMenuButton>

                    <!-- Đệ quy render submenu nếu có children -->
                    <transition name="fade">
                        <div v-if="item.children && isOpen(item)" class="ml-4 border-l border-gray-200 pl-2 dark:border-gray-700">
                            <NavMain :items="item.children" />
                        </div>
                    </transition>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: all 0.2s;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.submenu-active {
    color: #2563eb !important; /* Tailwind blue-600 */
    background: #f1f5f9 !important; /* Tailwind slate-100 */
    border-radius: 6px;
}
</style>
