# Swinx Patterns — useDataTable, Modal Forms, Routes, Flash

> Swinx-specific patterns that extend or customize standard Inertia v3 APIs.
> For base Inertia API, see the other reference files.

---

## useDataTable — Filter/Pagination Composable

### Import and setup

```typescript
import { useDataTable } from '@/composables/useDataTable';

interface Filters {
    search: string;
    status: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const {
    // State — writable reactive, bind directly in templates
    filters,
    // Core methods
    setFilter, clearFilter, clearAllFilters,
    setSort, clearSort, setPage, setPerPage,
    apply, refresh,
    // Convenience handlers for DataTable/DataPagination/DebouncedInput
    handleSearch, handleSortChange,
    handlePaginationNavigate, handlePageSizeChange,
    // Computed
    hasActiveFilters, isLoading, currentSort, currentDirection,
} = useDataTable<Filters>({
    baseUrl: route('module.entity.index'),
    initialFilters: {
        search:    props.filters?.search    ?? '',
        status:    props.filters?.status    ?? '',
        sort:      typeof props.filters?.sort === 'string' ? props.filters.sort : null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page:  props.filters?.per_page  ?? 15,
    },
    defaultValues: { status: '', per_page: 15, search: '', sort: null, direction: null },
    only: ['items', 'filters'],
    debounce: 300,
    fieldDebounce: { search: 400 },
    immediateFields: ['status'],
});
```

### Template binding

```vue
<!-- Search — reads from reactive filters, writes via handler -->
<DebouncedInput :model-value="filters.search" @update:model-value="handleSearch" />

<!-- Select filter — immediate navigation -->
<Select :model-value="filters.status" @update:model-value="(v) => setFilter('status', v)">

<!-- DataTable — sort via convenience handler -->
<DataTable :data="data" :columns="columns"
    :initial-sort="currentSort ?? undefined"
    :initial-direction="currentDirection ?? undefined"
    @sort-change="handleSortChange" />

<!-- DataPagination — page navigation via convenience handler -->
<DataPagination :pagination-data="items"
    @navigate="handlePaginationNavigate"
    @page-size-change="handlePageSizeChange" />

<!-- Clear button -->
<Button v-if="hasActiveFilters" @click="clearAllFilters">Clear</Button>
```

### Key behaviors

- `filters` is writable reactive — same ergonomics as v-model
- `setFilter(key, value)` — sets filter + resets page + triggers debounced navigation
- `setSort` / `setPage` / `setPerPage` — navigate immediately (no debounce)
- Sort and direction → **separate query params** (not `field:direction`)
- Pagination data comes from **server props** (`props.items`) — composable does not track internal pagination state
- Uses Inertia v3 `router.get(url, data, options)` under the hood

### PHP `[]` → JS Array trap

PHP `filters?.sort` where PHP sent `[]` → JS sees `Array.prototype.sort` (a function, not a string). Always use `typeof` guard:

```typescript
sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null,
```

Full docs: `docs/rules/frontend-gotchas.md` #1.

### Full reference

- `docs/useDataTable-examples.md`
- `docs/rules/filtering.md`

---

## Modal Forms — useForm + @inertiaui/modal-vue

> Full InertiaUI Modal API (all props, events, config, local/nested/slideover): `references/modal-inertiaui.md`

Swinx uses **route-based modals** — each modal is a separate Vue page wrapping content in `<Modal>`.

### Create form (route-based modal)

```typescript
// pages/Entity/Create.vue
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';

const modalRef = ref<InstanceType<typeof Modal> | null>(null);
const form = useForm({ name: '', code: '' });

const submit = () => {
    form.post(route('module.entity.store'), {
        preserveScroll: true,
        onSuccess: () => {
            modalRef.value?.close();
            form.reset();
        },
    });
};
```

```vue
<template>
    <Modal ref="modalRef">
        <div class="p-6">
            <h2 class="mb-1 text-lg font-semibold">Create Entity</h2>
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="name">Name <span class="text-destructive">*</span></Label>
                    <Input id="name" v-model="form.name" :class="{ 'border-destructive': form.errors.name }" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create' }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
```

