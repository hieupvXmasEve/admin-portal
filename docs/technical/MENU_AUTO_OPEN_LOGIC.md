# Menu Auto-Open Logic - Inertia.js Standard Approach

## Overview

The Swinburne Education Management System implements menu auto-open functionality using Inertia.js's official approach for active link detection. This ensures that parent menus automatically open when navigating to child routes.

## Inertia.js Active Link Detection

### Official Approach

According to Inertia.js documentation and community best practices, active link detection should use the `$page.url` property provided by the `usePage()` composable:

```typescript
import { usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();

// Check if link is active using $page.url
function isActive(item: NavItem): boolean {
    if (!item.href || item.href === '#') return false;

    const currentUrl = getBaseUrl(page.url);
    const itemUrl = getBaseUrl(item.href);

    // Exact URL match
    if (currentUrl === itemUrl) return true;

    // URL starts with pattern for parent/child routes
    if (currentUrl.startsWith(itemUrl) && itemUrl !== '/') {
        const nextChar = currentUrl[itemUrl.length];
        return nextChar === '/' || nextChar === '?' || nextChar === undefined;
    }

    return false;
}
```

### Key Benefits

1. **Reactive**: `$page.url` is automatically reactive in Vue components
2. **Official**: Uses Inertia.js's recommended approach
3. **Reliable**: Updates automatically when routes change
4. **Performance**: No manual polling or event listeners needed

## Implementation Details

### 1. Active Link Detection

```typescript
// Helper function to remove query parameters
function getBaseUrl(url: string): string {
    return url.split('?')[0];
}

// Inertia.js standard active link detection
function isActive(item: NavItem): boolean {
    if (!item.href || item.href === '#') return false;

    const currentUrl = getBaseUrl(page.url);
    const itemUrl = getBaseUrl(item.href);

    // Exact match: /users === /users
    if (currentUrl === itemUrl) return true;

    // Parent-child match: /users matches /users/create, /users/edit/51
    if (currentUrl.startsWith(itemUrl) && itemUrl !== '/') {
        const nextChar = currentUrl[itemUrl.length];
        return nextChar === '/' || nextChar === '?' || nextChar === undefined;
    }

    return false;
}
```

### 2. Auto-Open Parent Menus

```typescript
// Watch for route changes and auto-open parent menus
watch(
    () => page.url,
    (newUrl) => {
        console.log('Route changed to:', newUrl);

        // Recursively check and open parent menus that have active children
        function autoOpenParents(items: NavItem[]) {
            items.forEach((item) => {
                if (item.children) {
                    const shouldOpen = hasActiveChild(item);

                    if (shouldOpen) {
                        console.log(`Auto-opening menu: ${item.title}`);
                        openItems.value[item.title] = true;
                        autoOpenParents(item.children);
                    }
                }
            });
        }

        autoOpenParents(props.items);
    },
    { immediate: true },
);
```

### 3. Recursive Child Checking

```typescript
// Check if any child or nested child is active
function hasActiveChild(item: NavItem): boolean {
    if (!item.children) return false;

    return item.children.some((child) => {
        // Direct child is active
        if (isActive(child)) return true;

        // Nested children are active
        if (child.children && hasActiveChild(child)) return true;

        return false;
    });
}
```

## Usage Examples

### Template Implementation

```vue
<template>
    <!-- Single menu item -->
    <SidebarMenuButton
        v-if="!item.children"
        as-child
        :is-active="isActive(item)"
        :class="{
            'bg-primary/10 text-primary': isActive(item),
            'text-gray-700': !isActive(item),
        }"
    >
        <Link :href="item.href">
            <component :is="item.icon" />
            <span>{{ item.title }}</span>
        </Link>
    </SidebarMenuButton>

    <!-- Parent menu with auto-open -->
    <SidebarMenuButton
        v-else
        :class="{
            'bg-gray-100': isOpen(item) || hasActiveChild(item),
            'text-gray-700': !isOpen(item) && !hasActiveChild(item),
        }"
        @click="toggle(item)"
    >
        <!-- Menu content -->
    </SidebarMenuButton>
</template>
```

## Route Examples

### Active Link Matching

| Current URL | Menu Item URL | Active? | Reason |
|-------------|---------------|---------|--------|
| `/users` | `/users` | ✅ | Exact match |
| `/users/create` | `/users` | ✅ | Parent-child match |
| `/users/edit/51` | `/users` | ✅ | Parent-child match |
| `/users/51/profile` | `/users` | ✅ | Parent-child match |
| `/dashboard` | `/users` | ❌ | No match |
| `/user-settings` | `/users` | ❌ | Partial match prevented |

### Auto-Open Behavior

1. **Navigate to `/users`**:
   - "System Management" menu opens
   - "Users" item becomes active

2. **Navigate to `/users/create`**:
   - "System Management" menu opens
   - "Users" item becomes active (parent)

3. **Navigate to `/users/edit/51`**:
   - "System Management" menu opens
   - "Users" item becomes active (parent)

## Best Practices

### 1. Use Inertia.js Standard Approach
```typescript
// ✅ Good - Use $page.url
const isActive = $page.url.startsWith('/users');

// ❌ Avoid - Manual URL checking
const isActive = window.location.pathname.startsWith('/users');
```

### 2. Handle Edge Cases
```typescript
// Prevent partial matches like /user-settings matching /users
if (currentUrl.startsWith(itemUrl) && itemUrl !== '/') {
    const nextChar = currentUrl[itemUrl.length];
    return nextChar === '/' || nextChar === '?' || nextChar === undefined;
}
```

### 3. Clean URL Comparison
```typescript
// Remove query parameters for clean comparison
function getBaseUrl(url: string): string {
    return url.split('?')[0];
}
```

## Alternative Approaches (Not Recommended)

### 1. Component-Based Matching
```typescript
// Available but less flexible
const isActive = $page.component.startsWith('Users');
```

### 2. Route Name Matching (with Ziggy)
```typescript
// Works with Laravel named routes
const isActive = route().current() === 'users.index';
```

### 3. Manual Event Listeners
```typescript
// Avoid - not reactive and performance issues
window.addEventListener('popstate', handleRouteChange);
```

## Troubleshooting

### Issue: Menu not opening automatically
**Solution**: Check if `hasActiveChild()` function correctly identifies active children

### Issue: Wrong menu items showing as active
**Solution**: Verify URL matching logic and ensure proper path segment handling

### Issue: Menu state not updating
**Solution**: Ensure using `$page.url` from `usePage()` composable, not manual URL checking

## Performance Considerations

1. **Reactive Updates**: `$page.url` is automatically reactive
2. **No Polling**: No need for `setInterval()` or manual checks
3. **Efficient Matching**: Simple string operations for URL comparison
4. **Minimal Re-renders**: Only updates when routes actually change

## Conclusion

The Inertia.js standard approach provides a clean, performant, and reliable way to handle active link detection and menu auto-opening. By using `$page.url` and reactive Vue patterns, the system automatically maintains correct menu states without manual intervention or performance overhead.

This approach aligns with Inertia.js best practices and ensures consistent behavior across the application. 
