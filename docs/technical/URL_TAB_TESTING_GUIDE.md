# URL Parameter Tab Management - Testing Guide

This guide provides step-by-step instructions to test the URL parameter-based tab management implementation in the Campus Show page.

## Test Scenarios

### 1. Default Tab Behavior

**Test**: Load page without tab parameter

**Steps**:
1. Navigate to `/campuses/{id}` (without query parameters)
2. Observe the active tab

**Expected Result**:
- URL: `/campuses/{id}` (clean, no query parameters)
- Active Tab: "Campus Overview"
- Tab content shows campus information and statistics

### 2. Specific Tab via URL

**Test**: Load page with tab parameter

**Steps**:
1. Navigate to `/campuses/{id}?tab=buildings`
2. Observe the active tab

**Expected Result**:
- URL: `/campuses/{id}?tab=buildings`
- Active Tab: "Buildings Management"
- Tab content shows buildings table and management tools

### 3. Tab Switching Updates URL

**Test**: Click tabs and verify URL changes

**Steps**:
1. Start on `/campuses/{id}` (overview tab)
2. Click "Buildings Management" tab
3. Click "Campus Overview" tab

**Expected Results**:
- Step 2: URL changes to `/campuses/{id}?tab=buildings`
- Step 3: URL changes back to `/campuses/{id}` (parameter removed)

### 4. Browser Back/Forward Navigation

**Test**: Browser navigation maintains tab state

**Steps**:
1. Navigate to `/campuses/{id}` (overview tab)
2. Click "Buildings Management" tab
3. Click browser back button
4. Click browser forward button

**Expected Results**:
- Step 1: Overview tab active
- Step 2: Buildings tab active, URL has `?tab=buildings`
- Step 3: Overview tab active, URL clean
- Step 4: Buildings tab active, URL has `?tab=buildings`

### 5. Page Refresh Persistence

**Test**: Tab state survives page refresh

**Steps**:
1. Navigate to `/campuses/{id}?tab=buildings`
2. Refresh the page (F5 or Ctrl+R)

**Expected Result**:
- URL remains `/campuses/{id}?tab=buildings`
- "Buildings Management" tab remains active
- Buildings content is displayed

### 6. Invalid Tab Parameter Handling

**Test**: Invalid tab parameters default gracefully

**Steps**:
1. Navigate to `/campuses/{id}?tab=invalid`
2. Observe behavior

**Expected Result**:
- URL: `/campuses/{id}?tab=invalid`
- Active Tab: "Campus Overview" (fallback to default)
- Overview content is displayed

### 7. Bookmarking and Direct Links

**Test**: Tab URLs can be bookmarked and shared

**Steps**:
1. Navigate to `/campuses/{id}?tab=buildings`
2. Bookmark the page
3. Close browser/tab
4. Open bookmark

**Expected Result**:
- URL: `/campuses/{id}?tab=buildings`
- "Buildings Management" tab is active
- Buildings content is displayed

## Automated Testing

### JavaScript Console Tests

Open browser developer tools and run these commands in the console:

```javascript
// Test URL parameter reading
const urlParams = new URLSearchParams(window.location.search);
console.log('Current tab parameter:', urlParams.get('tab'));

// Test tab validation
const validTabs = ['overview', 'buildings'];
const currentTab = urlParams.get('tab');
console.log('Is valid tab:', validTabs.includes(currentTab));

// Test default fallback
const effectiveTab = validTabs.includes(currentTab) ? currentTab : 'overview';
console.log('Effective tab:', effectiveTab);
```

### URL Manipulation Tests

```javascript
// Test URL building
const url = new URL(window.location.href);
url.searchParams.set('tab', 'buildings');
console.log('Buildings URL:', url.href);

// Test parameter removal
url.searchParams.delete('tab');
console.log('Clean URL:', url.href);
```

## Manual Testing Checklist

- [ ] Default tab loads without parameters
- [ ] Tab parameter in URL activates correct tab
- [ ] Clicking tabs updates URL appropriately
- [ ] Browser back/forward works correctly
- [ ] Page refresh maintains tab state
- [ ] Invalid parameters fallback to default
- [ ] Bookmarked tab URLs work correctly
- [ ] URL sharing maintains tab state

## Browser Compatibility

Test the following browsers:

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

## Performance Considerations

Monitor these aspects during testing:

1. **Memory Usage**: Check for memory leaks with event listeners
2. **Navigation Speed**: URL updates should be instantaneous
3. **History Size**: Verify `replace: true` prevents history bloat
4. **Scroll Position**: Ensure `preserveScroll: true` works

## Debugging Tips

### Check Current Implementation

View the component's reactive state:

```javascript
// In Vue DevTools, inspect the component and check:
// - currentTab.value
// - validTabs array
// - Event listeners on window
```

### URL State Debugging

```javascript
// Check current URL state
console.log('Location:', window.location.href);
console.log('Search params:', new URLSearchParams(window.location.search).toString());
console.log('Tab parameter:', new URLSearchParams(window.location.search).get('tab'));
```

### Event Listener Verification

```javascript
// Check if popstate listener is attached
console.log('Event listeners:', getEventListeners(window));
```

## Common Issues and Solutions

### Issue: Tab doesn't update on browser navigation
**Solution**: Verify `popstate` event listener is properly attached in `onMounted()`

### Issue: URL parameter not updating
**Solution**: Check that `updateTabInURL()` is called from `handleTabChange()`

### Issue: Memory leaks
**Solution**: Ensure `onUnmounted()` removes event listeners

### Issue: Multiple history entries
**Solution**: Verify `replace: true` is used in `router.visit()`

### Issue: Invalid tab state
**Solution**: Check tab validation in `getCurrentTabFromURL()`

## Success Criteria

The implementation is successful if:

1. ✅ All test scenarios pass
2. ✅ No console errors occur
3. ✅ Browser navigation works smoothly  
4. ✅ URLs are clean and readable
5. ✅ Tab state persists across sessions
6. ✅ No memory leaks detected
7. ✅ Performance remains optimal 
