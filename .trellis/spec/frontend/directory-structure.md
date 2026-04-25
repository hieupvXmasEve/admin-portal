# Directory Structure

> **Canonical rules:** `docs/rules/` is the source of truth for coding standards.
> This file supplements the Trellis workflow context only. When in doubt, defer to `docs/rules/`.

> How frontend code is organized in this project.

---

## Overview

Frontend code lives under `resources/js` and follows a Vue 3 + Inertia structure.

- Route-backed pages live in `resources/js/pages`.
- Reusable UI lives in `resources/js/components`.
- Shared stateful logic lives in `resources/js/composables`.
- Shared TS contracts live in `resources/js/types`.
- Route helpers/constants live in `resources/js/utils` and `resources/js/constants`.

---

## Directory Layout

```text
resources/js/
├── app.ts
├── components/
├── composables/
├── constants/
├── directives/
├── layouts/
├── lib/
├── pages/
├── schemas/
├── stores/
├── types/
└── utils/
```

---

## Module Organization

- Inertia page names mirror server render paths under `pages/`.
- Shared filter/table primitives are extracted into `components/filters` and `components/tables`.
- Reusable data/query behavior is moving toward shared composables such as `useServerTableQuery`.
- Global bootstrapping and plugin wiring stay in `resources/js/app.ts`.
- Pinia usage exists but is light; most state is page-local, URL-driven, or server-provided.

---

## Naming Conventions

- Vue component files: PascalCase.
- Composables: `useXxx.ts`.
- Type files are mixed: most are kebab-case, but older PascalCase names also exist.
- Page folders are mixed by age of code (`Admin/...`, `Finance/...`, `students/...`, `rooms/...`), so follow the local feature area instead of forcing a global rename.

---

## Examples

- App bootstrapping + layout/plugin wiring: `resources/js/app.ts`
- Shared route helper map: `resources/js/utils/routes.ts`
- Newer server-table page structure: `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue`

## Anti-Patterns

- Do not create a new frontend top-level folder without approval.
- Do not assume all pages use the same naming style; keep consistency within the touched feature.
- Do not duplicate filter/table glue when an existing shared composable/component already fits.
