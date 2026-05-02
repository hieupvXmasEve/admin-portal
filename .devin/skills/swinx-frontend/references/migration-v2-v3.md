# v2 → v3 Migration Reference — Inertia.js

> Read this file when migrating existing v2 code or encountering v2 patterns that need updating.

---

## Breaking Changes

### App bootstrap

| Area | v2 | v3 |
|------|----|----|
| Vite plugin | `laravel-vite-plugin` only | Add `@inertiajs/vite` (replaces page resolution) |
| Page resolution | `resolvePageComponent()` in `resolve` callback | Plugin handles automatically |
| createInertiaApp | `resolve` + `setup` callbacks required | Neither required |

**v2:**
```typescript
createInertiaApp({
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
});
```

**v3:**
```typescript
createInertiaApp({
    title: (title) => `${title} - App Name`,
    // That's it. No resolve, no setup.
});
```

### Flash data

| Area | v2 | v3 |
|------|----|----|
| Server flash | `session()->flash()` + shared via middleware | `Inertia::flash('key', 'value')` |
| Access | `usePage().props.flash` | `usePage().flash` |
| Client flash | Not available | `router.flash({ key: value })` |
| Flash event | Not available | `router.on('flash', callback)` |

**v2 (still works in Swinx for legacy code):**
```php
return back()->with('success', 'Saved!');
// Frontend: usePage().props.flash.success (via HandleInertiaRequests share)
```

**v3 (use for new code):**
```php
return Inertia::flash('message', 'Saved!')->back();
// Frontend: usePage().flash.message
```

### Lazy/deferred props

| v2 | v3 |
|----|-----|
| `Inertia::lazy(fn)` | **Removed** |
| — | `Inertia::defer(fn)` — loads after initial render |
| — | `Inertia::optional(fn)` — loads on demand |

### Remember state

| v2 | v3 |
|----|-----|
| `useRemember(data, 'key')` | **Deprecated** |
| — | `useForm('key', data)` with form key |

### preserveScroll

| v2 | v3 |
|----|-----|
| `preserveScroll: true \| false` | `preserveScroll: true \| false \| 'errors'` |
| Manual per-visit | Set globally via `defaults.visitOptions` |

---

## Removed/Deprecated APIs

| API | Status | Replacement |
|-----|--------|-------------|
| `resolvePageComponent()` | Removed | `@inertiajs/vite` plugin |
| `Inertia::lazy()` | Removed | `Inertia::defer()` or `Inertia::optional()` |
| `useRemember()` | Deprecated | `useForm('key', data)` |
| Manual `setup()` in createInertiaApp | Removed | Plugin handles mounting |

---

## New v3 Features (no v2 equivalent)

### Forms & HTTP

| Feature | API |
|---------|-----|
| `<Form>` component | Template forms with slot props and native `name` attrs |
| `useHttp()` | Non-Inertia JSON requests with same reactive state |
| `useFormContext()` | Access parent `<Form>` from child components |
| Precognition | Real-time server validation via `.validate()` |
| Optimistic updates | `router.optimistic()`, `form.optimistic()`, `<Form :optimistic>` |

### Data Loading

| Feature | API |
|---------|-----|
| `Inertia::defer(fn)` | Lazy props loaded after render |
| `Inertia::optional(fn)` | Props loaded on demand |
| `Inertia::scroll()` | Cursor-based infinite scroll |
| `Inertia::merge()` | Prop accumulation (`.prepend()`, `.matchOn()`) |
| `Inertia::once()` | One-time props (`.fresh()`, `.until()`, `.as()`) |
| `usePoll()` | Polling with background throttling |
| `<Deferred>` component | Fallback/default slots for deferred props |
| `<InfiniteScroll>` component | Auto/manual/hybrid infinite scroll |
| `<WhenVisible>` component | Load-on-visibility |

### Navigation & State

| Feature | API |
|---------|-----|
| `Inertia::flash()` | Server-side flash (chained on response) |
| `router.flash()` | Client-side flash (no server request) |
| `router.on('flash')` | Flash event listener |
| `defaults.visitOptions` | Global visit defaults |
| `<Link prefetch>` | Prefetch on hover/click/mount |
| View Transitions | `viewTransition: true` option |
| Async visits | `async: true` for non-blocking |
| History encryption | `config/inertia.php` → `history.encrypt` |

### Layouts

| Feature | API |
|---------|-----|
| `setLayoutProps()` | Dynamic layout props from page |
| `resetLayoutProps()` | Reset to layout defaults |

### Tooling

| Feature | API |
|---------|-----|
| Wayfinder | Type-safe route generation from controllers |
| `@inertiajs/vite` | Auto page discovery + type generation |
| `expose_shared_prop_keys` | Instant visit optimization |

---

## Coexistence Strategy (Swinx-specific)

Swinx has ~239 legacy controllers using `back()->with('success', '...')`. Both flash systems coexist:

| System | Flow | Access |
|--------|------|--------|
| Legacy session flash | `back()->with('success', '...')` → `HandleInertiaRequests::share()` | `usePage().props.flash.success` |
| v3 native flash | `Inertia::flash('message', '...')` | `usePage().flash.message` |

**Rule:** Use `Inertia::flash()` for all new code. Legacy flash still works and doesn't need migration.
