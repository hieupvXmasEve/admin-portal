# URL Parameter-Based Tab Management

This document explains the implementation of URL parameter-based state management for tabs in Vue.js components, specifically demonstrated in the Campus Show page (`resources/js/pages/campuses/Show.vue`).

## Overview

The tab state management system allows tabs to persist their state in URL query parameters, providing several benefits:

1. **Browser History Support**: Users can navigate back/forward between tabs using browser buttons
2. **Bookmarkable Tabs**: Users can bookmark specific tabs and return to them later
3. **URL Sharing**: URLs can be shared with specific tab states
4. **Page Refresh Persistence**: Tab state survives page refreshes
5. **Clean URLs**: Default tab doesn't add unnecessary parameters

## Implementation Details

### 1. Tab State Management

```typescript
// Define valid tab values
const validTabs = ['overview', 'buildings'] as const;
type ValidTab = typeof validTabs[number];

// Get current tab from URL parameters
const getCurrentTabFromURL = (): ValidTab => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') as ValidTab;
    return validTabs.includes(tabParam) ? tabParam : 'overview';
};

// Reactive tab state
const currentTab = ref<ValidTab>(getCurrentTabFromURL());
```

### 2. URL Parameter Management

```typescript
// Update URL when tab changes
const updateTabInURL = (newTab: ValidTab) => {
    const url = new URL(window.location.href);
    
    if (newTab === 'overview') {
        // Remove tab parameter for default tab to keep URL clean
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', newTab);
    }
    
    // Update URL without page reload using Inertia.js
    router.visit(url.pathname + url.search, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [] // Don't reload any props
    });
};
```

### 3. Tab Change Handling

```typescript
// Handle tab change with validation
const handleTabChange = (newTab: string | number) => {
    const tabValue = String(newTab) as ValidTab;
    // Validate the tab value before setting
    if (validTabs.includes(tabValue)) {
        currentTab.value = tabValue;
        updateTabInURL(tabValue);
    }
};
```

### 4. Browser Navigation Support

```typescript
// Initialize tab state on mount and handle browser back/forward
onMounted(() => {
    // Handle browser back/forward navigation
    const handlePopState = () => {
        const newTab = getCurrentTabFromURL();
        currentTab.value = newTab;
    };
    
    window.addEventListener('popstate', handlePopState);
    
    // Cleanup listener when component is unmounted
    onUnmounted(() => {
        window.removeEventListener('popstate', handlePopState);
    });
});
```

### 5. Template Integration

```vue
<Tabs :model-value="currentTab" @update:model-value="handleTabChange" class="w-full">
    <TabsList class="grid w-full grid-cols-2">
        <TabsTrigger value="overview">Campus Overview</TabsTrigger>
        <TabsTrigger value="buildings">Buildings Management</TabsTrigger>
    </TabsList>

    <TabsContent value="overview" class="space-y-6">
        <!-- Campus Overview Content -->
    </TabsContent>

    <TabsContent value="buildings" class="space-y-6">
        <!-- Buildings Management Content -->
    </TabsContent>
</Tabs>
```

## URL Behavior

### Default State
- URL: `/campuses/1` (no query parameter)
- Active Tab: `overview`

### Non-Default State
- URL: `/campuses/1?tab=buildings`
- Active Tab: `buildings`

### Invalid Tab Parameter
- URL: `/campuses/1?tab=invalid`
- Active Tab: `overview` (fallback to default)

## Key Features

### 1. Clean URLs
The default tab (`overview`) doesn't add a query parameter to keep URLs clean and readable.

### 2. Validation
All tab parameters are validated against the `validTabs` array to prevent invalid states.

### 3. Browser History
The implementation uses `router.visit()` with `replace: true` to update the URL without creating new history entries for each tab change within the same page visit.

### 4. Event Cleanup
Proper cleanup of `popstate` event listeners to prevent memory leaks.

### 5. Type Safety
Full TypeScript support with proper typing for tab values.

## Usage in Other Components

To implement similar functionality in other components:

1. **Define Valid Tabs**:
   ```typescript
   const validTabs = ['tab1', 'tab2', 'tab3'] as const;
   type ValidTab = typeof validTabs[number];
   ```

2. **Copy URL Management Functions**:
   - `getCurrentTabFromURL()`
   - `updateTabInURL()`
   - `handleTabChange()`

3. **Add Lifecycle Hooks**:
   - `onMounted()` for popstate listener
   - `onUnmounted()` for cleanup

4. **Update Template**:
   - Use `:model-value="currentTab"`
   - Use `@update:model-value="handleTabChange"`

## Benefits

1. **User Experience**: Seamless tab navigation with browser support
2. **SEO Friendly**: Crawlable tab content with distinct URLs
3. **Shareable**: Each tab state has a unique URL
4. **Persistent**: State survives page refreshes and navigation
5. **Accessible**: Works with browser accessibility features

## Considerations

1. **Performance**: Uses `replace: true` to avoid excessive history entries
2. **Memory**: Proper event listener cleanup prevents memory leaks
3. **Validation**: Input validation prevents invalid states
4. **Compatibility**: Works with Inertia.js navigation system
5. **Clean URLs**: Smart parameter management for better UX 
