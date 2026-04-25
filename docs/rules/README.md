# Rules Index

Last updated: 2026-04-26
Status: Active — 21 rule files

This folder contains the canonical coding rules for Swinx.
All AI tools should use these as the authoritative reference — not tool-specific config files.

---

## Migration & Refactoring Rules (read first if working with legacy code)

| File | When to read | File types |
|---|---|---|
| `frozen-zones.md` | **ALWAYS** — defines what is frozen vs greenfield | — |
| `refactor-playbook.md` | Migrating a feature from legacy to Module | `**/*.php` |
| `reference-implementations.md` | Copy-ready patterns (Actions, Queries, Controllers, Vue) | `**/*.{php,vue,ts}` |

## Backend Rules (PHP / Laravel)

| File | When to read | File types |
|---|---|---|
| `backend.md` | Creating/modifying controllers, actions, queries | `**/*.php` |
| `architecture.md` | Creating a new module, cross-module features | — |
| `naming.md` | Naming any PHP class, method, route, or DB column | `**/*.php` |
| `contracts.md` | Module A needs data/behavior from Module B | `**/*.php` |
| `pattern.md` | Step-by-step implementation of a module feature | `**/*.php` |
| `security.md` | Gates, policies, authorization logic | `**/*.php` |
| `migration.md` | Moving legacy code from `app/Services/` into modules | `**/*.php` |
| `testing.md` | Writing Pest tests for filterable index flows | `**/*.php` |
| `laravel-boost.md` | General Laravel best practices (broad reference) | `**/*.php` |

## Frontend Rules (Vue / TypeScript)

| File | When to read | File types |
|---|---|---|
| `frontend.md` | Creating/modifying Vue components or pages | `**/*.{vue,ts}` |
| `filtering.md` | Building any list/index page with filters + pagination | `**/*.{vue,ts,php}` |
| `api-interaction.md` | Calling backend APIs from Vue | `**/*.{vue,ts}` |
| `shadcn_vue_conventions.md` | Using Reka-UI/Shadcn components (Select, Form, etc.) | `**/*.vue` |

## Cross-cutting Rules

| File | When to read | File types |
|---|---|---|
| `code-style.md` | General coding style baseline (PHP + Vue) | `**/*.{php,vue,ts}` |
| `realtime.md` | Broadcasting/Echo — transport layer rules | `**/*.{php,vue,ts}` |
| `realtime_notification.md` | Notification V2 specific broadcasting rules | `**/*.{php,vue,ts}` |
| `naming.md` | Full naming conventions for all layers | — |

## Reference / Config Files

| File | Purpose |
|---|---|
| `structure.md` | Project directory layout overview (reference) |
| `tech.md` | Technology stack summary (reference) |

---

## Quick mapping by task

| Task | Must read |
|---|---|
| **Refactor legacy feature to Module** | `frozen-zones.md`, `refactor-playbook.md`, `reference-implementations.md` |
| **Check code before commit** | `reference-implementations.md` (Anti-patterns section) |
| Add new feature to existing module | `frozen-zones.md`, `backend.md`, `pattern.md`, `naming.md` |
| Create a new module | `architecture.md`, `backend.md`, `naming.md`, `contracts.md` |
| Add list/index page with filters | `frontend.md`, `filtering.md`, `backend.md` |
| Add modal/drawer form | `frontend.md`, `api-interaction.md`, `shadcn_vue_conventions.md` |
| Cross-module data access | `contracts.md`, `architecture.md` |
| Add auth/permission check | `security.md` |
| Add real-time notification | `realtime.md`, `realtime_notification.md` |
| Write tests | `testing.md` |
| Migrate legacy controller | `frozen-zones.md`, `refactor-playbook.md`, `backend.md` |
