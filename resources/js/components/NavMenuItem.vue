<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronRight } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { NavItem } from '@/types'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from '@/components/ui/sidebar'
import NavMenuItem from '@/components/NavMenuItem.vue'

const props = defineProps<{
  item: NavItem
  level?: number
  activeHref?: string
}>()

const page = usePage()
// Default level to 0 if not provided
const currentLevel = computed(() => props.level ?? 0)
const isRoot = computed(() => currentLevel.value === 0)

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

// Check if this specific item is the active one
const isActive = computed(() => {
    if (!props.item.href || props.item.href === '#') return false;
    return props.item.href === props.activeHref;
});

// Check if any descendant is the active one
function hasActiveChild(item: NavItem): boolean {
    if (item.children) {
        return item.children.some(child => child.href === props.activeHref || hasActiveChild(child));
    }
    return false;
}

const isOpen = ref(false);

// Automatically open if a child is active OR there's a prefix match (for detail pages not in menu)
watch(() => [page.url, props.activeHref], () => {
    const isChildActive = hasActiveChild(props.item);
    
    // Also check if current URL starts with this item's path (for auto-opening on detail pages)
    const itemPath = normalizePath(props.item.href);
    const currentPath = normalizePath(page.url);
    const isPrefixMatch = itemPath && currentPath.startsWith(itemPath + '/');

    if (isChildActive || isPrefixMatch) {
        isOpen.value = true;
    }
}, { immediate: true });

</script>

<template>
  <!-- No Children -->
  <template v-if="!item.children?.length">
    <SidebarMenuItem v-if="isRoot">
      <SidebarMenuButton as-child :is-active="isActive" :tooltip="item.title">
        <Link :href="item.href">
          <component :is="item.icon" v-if="item.icon" />
          <span>{{ item.title }}</span>
        </Link>
      </SidebarMenuButton>
    </SidebarMenuItem>
    
    <SidebarMenuSubItem v-else>
      <SidebarMenuSubButton as-child :is-active="isActive">
        <Link :href="item.href">
          <!-- Only show icon if provided, usually subitems might not have icons or have smaller ones -->
          <component :is="item.icon" v-if="item.icon" />
          <span>{{ item.title }}</span>
        </Link>
      </SidebarMenuSubButton>
    </SidebarMenuSubItem>
  </template>

  <!-- With Children (Group/Collapsible) -->
  <template v-else>
    <Collapsible v-model:open="isOpen" class="group/collapsible" as-child>
      <SidebarMenuItem v-if="isRoot">
        <CollapsibleTrigger as-child>
          <SidebarMenuButton :tooltip="item.title" :is-active="isActive">
            <component :is="item.icon" v-if="item.icon" />
            <span>{{ item.title }}</span>
            <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
          </SidebarMenuButton>
        </CollapsibleTrigger>
        <CollapsibleContent>
            <SidebarMenuSub>
                <NavMenuItem 
                    v-for="child in item.children" 
                    :key="child.title" 
                    :item="child" 
                    :level="currentLevel + 1" 
                    :active-href="activeHref"
                />
            </SidebarMenuSub>
        </CollapsibleContent>
      </SidebarMenuItem>

      <SidebarMenuSubItem v-else>
        <CollapsibleTrigger as-child>
            <SidebarMenuSubButton :is-active="isActive">
                <component :is="item.icon" v-if="item.icon" />
                <span>{{ item.title }}</span>
                <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
            </SidebarMenuSubButton>
        </CollapsibleTrigger>
        <CollapsibleContent>
            <SidebarMenuSub>
                <NavMenuItem 
                    v-for="child in item.children" 
                    :key="child.title" 
                    :item="child" 
                    :level="currentLevel + 1" 
                    :active-href="activeHref"
                />
            </SidebarMenuSub>
        </CollapsibleContent>
      </SidebarMenuSubItem>
    </Collapsible>
  </template>
</template>
