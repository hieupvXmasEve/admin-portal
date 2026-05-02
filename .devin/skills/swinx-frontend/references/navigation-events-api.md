# Navigation, Events & useHttp API Reference — Inertia v3

> Read this file when implementing navigation, links, router visits, events, or useHttp.

---

## `<Link>` Component

```vue
import { Link } from '@inertiajs/vue3';

<!-- Basic -->
<Link href="/contacts">Contacts</Link>

<!-- Prefetch on hover (default, 75ms delay) -->
<Link href="/contacts" prefetch>Contacts</Link>

<!-- Prefetch on mousedown -->
<Link href="/contacts" prefetch="click">Contacts</Link>

<!-- Prefetch on mount -->
<Link href="/contacts" prefetch="mount">Contacts</Link>

<!-- POST as button -->
<Link href="/logout" method="post" as="button">Logout</Link>

<!-- With data -->
<Link href="/search" :data="{ q: 'term' }">Search</Link>

<!-- Preserve state and scroll -->
<Link href="/contacts" preserve-state preserve-scroll>Contacts</Link>

<!-- With Wayfinder -->
<Link :href="contactIndex.url()" prefetch>Contacts</Link>
```

### `<Link>` props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `href` | `string` | — | URL |
| `method` | `string` | `'get'` | HTTP method |
| `as` | `string` | `'a'` | Rendered element (e.g., `'button'`) |
| `data` | `object` | — | Data to send |
| `prefetch` | `boolean \| string` | — | `true`/`'hover'`/`'click'`/`'mount'` |
| `preserve-state` | `boolean` | `false` | Keep component state |
| `preserve-scroll` | `boolean` | `false` | Keep scroll position |
| `replace` | `boolean` | `false` | Replace history entry |

---

## Router — Manual Visits

```typescript
import { router } from '@inertiajs/vue3';

// Full options
router.visit(url, {
    method: 'post',
    data: { name: 'John' },
    preserveScroll: true,
    preserveState: true,
    only: ['contacts'],
    except: ['stats'],
    reset: ['contacts'],
    viewTransition: true,
    async: true,
    onBefore: (visit) => confirm('Continue?'),
    onStart: (visit) => { /* ... */ },
    onProgress: (progress) => { /* ... */ },
    onSuccess: (page) => { /* ... */ },
    onError: (errors) => { /* ... */ },
    onFinish: () => { /* ... */ },
    onCancel: () => { /* ... */ },
});

// Convenience methods
router.get(url, data, options);
router.post(url, data, options);
router.put(url, data, options);
router.patch(url, data, options);
router.delete(url, options);  // No data arg

// Reload current page
router.reload();
router.reload({ only: ['stats'] });
router.reload({ except: ['users'] });
router.reload({ reset: ['contacts'] });
```

### Visit options reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `method` | `string` | `'get'` | HTTP method |
| `data` | `object` | `{}` | Request data |
| `preserveScroll` | `boolean \| 'errors'` | `false` | Keep scroll position |
| `preserveState` | `boolean` | `false` | Keep component state |
| `only` | `string[]` | — | Partial reload: only these props |
| `except` | `string[]` | — | Partial reload: exclude these props |
| `reset` | `string[]` | — | Reset merged/scroll props |
| `viewTransition` | `boolean` | `false` | View Transitions API |
| `async` | `boolean` | `false` | Non-blocking visit |
| `errorBag` | `string` | — | Scope errors to a bag |

---

## Redirects (Backend)

```php
// Standard
return to_route('contacts.index');

// Flash + redirect back
return Inertia::flash('message', 'Saved!')->back();

// External redirect (full page load)
return Inertia::location('https://external.com');

// Preserve URL fragment
return redirect()->route('page')->preserveFragment();
```

---

## Scroll Management

```typescript
// Global default (app.ts)
defaults.visitOptions = { preserveScroll: 'errors' };

// Per-visit override
router.visit(url, { preserveScroll: true });
form.post(url, { preserveScroll: true });
```

