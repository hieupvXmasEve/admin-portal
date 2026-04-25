# Composable Guidelines

> **Canonical rules:** `docs/rules/` is the source of truth for coding standards.
> This file supplements the Trellis workflow context only. When in doubt, defer to `docs/rules/`.

> How Vue composables are used in this project.

---

## Overview

- This Vue repo uses composables, not React hooks.
- Shared logic lives in `resources/js/composables/useXxx.ts`.
- Data loading is split between Inertia page props, `useInertiaFilters`/`useServerTableQuery`, and `useApi` for JSON interactions.

---

## Composable Patterns

- Compose around one responsibility: filters, API access, permissions, uploads, notifications.
- Return a small object of state + handlers.
- Prefer generic composables when the behavior is shared by many pages.
- For server-driven tables, use `useServerTableQuery` over page-local filter synchronization.

Examples:

- Canonical server-table composable: `resources/js/composables/useServerTableQuery.ts`
- Lower-level URL sync composable: `resources/js/composables/useInertiaFilters.ts`
- JSON API wrapper: `resources/js/composables/useApiRequest.ts`

---

## Data Fetching

- Default for Inertia pages: fetch on server and receive via props.
- For server-side filtering/sorting/pagination, use Inertia navigation instead of client cache libraries.
- For modal/drawer/inline JSON operations, use `useApi()` / `useApiRequest()`.
- There is a migration path from older `useFilters` to `useInertiaFilters` and `useServerTableQuery`.

---

## Naming Conventions

- Composables use `useXxx.ts`.
- Helper interfaces/types are exported beside the composable when reused.
- Function names should describe the workflow (`applySearch`, `handlePageChange`, `clearFilters`).

---

## Common Mistakes

- Do not introduce page-local filter glue before checking `useServerTableQuery` and `useInertiaFilters`.
- Do not fetch JSON directly with bare `fetch` when `useApiRequest.ts` already handles headers and CSRF.
- Do not assume one filter composable is canonical everywhere; legacy `useFilters.ts` still exists in older pages.
