# Collapsible Sidebar Component

A clean, collapsible sidebar navigation component built with **shadcn-vue** and **Tailwind CSS** for Laravel Vue applications, featuring collapsible menu groups.

## Features

- ✅ **Collapsible menu groups** - Organize navigation into logical sections
- ✅ **Auto-expand active groups** - Groups with active items expand automatically
- ✅ **Collapsible sidebar** with icon-only mode
- ✅ **Active state management** with visual indicators
- ✅ **Clean, modern design** with Tailwind CSS
- ✅ **Simple reactive patterns** - No complex watch logic
- ✅ **Permission-based menu items** (optional)
- ✅ **Standard ShadCN implementation** - follows official patterns
- ✅ **Smooth animations** - Chevron rotation and content transitions

## Menu Structure

Organized into collapsible groups:
```
Dashboard
Academic ▼
  └── Programs
  └── Semesters
  └── Units
User Management ▼
  └── Students
  └── Lecturers
  └── All Users
System ▼
  └── Roles
  └── Reports
  └── Messages
  └── Settings
```

## Components Used

### Core shadcn-vue Components
- `Sidebar` - Main sidebar container
- `SidebarProvider` - Context provider for sidebar state
- `SidebarContent` - Scrollable content area
- `SidebarHeader` - Header section
- `SidebarFooter` - Footer section
- `SidebarGroup` - Menu group container
- `SidebarGroupLabel` - Group label
- `SidebarGroupContent` - Group content wrapper
- `SidebarMenu` - Menu container
- `SidebarMenuItem` - Individual menu item
- `SidebarMenuButton` - Clickable menu button
- `SidebarMenuSub` - Submenu container
- `SidebarMenuSubItem` - Submenu item
- `SidebarMenuSubButton` - Submenu button
- `SidebarTrigger` - Toggle button

### Additional Components
- `Collapsible` - For expandable menu sections
- `CollapsibleContent` - Collapsible content wrapper
- `CollapsibleTrigger` - Collapsible trigger button

## Installation

1. **Install shadcn-vue sidebar component:**
```bash
npx shadcn-vue@latest add sidebar
```

2. **Install collapsible component:**
```bash
npx shadcn-vue@latest add collapsible
```

3. **Add required CSS variables to your CSS file:**
```css
@layer base {
  :root {
    --sidebar-background: 0 0% 98%;
    --sidebar-foreground: 240 5.3% 26.1%;
    --sidebar-primary: 240 5.9% 10%;
    --sidebar-primary-foreground: 0 0% 98%;
    --sidebar-accent: 240 4.8% 95.9%;
    --sidebar-accent-foreground: 240 5.9% 10%;
    --sidebar-border: 220 13% 91%;
    --sidebar-ring: 217.2 91.2% 59.8%;
  }

  .dark {
    --sidebar-background: 240 5.9% 10%;
    --sidebar-foreground: 240 4.8% 95.9%;
    --sidebar-primary: 0 0% 98%;
    --sidebar-primary-foreground: 240 5.9% 10%;
    --sidebar-accent: 240 3.7% 15.9%;
    --sidebar-accent-foreground: 240 4.8% 95.9%;
    --sidebar-border: 240 3.7% 15.9%;
    --sidebar-ring: 217.2 91.2% 59.8%;
  }
}
```

## Usage

### 1. Define Menu Structure

Create your menu items with the `NavItem` interface:

```typescript
// types/index.d.ts
export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
    children?: NavItem[];
    requiredPermissions?: string[];
}
```

```typescript
// constants/menu-sidebar.ts
import type { NavItem } from '@/types';
import { LayoutDashboard, GraduationCap, Users, Settings } from 'lucide-vue-next';

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'Academic',
        href: '#',
        icon: GraduationCap,
        children: [
            {
                title: 'Programs',
                href: '/programs',
                icon: BookOpen,
                requiredPermissions: ['view_program'],
            },
            {
                title: 'Semesters',
                href: '/semesters',
                icon: Calendar,
                requiredPermissions: ['view_semester'],
            },
        ],
    },
    {
        title: 'User Management',
        href: '#',
        icon: Users,
        children: [
            {
                title: 'Students',
                href: '/students',
                icon: GraduationCap,
                requiredPermissions: ['view_student'],
            },
            {
                title: 'Lecturers',
                href: '/lecturers',
                icon: Users,
                requiredPermissions: ['view_lecturer'],
            },
        ],
    },
];
```

### 2. NavMain Component