### Edit form (route-based modal)

Same pattern, but:
- Initialize form from props: `useForm({ name: props.entity.name })`
- No `form.reset()` in onSuccess — optionally call `form.defaults()` instead
- Use `form.put()` instead of `form.post()`

### Open modal from parent page

```vue
<!-- Using ModalLink (preferred) -->
<ModalLink :href="route('entity.create')" as="button" class="inline-flex items-center gap-2 ...">
    <Plus class="h-4 w-4" /> Add Entity
</ModalLink>

<!-- Using ModalLink with loading state -->
<ModalLink :href="route('entity.edit', id)" #default="{ loading }">
    {{ loading ? 'Loading...' : 'Edit' }}
</ModalLink>

<!-- Using prefetch for faster perceived performance -->
<ModalLink :href="route('entity.create')" prefetch="hover" as="button">
    Add Entity
</ModalLink>
```

### Open modal programmatically (no ModalLink)

```typescript
import { visitModal } from '@inertiaui/modal-vue';
visitModal(route('entity.create'));
// With options:
visitModal(route('entity.create'), {
    config: { maxWidth: 'lg' },
    onClose: () => console.log('closed'),
});
```

### Rules

- Modal forms always use `useForm` (not vee-validate)
- Close modal via `modalRef.value?.close()` in `onSuccess`
- `form.reset()` only for Create — Edit forms use `form.defaults()` or nothing
- Use `form.processing` to disable submit button
- Use `form.errors.*` for inline validation
- Wrap modal content in `<div class="p-6">` (Swinx padding convention)
- Use `as="button"` on `<ModalLink>` for non-link triggers
- Backend uses `Inertia::render()` — NOT `Inertia::modal()` (no backend package)
- Confirm dialogs use `useConfirmDialogStore` — NOT `<Modal>`
- **Never** `router.visit()` to a modal page — renders as full page, not overlay

---

## Route References — systemRoutes + route()

### Wayfinder route() helper (preferred for new code)

```typescript
import { index, update } from '@/wayfinder/App/Http/Controllers/ContactController';
router.visit(index.url());
form.submit(update({ contact: id }));
```

### systemRoutes (legacy helper, still valid)

```typescript
import { systemRoutes } from '@/utils/routes';
router.visit(systemRoutes.buildings.index());
```

### Adding new routes to systemRoutes

If `systemRoutes` is missing an entry for a new module route, add it to `resources/js/utils/routes.ts`:

```typescript
export const systemRoutes = {
    // ... existing entries
    newEntity: {
        index: () => route('module.new-entity.index'),
        create: () => route('module.new-entity.create'),
        edit: (id: number) => route('module.new-entity.edit', { newEntity: id }),
    },
};
```

### Rules

- **Never** use literal URL strings (`'/buildings'`)
- Prefer Wayfinder imports for new code
- `systemRoutes` for legacy code or when Wayfinder entry doesn't exist
- `route()` for API endpoints

---

## Flash — Legacy + v3 Coexistence

### Two systems, both work

| System | Backend | Frontend access | Count |
|--------|---------|----------------|-------|
| **Legacy** | `return back()->with('success', '...')` | `usePage().props.flash` | 239 controllers |
| **Inertia v3** | `Inertia::flash('message', '...')` | `usePage().flash` | New code |

### New code pattern

```php
// Backend
Inertia::flash('message', 'Contact created.');
return back();
```

```typescript
// Frontend — in AppLayout.vue, called once
import { useFlashToast } from '@/composables/useFlashToast';
useFlashToast(); // Watches page.flash and triggers toasts
```

### Legacy pattern (do not break)

```php
// Backend — 239 controllers use this
return back()->with('success', 'Done.');
```

```typescript
// Frontend — handled by HandleInertiaRequests::share()
const flash = usePage().props.flash; // { success, error, warning, info }
```

### Rules

- **New code** → `Inertia::flash()` + `page.flash`
- **Existing code** → don't change unless refactoring the whole feature
- **Never** use `page.props.flash` in new code
- Both systems coexist — `useFlashToast()` in AppLayout handles both
- Full reference: `docs/rules/toast-patterns.md`
