# State Management

> How state is managed in this project.

---

## Overview

- Primary state model is server-driven Inertia props plus page-local Vue state.
- URL query state is important for lists/filters and is managed through Inertia composables.
- Pinia exists, but usage is intentionally light.

---

## State Categories

- Local UI state: `ref`, `computed`, `watch` inside page/component files.
- URL/query state: `useInertiaFilters` and `useServerTableQuery`.
- Server state for page loads: Inertia props.
- Global cross-page UI state: Pinia store when truly shared.

Examples:

- Page-local state + server props: `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue`
- URL/query state orchestration: `resources/js/composables/useServerTableQuery.ts`
- Global dialog store: `resources/js/stores/confirmDialog.ts`

---

## When to Use Global State

Use Pinia only when state must survive across unrelated components/pages or support a global UI primitive.

Current verified example:

- Global confirm dialog workflow: `resources/js/stores/confirmDialog.ts`

If state only affects one page or one page tree, keep it local.

---

## Server State

- Prefer server-provided Inertia props for initial data.
- For filtered/paginated pages, synchronize state through URL params and partial reloads.
- Use `useApi()` only for non-navigating JSON flows.
- There is no React Query/SWR-style cache layer in the verified baseline.

---

## Common Mistakes

- Do not promote page-local state into Pinia prematurely.
- Do not duplicate server state into multiple refs unless transformation is necessary.
- Do not break URL-backed filter state by mutating local refs without Inertia sync.
