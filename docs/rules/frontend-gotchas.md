---
paths: '**/*.{vue,ts}'
---

# Frontend Gotchas

Known pitfalls when working with Laravel + Inertia + Vue 3. Every item here has caused a production bug at least once.

---

## 1. PHP empty array serializes to JS Array — breaks `filters.sort`

### The bug

PHP's `$request->only([...])` returns `[]` when no matching keys exist. PHP serializes `[]` to JSON `[]`, which JavaScript receives as an **Array** — not `{}`.

`Array.prototype.sort` is a built-in function, so:

```js
const filters = [];       // from PHP []
filters.sort;             // => function sort() { [native code] }  (truthy!)
filters.sort ?? null;     // => function sort() { [native code] }  (?? only guards null/undefined)
filters.sort || null;     // => function sort() { [native code] }  (functions are truthy)
```

### Consequences

1. **Vue warning:** `Invalid prop: type check failed for prop "initialSort". Expected String, got Function`
2. **Filtering breaks:** every `router.get()` sends `sort=function+sort()+%7B+[native+code]+%7D` in URL params — backend validation rejects with 422, data never updates

### The fix

Always use `typeof` guard when reading `sort` from server props:

```typescript
// WRONG — all of these pass through the native function
sort: props.filters?.sort ?? null,
sort: props.filters?.sort || null,

// CORRECT — typeof guard ensures only real strings pass
sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null,
```

For template bindings, use `currentSort`/`currentDirection` computed refs from `useDataTable` (they are properly typed as `string | null`):

```vue
<!-- WRONG — filters.sort may be native function -->
<DataTable :initial-sort="filters.sort || undefined" />

<!-- CORRECT — currentSort is typed string | null -->
<DataTable :initial-sort="currentSort ?? undefined"
           :initial-direction="currentDirection ?? undefined" />
```

### Affected props

Only `sort` triggers this bug because `Array.prototype.sort` exists. Other filter keys (`search`, `direction`, `per_page`) are safe since arrays don't have those as built-in properties.

---

## 2. macOS case-insensitive filesystem — folder rename

macOS default filesystem (`APFS case-insensitive`) treats `campuses/` and `Campuses/` as the same directory. A direct `git mv campuses Campuses` fails.

### The fix

Two-step rename:

```bash
git mv resources/js/pages/campuses resources/js/pages/_tmp_campuses
git mv resources/js/pages/_tmp_campuses resources/js/pages/Campuses
```

After rename, update all `Inertia::render()` calls to match the new PascalCase path.
