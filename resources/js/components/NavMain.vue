<script setup lang="ts">
import { SidebarGroup, SidebarMenu } from '@/components/ui/sidebar';
import NavMenuItem from '@/components/NavMenuItem.vue';
import type { NavItem } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage();

function normalizePath(path: string) {
    if (!path || path === '#') return '';
    try {
        const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
        const url = new URL(path, base);
        return url.pathname;
    } catch {
        return path.split('?')[0];
    }
}

// Global best match calculation
const activeHref = computed(() => {
    const currentPath = normalizePath(page.url);
    let bestMatch = '';
    
    const findBestMatch = (items: NavItem[]) => {
        for (const item of items) {
            const itemPath = normalizePath(item.href);
            
            if (itemPath) {
                // Exact match found
                if (currentPath === itemPath) {
                    bestMatch = item.href;
                    return true;
                }
                
                // Prefix match: current path starts with target + /
                // Example: /forms/admin/runs starts with /forms/admin/
                if (currentPath.startsWith(itemPath + '/')) {
                    // Update if this match is longer (more specific)
                    if (!bestMatch || itemPath.length > normalizePath(bestMatch).length) {
                        bestMatch = item.href;
                    }
                }
            }
            
            if (item.children?.length) {
                if (findBestMatch(item.children)) return true;
            }
        }
        return false;
    };
    
    findBestMatch(props.items);
    return bestMatch;
});
</script>

<template>
    <SidebarGroup>
        <SidebarMenu>
            <NavMenuItem 
                v-for="item in items" 
                :key="item.title" 
                :item="item" 
                :active-href="activeHref"
            />
        </SidebarMenu>
    </SidebarGroup>
</template>
