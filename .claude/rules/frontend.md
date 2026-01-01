---
paths: "**/*.{vue,js,ts}"
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

## 4. Filters & Pagination (useInertiaFilters)
- **Composable**: Use `useInertiaFilters` for standardized filtering/pagination.
- **Features**: Syncs with URL, preserves state on pagination, supports debouncing.
- **Type Safety**: Always define an interface for your filters.
- **Reference**: See `.claude/rules/filtering.md` for detailed implementation patterns.
- **Pattern**:
  ```typescript
  const { filters, ... } = useInertiaFilters<EntityFilters>({
      baseUrl: route('admin.entities.index'),
      initialFilters: { ... },
      defaultValues: { ... },
      only: ['items', 'filters'],
  });
  ```

## 5. UI Logic
- **No Business Logic**: Frontend only displays state provided by Backend.
- **Inertia**: Do not call APIs directly from Pages; receive data via Props.
