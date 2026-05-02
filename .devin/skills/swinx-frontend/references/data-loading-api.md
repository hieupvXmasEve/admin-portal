# Data Loading API Reference — Inertia v3

> Read this file when implementing data loading: defer, scroll, optional, poll, merge, once, partial reloads.

---

## Deferred Props — `Inertia::defer()`

Lazy-load heavy data after initial page render.

### Backend

```php
return Inertia::render('Dashboard', [
    'user' => $user,                                    // Immediate
    'stats' => Inertia::defer(fn () =>                  // Lazy — after render
        DB::table('stats')->get()
    ),
    'chart' => Inertia::defer(fn () =>                  // Grouped — single request
        ChartQuery::run(), 'analytics'
    ),
    'report' => Inertia::defer(fn () =>
        ReportQuery::run(), 'analytics'                 // Same group
    ),
]);
```

### Frontend — `<Deferred>` component

```vue
<script setup>
import { Deferred } from '@inertiajs/vue3';
defineProps<{ stats: Stat[] | null }>();
</script>

<template>
    <Deferred data="stats">
        <template #fallback>
            <Skeleton />
        </template>
        <template #default="{ reloading }">
            <StatsTable :data="stats" :class="{ 'opacity-50': reloading }" />
        </template>
    </Deferred>
</template>
```

### Manual reload of deferred prop

```typescript
router.reload({ only: ['stats'] });
```

---

## Partial Reloads — `only` / `except` / `reset`

Refresh specific props without full page visit.

### Frontend

```typescript
router.reload({ only: ['timestamp', 'randomNumber'] }); // Only these
router.reload({ except: ['users'] });                    // Everything except
router.reload();                                          // Full reload
router.reload({ reset: ['contacts'] });                  // Reset merged/scroll data
```

### Backend — lazy closures

Props wrapped in closures are only evaluated when requested:

```php
return Inertia::render('Page', [
    'users' => fn () => User::paginate(15),     // Lazy — skipped if not in `only`
    'stats' => fn () => StatsQuery::run(),      // Lazy
    'timestamp' => now()->toISOString(),         // Always evaluated
]);
```

---

## Infinite Scroll — `Inertia::scroll()`

### Backend

```php
return Inertia::render('Contacts/Index', [
    'contacts' => Inertia::scroll(
        ContactResource::collection(
            Contact::query()
                ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
                ->cursorPaginate(15)  // MUST use cursorPaginate, not paginate()
        )
    ),
]);
```

### Frontend — Auto mode (default)

```vue
<InfiniteScroll data="contacts" :buffer="200" preserve-url>
    <template #default="{ items }">
        <ContactCard v-for="c in items" :key="c.id" :contact="c" />
    </template>
    <template #loading>
        <Spinner />
    </template>
</InfiniteScroll>
```

### Frontend — Manual mode (load more button)

```vue
<InfiniteScroll data="contacts" manual>
    <template #default="{ items }">
        <ContactCard v-for="c in items" :key="c.id" :contact="c" />
    </template>
    <template #next="{ loading, fetch, hasMore, manualMode }">
        <Button v-if="hasMore" :disabled="loading" @click="fetch">
            {{ loading ? 'Loading...' : 'Load More' }}
        </Button>
    </template>
</InfiniteScroll>
```

### Frontend — Hybrid mode (auto then manual)

```vue
<!-- Auto first 2 pages, then "Load More" button -->
<InfiniteScroll data="contacts" :manual-after="2">
    <!-- same slots -->
</InfiniteScroll>
```

### Reset on filter change

```typescript
// CRITICAL: reset accumulated data when filter changes
router.reload({ reset: ['contacts'] });
```

### `<InfiniteScroll>` props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `data` | `string` | — | Prop name (must match backend key) |
| `buffer` | `number` | `0` | Pixels before bottom to trigger |
| `manual` | `boolean` | `false` | Manual mode only |
| `manual-after` | `number` | — | Auto for N pages, then manual |
| `preserve-url` | `boolean` | `false` | Don't update browser URL |

### `#next` slot props

| Prop | Type | Description |
|------|------|-------------|
| `loading` | `boolean` | Fetching next page |
| `fetch` | `() => void` | Load next page |
| `hasMore` | `boolean` | More pages available |
| `manualMode` | `boolean` | Currently in manual mode |

---

## Optional Props — `Inertia::optional()` + `<WhenVisible>`

Load when scrolled into view.

### Backend

```php
return Inertia::render('Page', [
    'expensiveData' => Inertia::optional(fn () => ExpensiveQuery::run()),
]);
```

### Frontend

```vue
import { WhenVisible } from '@inertiajs/vue3';

<WhenVisible data="expensiveData" :buffer="200">
    <template #fallback>
        <Skeleton />
    </template>
    <ExpensiveComponent :data="expensiveData" />
</WhenVisible>
```

---

## Polling — `usePoll()`

Periodic data refresh with background tab throttling.

```typescript
import { usePoll } from '@inertiajs/vue3';

// Auto-start
usePoll(5000);

// With options
usePoll(5000, {
    only: ['notifications'],
    onFinish: () => count.value++,
});

// Manual control
const { start, stop } = usePoll(2000, {
    only: ['stats'],
    onFinish: () => pollCount.value++,
}, {
    autoStart: false,
    // keepAlive: true,  // Prevent background throttling
});

start();
stop();
```

**Background throttling:** Polls reduce ~90% when tab is hidden. Use `keepAlive: true` only if genuinely needed.

---

## Prop Merging — `Inertia::merge()`

Accumulate data across multiple requests.

### Backend

```php
// Append (default)
'messages' => Inertia::merge(Message::latest()->take(20)->get()),

// Prepend new items
'notifications' => Inertia::merge(Notification::latest()->take(10)->get())->prepend(),

// Merge by key (upsert — updates existing items, adds new ones)
'users' => Inertia::merge(User::all())->matchOn('id'),
```

### Frontend — Reset

```typescript
// Discard accumulated data and start fresh
router.reload({ reset: ['messages'] });
```

---

## Once Props — `Inertia::once()`

Resolved once, never re-evaluated on partial reloads.

```php
// Basic — once per visit
'categories' => Inertia::once(fn () => Category::all()),

// Fresh on each full visit (but still skipped on partial reloads)
'serverTime' => Inertia::once(fn () => now())->fresh(),

// Expiring — auto-refreshes after TTL
'token' => Inertia::once(fn () => generateToken())->until(now()->addSeconds(30)),

// Aliased — shared key across pages (same value reused)
'settings' => Inertia::once(fn () => Settings::all())->as('global-settings'),
```

### Modifiers

| Modifier | Description |
|----------|-------------|
| `.fresh()` | Fresh value on each full visit |
| `.until(Carbon)` | Auto-refresh after expiry |
| `.as('key')` | Share across pages by key |
