---
paths: '**/*.{vue,js,ts}'
---

# Frontend Rules (Vue 3 + Inertia + Shadcn)

## 1. Directory Structure

```text
resources/js/
 ├─ Pages/                   # Organized by /{Module}/{Entity}/
 │   ├─ Academic/
 │   │   └─ CourseOffering/
 │   │       ├─ Index.vue    # List page
 │   │       └─ Show.vue     # Detail page
 ├─ Components/              # Reusable UI (Shadcn/Custom)
 ├─ Layouts/                 # Main wrappers
 ├─ Composables/             # Shared logic (hooks)
 └─ types/                   # TypeScript definitions
```

## 2. Structure & Naming

- **Pages**: 1-1 mapping with Routes (e.g., `Academic/Records/Index.vue`).
- **Components**: PascalCase (e.g., `RecordTable.vue`). Avoid generic names like `Table.vue`.
- **Composables**: Use `use` prefix (e.g., `useDataTable.ts`). Do NOT put complex business logic here.

## 3. Shadcn-Vue Conventions

### Select Component

- **Value Requirement**: `value` prop MUST be a **non-empty string**.
    - Use `"all"`, `"default"` for reset/global states.
    - NEVER use `value=""`.
    - Cast numeric IDs: `:value="String(item.id)"`.
    - Filter empty values before `v-for`.

### Form & Input

- **State**: Use `useForm` (Inertia) for submission, `ref` for simple filters.
- **Validation**: Use `vee-validate` or Inertia's errors (`form.errors.field`).

### General

- **Hydration**: Provide default values for props to avoid hydration mismatch (e.g., `ref(props.initialValue || 'all')`).
- **Types**: Define strict interfaces for data items.

## 3. Toast Notifications (vue-sonner + Inertia v3 flash)

- **Backend**: Use `Inertia::flash('message', '...')` — toast shows automatically via `useFlashToast()`.
- **No manual `toast.success()`** needed in form `onSuccess` callbacks when using `Inertia::flash()`.
- **Detailed patterns**: See `docs/rules/toast-patterns.md`.
- **Legacy**: 239 controllers still use `->with('success', ...)` → `page.props.flash`. Migrate gradually.

## 4. Filters & Pagination

Use `useDataTable` for all list/index pages. It replaces both `useServerTableQuery` and `useInertiaFilters`.

**Required reading:** `docs/rules/frontend-gotchas.md` — covers PHP-to-JS serialization traps (e.g. `filters?.sort` resolving to native function).

### `useDataTable` — STANDARD (all list pages)

- **When:** Any new list/index page, or refactoring an existing one
- **Behavior:** Explicit control — debounced navigation via `setFilter()`, immediate via `apply()`
- **Features:** Built-in validation, dependent filters, per-field debounce, performance metrics
- **Reference:** `docs/useDataTable-examples.md`

```typescript
import { useDataTable } from '@/composables/useDataTable';

const {
    state, setFilter, apply, setPage, setPerPage, setSort,
    clearAllFilters, hasActiveFilters, isLoading,
    currentSort, currentDirection,
} = useDataTable<Filters>({
    baseUrl: route('admin.entities.index'),
    initialFilters: {
        search: props.filters?.search ?? '',
        status: props.filters?.status ?? '',
        sort: typeof props.filters?.sort === 'string' ? props.filters.sort : null,  // typeof guard — see docs/rules/frontend-gotchas.md
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
    },
    defaultValues: { status: '', per_page: 15, sort: null, direction: null },
    only: ['items', 'filters'],
    debounce: 300,
});
```

### Legacy composables — DO NOT USE (new code) · MIGRATE (when editing)

> 🔴 **MANDATORY for humans and AI agents:** `useDataTable` is the ONLY approved
> filter/sort/pagination composable. ALWAYS use it when creating new code, and
> migrate to it when editing a page that still uses a legacy composable. Never
> introduce or extend `useInertiaFilters` / `useServerTableQuery`.

| Composable | Status | Action |
|---|---|---|
| `useInertiaFilters` | LEGACY — ≈26 pages | New code: forbidden. Editing: migrate to `useDataTable`. Only a strictly isolated one-line bug fix may stay in place. |
| `useServerTableQuery` | LEGACY — ≈9 pages | New code: forbidden. Editing: migrate to `useDataTable`. Only a strictly isolated one-line bug fix may stay in place. |

See `docs/useDataTable-examples.md` for full usage patterns including validation, dependent filters, and migration guide. Full rules: `docs/rules/filtering.md`.

## 5. Form Patterns

Three patterns exist. The choice depends on **navigation context**:

| Form type | Composable | When |
|---|---|---|
| Inertia page form (navigates) | `useForm` from `@inertiajs/vue3` | Full-page create/edit/login forms |
| Modal/drawer form (Inertia) | `useForm` from `@inertiajs/vue3` + modal ref | Modal create/edit with backend redirect |
| Modal/drawer form (JSON API) | `vee-validate` + `zod` + `useApi` | Pure API calls, no navigation |

**Never mix patterns.** Use the right composable for the right context.

### Modal forms with useForm + toast

For Inertia modal forms (Create/Edit), use this pattern:

```typescript
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';

const modalRef = ref<InstanceType<typeof Modal> | null>(null);

const form = useForm({
    name: '',
    code: '',
});

const submit = () => {
    form.post(route('module.entity.store'), {
        onSuccess: () => {
            modalRef.value?.close(); // Close modal
            form.reset(); // Clear form (Create only)
        },
        onError: () => {
            // Errors via form.errors
        },
    });
};
```

```vue
<template>
    <Modal ref="modalRef">
        <!-- Form content -->
    </Modal>
</template>
```

### Toast integration

Backend uses `Inertia::flash()` (native v3) for messages — goes to `page.flash`, not `page.props`:

```php
Inertia::flash('message', 'Item created successfully.');
return back();

// Or chained:
return Inertia::flash('message', 'Item created successfully.')->back();
```

Frontend handles automatically via `useFlashToast()` in AppLayout — no manual `toast.success()` needed.

## 6. Page Folder Naming

- **New page directories:** Must be `PascalCase` — `resources/js/pages/Finance/`, `resources/js/pages/Rooms/`
- **Existing legacy dirs:** `kebab-case` (`rooms/`, `curriculum-versions/`) and `lowercase` (`auth/`) are allowed to remain — rename during refactor only

## 7. UI Logic

- **No Business Logic**: Frontend only displays state provided by Backend.
- **Inertia**: Do not call APIs directly from Pages; receive data via Props.
- **API calls**: Use `useApi` or `useApiRequest` composables — never raw `axios`.
- **Routes**: Always use `route('name')` or `systemRoutes.x.y()` — never literal URL strings.
