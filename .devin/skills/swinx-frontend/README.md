# swinx-frontend

Unified Inertia v3 + Vue 3 frontend skill for the Swinx project.

## What it does

- **Routes tasks** to the right reference file (dispatcher pattern)
- **Enforces hard gates** (useForm, useDataTable, Inertia::flash, etc.)
- **Provides decision tables** for forms, data loading, and flash
- **Catches gotchas** specific to Inertia v3 + Swinx conventions

## Modes

| Mode | Trigger | Reference loaded |
|------|---------|------------------|
| Refactor | `[FeatureName]` argument or "refactor"/"migrate" | `references/refactor-procedure.md` |
| New feature | "new page" / "create" / "build" | Core Procedures in SKILL.md |
| List page | "filter" / "table" / "useDataTable" | `references/swinx-patterns.md` |
| Modal form | "modal" / "drawer" / "ModalLink" | `references/swinx-patterns.md` + `references/modal-inertiaui.md` |
| Review | "review" / "check" / "audit" | Gotchas + Forbidden Patterns in SKILL.md |
| Migration | "v2" / "upgrade" | `references/migration-v2-v3.md` |

## Reference files

| File | Content |
|------|---------|
| `references/refactor-procedure.md` | Steps 1–11 for migrating legacy features |
| `references/swinx-patterns.md` | useDataTable, modal forms, systemRoutes, flash coexistence |
| `references/forms-api.md` | useForm, `<Form>`, uploads, precognition, optimistic updates |
| `references/data-loading-api.md` | defer, scroll, optional, poll, merge, once, partial reloads |
| `references/navigation-events-api.md` | Links, router, events, useHttp |
| `references/layouts-config-wayfinder.md` | Layouts, Head, config, Wayfinder |
| `references/modal-inertiaui.md` | @inertiaui/modal-vue full API, components, config, patterns |
| `references/migration-v2-v3.md` | v2→v3 breaking changes and new features |

## Quick rules

- Inertia page forms → `useForm` (never vee-validate)
- Filter pages → `useDataTable` (never useInertiaFilters)
- Flash → `Inertia::flash()` + `page.flash` (not `page.props.flash`)
- Routes → `route()` or `systemRoutes` (never literal URLs)
- New logic → `app/Modules/{Domain}/Actions/` (never `app/Services/`)
- Modals → `<ModalLink>` + route-based `<Modal>` page (never `router.visit()` to modal routes)
- Confirm dialogs → `useConfirmDialogStore` (not `<Modal>` component)
