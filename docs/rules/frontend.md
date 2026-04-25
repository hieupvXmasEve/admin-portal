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
- **Composables**: Use `use` prefix (e.g., `useInertiaFilters.ts`). Do NOT put complex business logic here.

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

## 3. Toast Notifications (vue-sonner)

- **Standard**: `toast.success()` for success, `toast.error()` for failure.
- **With useForm**: Trigger in `onSuccess` / `onError` callbacks.
- **Message**: Short, actionable messages.

## 4. Filters & Pagination

Use `useDataTable` for all list/index pages. It replaces both `useServerTableQuery` and `useInertiaFilters`.

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
    currentPage, totalPages, isFirstPage, isLastPage,
} = useDataTable<Filters>({
    baseUrl: route('admin.entities.index'),
    initialFilters: { search: props.filters?.search ?? '', status: props.filters?.status ?? '' },
    defaultValues: { status: '', per_page: 15 },
    only: ['items', 'filters'],
    debounce: 300,
});
```

### Legacy composables — DO NOT USE on new pages

| Composable | Status | Action |
|---|---|---|
| `useInertiaFilters` | LEGACY — 26 pages | Bug fix in-place only. Migrate on refactor. |
| `useServerTableQuery` | LEGACY — 9 pages | Bug fix in-place only. Migrate on refactor. |

See `docs/useDataTable-examples.md` for full usage patterns including validation, dependent filters, and migration guide.

## 5. Form Patterns

Two patterns exist. The choice depends on **whether the form navigates**:

| Form type | Composable | When |
|---|---|---|
| Inertia page form (navigates) | `useForm` from `@inertiajs/vue3` | Full-page create/edit/login forms |
| Modal/drawer form (no navigation) | `vee-validate` + `zod` + `useApi` | Inline create/edit dialogs, drawers |

**Never mix these.** Do not use `vee-validate` on Inertia page forms. Do not use Inertia `useForm` in modals.

## 6. Page Folder Naming

- **New page directories:** Must be `PascalCase` — `resources/js/pages/Finance/`, `resources/js/pages/Rooms/`
- **Existing legacy dirs:** `kebab-case` (`rooms/`, `curriculum-versions/`) and `lowercase` (`auth/`) are allowed to remain — rename during refactor only

## 7. UI Logic

- **No Business Logic**: Frontend only displays state provided by Backend.
- **Inertia**: Do not call APIs directly from Pages; receive data via Props.
- **API calls**: Use `useApi` or `useApiRequest` composables — never raw `axios`.
- **Routes**: Always use `route('name')` or `systemRoutes.x.y()` — never literal URL strings.