```vue
<!-- NavMain.vue -->
<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();

function getBaseUrl(url: string): string {
    return url.split('?')[0];
}

function isActive(item: NavItem): boolean {
    if (!item.href || item.href === '#') return false;
    return getBaseUrl(item.href) === getBaseUrl(page.url);
}

function hasActiveChild(item: NavItem): boolean {
    if (!item.children) return false;
    return item.children.some((child) => {
        if (child.href && child.href !== '#' && getBaseUrl(child.href) === getBaseUrl(page.url)) return true;
        return hasActiveChild(child);
    });
}
</script>

<template>
    <SidebarGroup>
        <SidebarGroupLabel>Application</SidebarGroupLabel>
        <SidebarGroupContent>
            <SidebarMenu>
                <template v-for="item in items" :key="item.title">
                    <!-- Single menu item without children -->
                    <SidebarMenuItem v-if="!item.children">
                        <SidebarMenuButton as-child :is-active="isActive(item)">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>

                    <!-- Collapsible menu group with children -->
                    <Collapsible v-else :default-open="hasActiveChild(item)" class="group/collapsible">
                        <SidebarMenuItem>
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton :tooltip="item.title">
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                    <ChevronRight class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem v-for="subItem in item.children" :key="subItem.title">
                                        <SidebarMenuSubButton as-child :is-active="isActive(subItem)">
                                            <Link :href="subItem.href">
                                                <component :is="subItem.icon" />
                                                <span>{{ subItem.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>
                </template>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
```

### 3. Use the AppSidebar Component

```vue
<!-- AppSidebar.vue -->
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
} from '@/components/ui/sidebar';
import { mainNavItems } from '@/constants/menu-sidebar';
import { Link } from '@inertiajs/vue3';
import { GraduationCap, User } from 'lucide-vue-next';
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
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
        
        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton>
                        <User class="size-4" />
                        <span>User Account</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
</template>
```

### 4. Wrap Your App with SidebarProvider

```vue
<!-- App.vue or Layout.vue -->
<script setup lang="ts">
import AppSidebar from '@/components/AppSidebar.vue';
import { SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar';
</script>

<template>
    <SidebarProvider>
        <AppSidebar />
        <main>
            <SidebarTrigger />
            <RouterView />
        </main>
    </SidebarProvider>
</template>
```

## Key Features Explained

### 1. Collapsible Menu Groups

The component organizes navigation into logical collapsible groups:
- **Academic** - Programs, Semesters, Units
- **User Management** - Students, Lecturers, All Users
- **System** - Roles, Reports, Messages, Settings

### 2. Auto-Expand Active Groups

- Groups containing active menu items automatically expand
- Uses `hasActiveChild()` function to detect active children
- `default-open` prop on Collapsible component handles initial state

### 3. Smooth Animations

- Chevron icon rotates 90 degrees when group expands
- Smooth content transitions using CollapsibleContent
- CSS transitions for visual feedback

### 4. Active State Management

- Automatically highlights the current page based on the URL
- Active state detection for both parent groups and child items
- Visual indicators for active states

### 5. Responsive Design

- Collapses to icon-only mode on smaller screens
- Groups hide their content when sidebar is collapsed
- Touch-friendly on mobile devices

### 6. Simple Implementation

- Uses ShadCN's built-in Collapsible component
- No complex state management or watch functions
- Easy to understand and maintain

## Customization

### Styling

The component uses Tailwind CSS classes and CSS custom properties for theming. You can customize:

- Colors via CSS custom properties
- Spacing and sizing via Tailwind classes
- Icons by replacing Lucide icons
- Animation duration and easing

### Icons

Uses Lucide Vue icons. You can replace with any icon library:

```vue
<component :is="item.icon" />
```

### Permissions

Optional permission-based menu filtering:

```typescript
{
    title: 'Admin Panel',
    href: '/admin',
    requiredPermissions: ['admin_access'],
}
```

### Group Organization

Easily reorganize menu items into different groups by modifying the menu structure:

```typescript
{
    title: 'Custom Group',
    href: '#',
    icon: CustomIcon,
    children: [
        // ... child items
    ],
}
```

## Browser Support

- Modern browsers with CSS Grid and Flexbox support
- Mobile browsers with touch support
- Keyboard navigation for accessibility

## Dependencies

- Vue 3
- Inertia.js (for navigation)
- shadcn-vue components (sidebar, collapsible)
- Tailwind CSS
- Lucide Vue (for icons)

## License

This component follows the same license as your Laravel application. 
