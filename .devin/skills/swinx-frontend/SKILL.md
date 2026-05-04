---
name: swinx-frontend
description: "Unified Inertia v3 + Vue 3 frontend standards for Swinx. Use when writing, refactoring, or reviewing any frontend code. Covers useForm, data loading, flash, Wayfinder, useDataTable, @inertiaui/modal-vue (route-based modals, local modals, slideover, nested), and legacy migration."
argument-hint: "[FeatureName] (optional — triggers refactor mode)"
allowed-tools:
  - read
  - grep
  - glob
  - exec
  - edit
permissions:
  allow:
    - Write(app/Modules/**)
    - Write(resources/js/pages/**)
    - Write(resources/js/utils/routes.ts)
    - Exec(./scripts/dev.sh **)
  ask:
    - Write(routes/**)
    - Write(app/Http/**)
triggers:
  - user
  - model
---

# Swinx Frontend — Inertia v3 + Vue 3 Standards

> Single source of truth for all frontend work. Adapt backend patterns to `CLAUDE.md` + `docs/rules/`.
> Detailed API in `references/` — loaded on demand (noted inline).

---

## Intent Detection

Detect task type, then load the right reference:

| Signal | Mode | Load |
|--------|------|------|
| `$ARGUMENTS` provided / "refactor" / "migrate" / "legacy" | **Refactor** | `references/refactor-procedure.md` |
| "new page" / "create feature" / "build" | **New feature** | Core Procedures below |
| "filter" / "list page" / "table" / "useDataTable" | **List page** | `references/swinx-patterns.md` |
| "modal" / "drawer" / "dialog form" / "ModalLink" | **Modal form** | `references/swinx-patterns.md` + `references/modal-inertiaui.md` |
| "review" / "check" / "audit" | **Review** | Gotchas + Forbidden Patterns below |
| "v2" / "migrate to v3" / "upgrade" | **Migration** | `references/migration-v2-v3.md` |
| Needs Inertia API details | **Reference** | Specific `references/*.md` file |

---

## Hard Gates

Non-negotiable rules. Enforce on every task regardless of mode.

1. **Inertia page forms → `useForm`**. Never vee-validate+Zod on Inertia pages.
2. **Filter/pagination pages → `useDataTable`**. Never `useInertiaFilters` or `useServerTableQuery`.
3. **New flash → `Inertia::flash()`**. Access via `page.flash` NOT `page.props.flash`.
4. **Routes → `route()` helper or `systemRoutes`**. Never literal URL strings.
5. **Validation → `FormRequest`**. Never validate inline in controllers.
6. **New business logic → `app/Modules/{Domain}/Actions/`**. Never `app/Services/`.
7. **New routes → `app/Modules/{Domain}/routes/`**. Never `routes/web/*.php` (frozen zones).
8. **API responses → `ApiResponse::success()`**. Never `response()->json()` directly.

---

## Decision Tables

### Forms — which helper?

| Scenario | Use | Key point |
|----------|-----|-----------|
| CRUD page form | `useForm` | `v-model`, full programmatic control |
| Simple template form | `<Form>` component | Native `name` attrs, slot props |
| Edit form with initial data | `useForm` or `<Form>` `:default-value` | Both work |
| Modal/drawer (Swinx) | `useForm` + modal ref | See `references/swinx-patterns.md` |
| JSON API (no page visit) | `useHttp` | Returns JSON, no navigation |
| Real-time validation | `useForm` + precognition | `form.validate('field')` on `@change` |
| File upload | `useForm` | Auto multipart + `form.progress.percentage` |

### Data Loading — which backend helper?

| Scenario | Backend | Frontend |
|----------|---------|----------|
| Heavy data after render | `Inertia::defer(fn)` | `<Deferred data="prop">` |
| Grouped deferred loads | `Inertia::defer(fn, 'group')` | One request per group |
| Infinite scroll list | `Inertia::scroll(cursorPaginate)` | `<InfiniteScroll data="prop">` |
| Load on scroll into view | `Inertia::optional(fn)` | `<WhenVisible data="prop">` |
| Periodic refresh | Any props | `usePoll(interval)` |
| Accumulate across requests | `Inertia::merge([...])` | Reset: `router.reload({ reset: ['prop'] })` |
| Load once, never re-fetch | `Inertia::once(fn)` | Automatic |
| Refresh specific props | Closures `fn ()` | `router.reload({ only: [...] })` |
| **Filter/sort/paginate (Swinx)** | **Standard Laravel** | **`useDataTable`** — see `references/swinx-patterns.md` |

### Flash — server vs client?

| Scenario | Use |
|----------|-----|
| After form submit (redirect) | `Inertia::flash('key', 'value')->back()` |
| Client-only notification | `router.flash({ key: value })` |
| Toast on any flash | `router.on('flash', ...)` in layout |

### Modals — which type? (`@inertiaui/modal-vue`)

| Scenario | Type | Trigger | Key point |
|----------|------|---------|-----------|
| CRUD create/edit form | **Route-based modal** | `<ModalLink :href="route(...)">` | Separate Vue page wrapped in `<Modal>` |
| Simple confirm dialog | **Pinia store** | `useConfirmDialogStore` | NOT `<Modal>` — use existing Swinx pattern |
| Inline confirm (no route) | **Local modal** | `<ModalLink href="#name">` + `<Modal name="...">` | Same page, no server request |
| Detail panel / sidebar | **Slideover** | `<ModalLink ... slideover>` | Side panel instead of center overlay |
| Open from code (no link) | **Programmatic** | `visitModal('/path')` | `import { visitModal } from '@inertiaui/modal-vue'` |
| Form in nested modal | **Axios** | `axios.post()` + `modalRef.close()` | `router.post()` closes ALL stacked modals |

> Full API: `references/modal-inertiaui.md`. Swinx modal form patterns: `references/swinx-patterns.md`.

---

## Core Procedures

### Build an Inertia page form

1. Default to `useForm` in Swinx. Use `<Form>` only for simple template-only forms.
2. Backend: `FormRequest` → `Action::run()` → `Inertia::flash('message', '...')->back()`.
3. Frontend: `form.errors.*` for validation, `form.processing` to disable submit.
4. Edit forms: call `form.defaults()` in `onSuccess`.

```typescript
const form = useForm('form-key', { name: '', email: '' });
form.submit(storeContact(), {
    preserveScroll: true,
    onSuccess: () => form.defaults(),
});
```

```php
public function store(StoreRequest $request): RedirectResponse
{
    CreateContactAction::run($request->validated());
    return Inertia::flash('message', 'Created.')->back();
}
```

> For `<Form>` component, precognition, optimistic updates, file uploads: read `references/forms-api.md`.

### Build a data-loading page

1. Heavy props → `Inertia::defer(fn)`. Lists → `Inertia::scroll(cursorPaginate)`.
2. Below-the-fold → `Inertia::optional(fn)` + `<WhenVisible>`.
3. Use closures `fn ()` for partial-reload-friendly props.

```php
return Inertia::render('Dashboard', [
    'user' => $user,
    'stats' => Inertia::defer(fn () => StatsQuery::run()),
    'contacts' => Inertia::scroll(ContactResource::collection($query->cursorPaginate(15))),
]);
```

> For merge, once, polling, partial reloads: read `references/data-loading-api.md`.

### Set up flash toasts

1. Backend: `Inertia::flash('message', '...')` on any response.
2. Frontend: `useFlashToast()` once in `AppLayout.vue`.
3. Access: `usePage().flash` — **NOT** `usePage().props.flash`.

> Full flash patterns + legacy coexistence: read `references/swinx-patterns.md`.

### Use Wayfinder for type-safe routes

```typescript
import { index, update } from '@/wayfinder/App/Http/Controllers/ContactController';
form.submit(update({ contact: id }), { preserveScroll: true });  // useForm
router.visit(index.url());                                        // router
<Link :href="index.url()" prefetch>Contacts</Link>               // Link
<Form v-bind="update.form({ contact: id })">                     // Form component
```

> Full Wayfinder API, layouts, config: read `references/layouts-config-wayfinder.md`.

### Build a route-based modal form (Swinx standard)

1. **Backend**: Standard controller using `Inertia::render()` — NOT `Inertia::modal()`.
2. **Modal page**: Wrap content in `<Modal ref="modalRef">`. Use `useForm` inside.
3. **Parent page**: Open with `<ModalLink :href="route(...)" as="button">`.
4. **Close**: `modalRef.value?.close()` in `onSuccess`. Create forms also `form.reset()`.

```vue
<!-- Create.vue (modal page) -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';
import { ref } from 'vue';

const modalRef = ref<InstanceType<typeof Modal> | null>(null);
const form = useForm({ name: '', code: '' });

const submit = () => {
    form.post(route('entity.store'), {
        preserveScroll: true,
        onSuccess: () => { modalRef.value?.close(); form.reset(); },
    });
};
</script>
<template>
    <Modal ref="modalRef">
        <div class="p-6">
            <form @submit.prevent="submit">...</form>
        </div>
    </Modal>
</template>
```

```vue
<!-- Index.vue (parent page) — trigger -->
<ModalLink :href="route('entity.create')" as="button" class="...">
    <Plus class="h-4 w-4" /> Add Item
</ModalLink>
```

> Full InertiaUI Modal API (props, events, config, local modals, nesting, slideover): read `references/modal-inertiaui.md`.

---

## Gotchas

Mistakes the agent will make without these corrections:

1. **`page.flash` not `page.props.flash`** — v3 moved flash off `props`.
2. **No `resolve()`/`setup()` in createInertiaApp** — `@inertiajs/vite` plugin handles both.
3. **`<Form>` uses `name` attrs, not `v-model`** — native HTML names, not Vue bindings.
4. **`Inertia::lazy()` is removed** — use `Inertia::defer(fn)` or `Inertia::optional(fn)`.
5. **`preserveScroll: 'errors'` is the v3 default** — set in `defaults.visitOptions`, not per-visit.
6. **`useRemember()` is deprecated** — use `useForm('key', data)` with form key.
7. **`router.reload({ reset: [...] })` for InfiniteScroll filter changes** — without reset, stale data accumulates.
8. **Wayfinder `.form()` ≠ `.url()`** — `.form()` returns `{ action, method }` for `<Form>`. Regular `action()` returns `{ url, method }`.
9. **`usePoll()` throttled in background tabs** — ~90% reduction. Use `keepAlive: true` only if needed.
10. **`Inertia::scroll()` requires `cursorPaginate()`** — `paginate()` won't work.
11. **PHP `[]` → JS Array trap** — `filters?.sort` resolves to `Array.prototype.sort`. Use `typeof` guard. See `docs/rules/frontend-gotchas.md`.
12. **Legacy flash still works** — 239 controllers use `back()->with('success', ...)` → `page.props.flash`. Don't break it. New code uses `Inertia::flash()` → `page.flash`.
13. **`router.visit()` to open a modal page** — renders as full page, not modal. Use `<ModalLink>` or `visitModal()`.
14. **`Inertia::modal()` not available** — Swinx has no backend package. Use `Inertia::render()` for modal pages.
15. **`router.post()` in nested modals** — closes ALL stacked modals. Use Axios for nested modal form submissions.
16. **Confirm dialogs ≠ `<Modal>`** — use `useConfirmDialogStore` (Pinia) for simple confirms. `<Modal>` is for route-based CRUD forms.
17. **`DatePicker` inside modal → must pass `:portal-to="modalContentRef"`** — `useNativeDialog: false` creates a focus trap; without `portalTo`, `PopoverPortal` teleports outside the trap and the calendar closes immediately on open. See `references/modal-inertiaui.md` § UI Component Gotchas.
18. **`Select` inside modal → needs `useNativeDialog: false` in app.ts** — already set globally in Swinx; don't revert it. Native `<dialog>` top-layer puts `SelectPortal` (teleported to `body`) behind the modal.
19. **`TimePicker` (reka-ui `TimeFieldRoot`) inside modal** — works with no extra setup (no Popover/portal involved).
20. **No `toast.success()` in `onSuccess` for full-page or modal forms** — when the backend uses `Inertia::flash()`, `useFlashToast()` in AppLayout fires the toast automatically. Calling `toast.success()` manually causes a duplicate. Only use manual toast for client-side-only feedback that has no backend flash (rare).
21. **`vee-validate` + Zod on Inertia pages is a hard violation** — vee-validate is only for non-navigating modal/drawer forms that call JSON APIs. Inertia page forms (`students/Edit.vue`, `students/Create.vue`, etc.) **must** use `useForm` from `@inertiajs/vue3`. The server-side `FormRequest` errors land in `form.errors.*` automatically on any 422 response.

---

## Forbidden Patterns

| Wrong | Correct |
|-------|---------|
| `resolvePageComponent` import | Remove — `@inertiajs/vite` handles it |
| `setup({ el, App, props, plugin })` | Omit — plugin handles createInertiaApp |
| `page.props.flash` (new code) | `page.flash` |
| `Inertia::lazy(fn)` | `Inertia::defer(fn)` or `Inertia::optional(fn)` |
| `useRemember()` | `useForm('key', data)` |
| `v-model` inside `<Form>` | `name="field"` attribute |
| `axios.post()` for JSON | `useHttp()` |
| `preserveScroll: true` everywhere | `defaults.visitOptions = { preserveScroll: 'errors' }` |
| `paginate()` with InfiniteScroll | `cursorPaginate()` |
| vee-validate on Inertia pages | `useForm` from `@inertiajs/vue3` |
| `useInertiaFilters` / `useServerTableQuery` | `useDataTable` |
| `response()->json()` in controllers | `ApiResponse::success()` |
| Literal URL in frontend | `route()` or `systemRoutes` |
| New logic in `app/Services/` | `app/Modules/{Domain}/Actions/` |
| New routes in `routes/web/*.php` | `app/Modules/{Domain}/routes/` |
| `router.visit()` to modal page | `<ModalLink>` or `visitModal()` |
| `Inertia::modal()` in controller | `Inertia::render()` (no backend package) |
| `router.post()` in nested modal | `axios.post()` + `modalRef.close()` |
| `<Modal>` for confirm dialogs | `useConfirmDialogStore` (Pinia) |
| `DatePicker` in modal without `portalTo` | `<DatePicker :portal-to="modalContentRef ?? undefined">` |
| Revert `useNativeDialog: false` in app.ts | Keep it — required for Select dropdowns above modal |
| `input[type=date]` / `input[type=time]` in forms | `<DatePicker>` / `<TimePicker>` from `@/components/ui` |
| `toast.success()` in form `onSuccess` | Remove — `Inertia::flash()` + `useFlashToast()` handles it automatically |
| `vee-validate` + Zod on Inertia page forms | `useForm` from `@inertiajs/vue3` + `form.errors.*` for validation display |

---

## Reference Files

Load on demand based on task:

| File | When to read |
|------|-------------|
| `references/refactor-procedure.md` | Migrating legacy feature to modular architecture |
| `references/swinx-patterns.md` | useDataTable, modal forms, systemRoutes, flash coexistence |
| `references/forms-api.md` | useForm API, `<Form>` props/slots, uploads, precognition, optimistic |
| `references/data-loading-api.md` | defer, scroll, optional, poll, merge, once, partial reloads |
| `references/navigation-events-api.md` | Links, router methods, all 12 events, useHttp |
| `references/layouts-config-wayfinder.md` | Layouts, Head, config/inertia.php, Wayfinder API |
| `references/modal-inertiaui.md` | @inertiaui/modal-vue API, components, config, local/nested/slideover |
| `references/migration-v2-v3.md` | v2→v3 breaking changes, removed APIs, new features |

## Docs Rules (project)

| File | Governs |
|------|---------|
| `docs/rules/frontend.md` | Vue component patterns |
| `docs/rules/frontend-gotchas.md` | PHP-to-JS pitfalls |
| `docs/rules/filtering.md` | useDataTable + Laravel filter rules |
| `docs/rules/backend.md` | Controller/Action/Query rules |
| `docs/rules/architecture.md` | Module boundary rules |
| `docs/rules/naming.md` | Naming conventions |
| `docs/rules/frozen-zones.md` | Zones you must NOT modify |
| `docs/rules/reference-implementations.md` | Copy-ready code templates |
| `docs/rules/toast-patterns.md` | Toast system docs |