---

## Global Router Events

Register via `router.on(name, callback)` — returns cleanup function.

```typescript
const removers: (() => void)[] = [];

removers.push(
    router.on('before', (event) => {
        // event.detail.visit.url — return false to cancel
    }),
    router.on('start', (event) => {
        // event.detail.visit.url
    }),
    router.on('progress', (event) => {
        // event.detail.progress?.percentage — file uploads only
    }),
    router.on('success', (event) => {
        // event.detail.page.url
    }),
    router.on('error', (event) => {
        // event.detail.errors — validation errors
    }),
    router.on('finish', () => {
        // Visit completed (success or error)
    }),
    router.on('navigate', (event) => {
        // event.detail.page.url — page component swapped
    }),
    router.on('flash', (event) => {
        // event.detail.flash — NEW in v3
    }),
    router.on('httpException', (event) => {
        // HTTP errors: 403, 404, 500, etc.
    }),
    router.on('networkError', (event) => {
        // Network failure
    }),
    router.on('prefetching', (event) => {
        // Prefetch started
    }),
    router.on('prefetched', (event) => {
        // Prefetch completed
    }),
);

// Cleanup
onUnmounted(() => removers.forEach((r) => r()));
```

### All events (12 total)

| Event | When | Detail |
|-------|------|--------|
| `before` | Before any visit | `visit` (return `false` to cancel) |
| `start` | Visit started | `visit` |
| `progress` | Upload/download progress | `progress.percentage` |
| `success` | Visit succeeded | `page` |
| `error` | Validation errors | `errors` |
| `finish` | Visit completed | — |
| `navigate` | Page component swapped | `page` |
| `flash` | Flash data received | `flash` |
| `httpException` | HTTP error (4xx/5xx) | status, body |
| `networkError` | Network failure | — |
| `prefetching` | Prefetch started | — |
| `prefetched` | Prefetch completed | — |

### Per-visit callbacks

```typescript
router.visit(url, {
    onBefore: (visit) => confirm('Sure?'),
    onStart: (visit) => { /* ... */ },
    onProgress: (progress) => { /* ... */ },
    onSuccess: (page) => { /* ... */ },
    onError: (errors) => { /* ... */ },
    onFinish: () => { /* ... */ },
    onCancel: () => { /* ... */ },
});
```

---

## useHttp — Non-Inertia JSON Requests

For API calls that should NOT trigger page visits.

```typescript
import { useHttp } from '@inertiajs/vue3';

interface ApiResponse {
    message: string;
    timestamp: string;
}

const http = useHttp<{ name: string }, ApiResponse>({ name: '' });

function submit() {
    http.post('/api/greet', {
        onSuccess: (response) => {
            // response typed as ApiResponse
            console.log(response.message);
        },
    });
}

// Cancel in-flight request
http.cancel();
```

### Reactive state (same as useForm)

| Property | Type | Description |
|----------|------|-------------|
| `http.processing` | `boolean` | Request in flight |
| `http.isDirty` | `boolean` | Data changed |
| `http.hasErrors` | `boolean` | Errors present |
| `http.wasSuccessful` | `boolean` | Last request succeeded |
| `http.recentlySuccessful` | `boolean` | Succeeded within 2s |

### Template

```vue
<Input v-model="http.name" />
<Button :disabled="http.processing" @click="submit">
    {{ http.processing ? 'Sending...' : 'Send' }}
</Button>
<Button v-if="http.processing" variant="destructive" @click="http.cancel()">
    Cancel
</Button>
```

### useHttp vs useForm

| | `useForm` | `useHttp` |
|--|-----------|-----------|
| Server response | Inertia page visit | JSON |
| Page navigation | Yes | No |
| Use case | CRUD forms | API calls, toggles, status checks |
| Reactive state | Same | Same |
