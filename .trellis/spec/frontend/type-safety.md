# Type Safety

> **Canonical rules:** `docs/rules/` is the source of truth for coding standards.
> This file supplements the Trellis workflow context only. When in doubt, defer to `docs/rules/`.

> Type safety patterns in this project.

---

## Overview

- TypeScript is enabled across the Vue frontend.
- Shared app contracts live in `resources/js/types`.
- Local page/component props are often expressed as local `interface Props`.
- Runtime form validation commonly uses Zod through `@vee-validate/zod`.

---

## Type Organization

- Global/shared app types: `resources/js/types/index.d.ts`
- Feature-specific contracts: files such as `resources/js/types/student-decision.ts`
- Page-local props and filter types stay inside the page when they are not reused.

Examples:

- Shared pagination/auth types: `resources/js/types/index.d.ts`
- Feature contract: `resources/js/types/student-decision.ts`
- Local page props/filters: `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue`

---

## Validation

- Use Zod schemas with `toTypedSchema(...)` when the page already uses vee-validate.
- Inertia submissions can coexist with typed validation when the UI needs rich client validation.
- Keep frontend validation aligned with backend validation rules where practical.

Examples:

- Zod + vee-validate form schema: `resources/js/pages/students/Create.vue`
- Another typed schema-heavy page: `resources/js/pages/TuitionPlans/Create.vue`

---

## Common Patterns

- `defineProps<Props>()` and `withDefaults(defineProps<Props>(), ...)`.
- Generic composables for typed table/filter flows: `useServerTableQuery<T>()`.
- Shared route helpers to avoid stringly typed URLs: `resources/js/utils/routes.ts`.

---

## Forbidden Patterns

- Avoid new `any` usage when a local interface or shared type is easy to define.
- Avoid hard-coded literal URLs when a route helper already exists.
- Avoid unnecessary type assertions when inference or explicit interfaces are enough.

Current reality note: older files still contain some `any` and looser typing, so prefer incremental improvement over wide refactors.
