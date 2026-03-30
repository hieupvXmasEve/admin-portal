# Component Guidelines

> How components are built in this project.

---

## Overview

- New Vue files use `<script setup lang="ts">`.
- Shared UI is built on `reka-ui`/shadcn-style components under `@/components/ui`.
- Feature pages compose shared filters, table wrappers, dialogs, and form inputs instead of rebuilding primitives.

---

## Component Structure

- Imports first, then local interfaces/types, then `defineProps` / `defineEmits`, then composable/state setup, then handlers.
- Prefer a single semantic wrapper when it improves layout and readability, and avoid unnecessary wrapper churn in stable shared components unless the task requires it.
- Use shared wrappers for repeated UI patterns like filter panels and server-paginated tables.

Examples:

- Simple shared component with typed props/emits: `resources/js/components/filters/FilterPanel.vue`
- Shared table wrapper with slot passthrough: `resources/js/components/tables/ServerPaginatedDataTable.vue`
- Full page composition with shared components: `resources/js/pages/Admin/Reports/StudentDecisions/Index.vue`

---

## Props Conventions

- Prefer local `interface Props` and `defineProps<Props>()`.
- Use `withDefaults(...)` when defaults matter.
- Use typed emits with `defineEmits`.
- For table/data wrappers, expose strongly named props rather than generic bags when possible.

---

## Styling Patterns

- Tailwind CSS is the default styling path.
- Reuse UI primitives from `@/components/ui` before writing ad hoc markup.
- Shared pages often use `Card`, `Button`, `Input`, `Dialog`, and custom filter/table wrappers.
- Keep spacing with layout utilities (`space-y-*`, `gap-*`) rather than margin-heavy one-offs.

---

## Accessibility

- Prefer accessible shadcn/reka primitives for dialogs, selects, buttons, and form controls.
- Use visible labels for form fields unless the surrounding component already provides accessible labeling.
- Preserve keyboard/navigation behavior by extending shared UI primitives instead of replacing them with raw divs.

---

## Common Mistakes

- Do not bypass shared UI primitives for common controls without a reason.
- Do not leave page-local table/filter logic duplicated when shared server-table components already exist.
- Do not mix untyped props with typed page contracts in the same new component.
- Do not assume every page uses the newest shared patterns; some older pages still use legacy local glue.
