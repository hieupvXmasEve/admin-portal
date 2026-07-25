---
title: Frontend Rules
status: active
owner: Frontend Team
last_verified: 2026-07-25
scope: engineering-rules
applies_to:
  - resources/js
  - resources/css
---

# Frontend Rules

Swinx uses Vue 3, TypeScript, Inertia v3, Tailwind CSS 4, shadcn-vue/Reka UI,
and Lucide icons. Do not copy Inertia v2 examples or restore removed APIs.

## Structure and types

- New Vue components use one root element and `<script setup lang="ts">`.
- Pages live under `resources/js/pages/{PascalCaseFeature}` and map explicitly
  to the Inertia route.
- Shared components, composables, and types live in their existing
  `resources/js/components`, `composables`, and `types` owners.
- Component and type names are PascalCase; composables use a descriptive
  `useXxx` name.
- Use strict interfaces. Laravel prop keys and matching TypeScript properties
  remain snake_case.
- Keep business decisions on the backend; the UI renders states and allowed
  operations supplied by the server.
- Use `route(...)` or typed route constants, never new literal endpoint paths.
- Use `lucide-vue-next` and existing `@/components/ui` primitives before adding
  custom equivalents.

## Inertia v3

- Deferred UI uses `<Deferred data="prop_name">`, a `#fallback` skeleton, and
  `#default="{ reloading }"`.
- Do not bind an array to `Deferred.data`.
- Read flash through Inertia v3's flash surface and produce it on the backend
  with `Inertia::flash()`. Do not depend on `$page.props.flash`.
- Use the v3 event names (`httpException`, `networkError`) and
  `router.cancelAll()`; do not restore their removed v2 names.
- When a frontend build is not reflected, use the project wrapper to run the
  Vite dev server or build.

## Form decision

Choose one form path by interaction type:

| Interaction | Form and transport |
|---|---|
| Full Inertia page create/edit/login | Inertia `useForm` |
| Inertia modal that redirects or reloads props | Inertia `useForm` |
| Non-navigating JSON modal/drawer/inline flow | `vee-validate` + Zod + `useApi` |

Do not combine Inertia `useForm` and vee-validate in the same form.

### Inertia forms

- Let FormRequests and server validation own the contract.
- Read validation from `form.errors`.
- Disable or show progress while `form.processing`.
- On success, close the modal and reset create-only state as required.
- Use the automatic Inertia flash/toast path rather than adding a duplicate
  success toast.

### JSON forms

- Define a typed Zod schema mirroring the FormRequest and initialize every form
  field.
- Hydrate edit values with `setValues` and clear local state on cancellation
  when appropriate.
- Initialize `useApi` once per component from
  `@/composables/useApiRequest`; `useApiRequest` is the compatibility alias.
- Supply response generics, await every request, and use named routes.
- Map backend validation errors into the form and show a contextual error toast
  for unexpected failures.
- After success, update local state or reload only the required Inertia props.
- Do not use raw Axios or `fetch` for application API calls.

## Toasts

- Inertia mutations use backend `Inertia::flash()` and the app-level
  `useFlashToast()` integration.
- JSON mutations use `toast.success()` or `toast.error()` at the point the
  request result is known.
- Do not show success toasts for GET navigation or duplicate the flash toast in
  an `onSuccess` callback.

## Filters and tables

Every server-filtered, sorted, or paginated list uses `useDataTable`. Legacy
`useInertiaFilters` and `useServerTableQuery` usages are migrated when the page
is materially edited. Follow [filtering.md](filtering.md).

## Component invariants

- Every `SelectItem` value is a non-empty string. Use a sentinel such as `all`
  for reset and convert numeric IDs with `String(id)`.
- Bind Reka checkbox state through `:model-value` and
  `@update:model-value`.
- Use `DatePicker` and `TimePicker` primitives rather than native date/time
  inputs.
- In a modal, pass its content element to `DatePicker` through `portal-to` so
  overlays stay inside the correct stacking context.
- Keep `useNativeDialog: false` in `resources/js/app.ts`; this is required for
  Select overlays above modal content.
- Give hydrated controls explicit defaults that match their item value types.

## Theme and selection

The accent token is the brand green. A solid accent or primary surface must
pair its foreground token.

| State | Classes |
|---|---|
| Selected row or card | `border-primary bg-primary/5 ring-1 ring-primary/30` |
| Row hover | `hover:bg-muted/50` |
| Menu or icon hover | `hover:bg-accent hover:text-accent-foreground` |
| Solid accent surface | `bg-accent text-accent-foreground` |

Do not use bare `bg-accent` or `bg-primary` on content with default dark text.

## Filesystem casing

On case-insensitive macOS filesystems, rename a page directory through a
temporary name, then update every `Inertia::render()` path. Existing lowercase
or kebab-case directories remain legacy; new directories are PascalCase.

## Review checklist

- Inertia v3 APIs, prop casing, and flash behavior are correct.
- The form path matches its navigation context.
- Routes and API calls use project helpers.
- Existing UI primitives and icons are reused.
- Select, checkbox, picker, modal, and theme invariants are preserved.
- Loading, error, empty, and disabled states are present where the interaction
  needs them.
