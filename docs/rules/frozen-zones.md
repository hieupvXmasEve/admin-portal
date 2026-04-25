---
paths: '**/*.php'
---

# Frozen Zones — Legacy Code Boundaries

Last updated: 2026-04-26
Status: Active

This file defines which directories are **frozen** (no new code allowed) versus **greenfield** (where new code must live).

---

## The Rule

> **If it's in a Frozen Zone, touch it only to fix bugs. Never add new methods, classes, or routes there.**

---

## Frozen Zones (Legacy — Bug Fix Only)

These directories exist from the old architecture. Do NOT add new code here.

### `app/Services/` — FROZEN

**Reason:** Service classes were replaced by Actions + Queries inside Modules.

```
app/Services/                ← FROZEN: Bug fix only. No new Service classes.
```

**Allowed:** Fix a bug in an existing method.
**Forbidden:** Create a new Service class. Add a new method to an existing Service. Extract Service logic into another Service.

**When refactoring:** Extract the logic into `app/Modules/{Domain}/Actions/` or `app/Modules/{Domain}/Queries/`. Then remove the method from the Service once all callers are migrated.

---

### `app/Http/Controllers/` (top-level) — FROZEN

**Reason:** Module controllers live inside `app/Modules/{Domain}/Http/`. The top-level `app/Http/Controllers/` is legacy.

```
app/Http/Controllers/        ← FROZEN: Bug fix only. No new controller files.
```

**Exception:** `app/Http/Controllers/Controller.php` (base class) is shared — can be modified if the base class needs updating.

**Allowed:** Fix a bug in an existing controller method.
**Forbidden:** Create a new controller file here. Add a new action method to an existing controller here.

**When refactoring:** Create the new controller in `app/Modules/{Domain}/Http/Web/Admin/` or the appropriate sub-path, then redirect the old route to the new controller.

---

### `routes/web/*.php` (top-level) — FROZEN for new routes

**Reason:** Module routes live in `app/Modules/{Domain}/routes/`. The top-level `routes/web/` files are legacy.

```
routes/web/                  ← FROZEN: No new routes. Bug fix only.
routes/api/                  ← FROZEN: No new routes. Bug fix only.
```

**Allowed:** Fix a misconfigured route (wrong middleware, wrong controller reference).
**Forbidden:** Add a new route. Re-order or restructure this file.

**When refactoring:** Register new routes in `app/Modules/{Domain}/routes/web.php` or `api.php`. The old route file entry can be left or removed once confirmed no external links depend on it.

---

## Frontend Frozen Zones

The following Vue patterns are **legacy** — fix when you touch a file, but do not introduce in new code.

### `useInertiaFilters` and `useServerTableQuery` on list pages — LEGACY

```
useInertiaFilters in resources/js/pages/     ← LEGACY (26 pages): Bug fix only
useServerTableQuery in resources/js/pages/   ← LEGACY (9 pages): Bug fix only
DataTable + DataPagination with inline inputs ← LEGACY component set
```

**Allowed:** Leave existing pages that use these composables as-is unless in refactor scope.  
**Forbidden:** Use `useInertiaFilters` or `useServerTableQuery` on any new list/index page.  
**When refactoring:** Replace with `useDataTable` (see `docs/useDataTable-examples.md`).

---

### `kebab-case` and `lowercase` page folders — LEGACY NAMING

```
resources/js/pages/rooms/          ← LEGACY naming (existing — do not add to)
resources/js/pages/curriculum-versions/  ← LEGACY naming (existing — do not add to)
resources/js/pages/auth/           ← LEGACY naming (existing — do not add to)
```

**Allowed:** Add files inside existing legacy-named folders when fixing a bug.  
**Forbidden:** Create a new folder in `kebab-case` or `lowercase` under `resources/js/pages/`.  
**When refactoring:** Rename folder to `PascalCase` as part of refactor scope.

---

## Greenfield Zones (New Code Goes Here)

These are where all new code must live.

**Backend:**

| What | Where |
|---|---|
| New business logic | `app/Modules/{Domain}/Actions/` |
| Complex read queries | `app/Modules/{Domain}/Queries/` |
| New web controllers | `app/Modules/{Domain}/Http/Web/Admin/` or `Student/` |
| New API controllers | `app/Modules/{Domain}/Http/Api/Admin/` or `Student/` or `Lecturer/` |
| Request validation | `app/Modules/{Domain}/Http/Requests/{Domain}/` |
| Route registration | `app/Modules/{Domain}/routes/web.php` or `api.php` |
| Cross-module contracts | `app/Shared/Contracts/{Domain}/` |

**Frontend:**

| What | Where | Pattern |
|---|---|---|
| New Vue pages | `resources/js/pages/{PascalCase}/` | PascalCase folder name |
| New composables | `resources/js/composables/` | TypeScript `.ts` files |
| List/index page composable | In page file | `useDataTable` |
| List/index page components | In page template | shadcn-vue primitives (see `docs/useDataTable-examples.md`) |
| Inertia page form | In page file | `useForm` from `@inertiajs/vue3` |
| Modal/drawer form | In page file | `vee-validate` + `zod` + `useApi` |
| Route references | Everywhere | `route('name')` or `systemRoutes.x.y()` |

---

## Quick Decision Tree

```
Q: I need to add new feature X for domain Y.
   Where does the code go?

   Backend:
   → Business logic       → app/Modules/Y/Actions/
   → Read-only data       → app/Modules/Y/Queries/
   → HTTP handler         → app/Modules/Y/Http/Web/ or Http/Api/
   → Cross-module data    → app/Shared/Contracts/Y/ (Contract + implementation)

   Frontend:
   → New page             → resources/js/pages/Y/  (PascalCase folder)
   → List page with table → useDataTable (see docs/useDataTable-examples.md)
   → Full page form       → useForm from @inertiajs/vue3
   → Modal/drawer form    → vee-validate + zod + useApi

Q: I need to fix a bug in existing code in app/Services/OldService.php

   → Allowed. Fix the bug in place.
   → Do NOT add new methods while you're there.
   → Optionally: note in a comment that this method is a refactor candidate.

Q: I need to fix a bug in resources/js/pages/rooms/Index.vue
   (which uses the legacy useInertiaFilters pattern)

   → Allowed. Fix the bug in place.
   → Do NOT migrate to useDataTable unless this is a planned refactor.
   → Do NOT add new filter inputs using the old inline pattern if avoidable.
```

---

## Why This Matters for AI Tools

When generating or suggesting code, always check:

**Backend:**
1. **Is the target file/directory in a Frozen Zone?** If yes, do not create new files or methods there.
2. **Is there an existing Module for this domain?** Check `app/Modules/` first.
3. **Does the task require new business logic?** → Always `Actions/`.
4. **Does the task require reading/aggregating data?** → Always `Queries/`.

**Frontend:**
5. **Is this a new list/index page?** → Use `useDataTable` (see `docs/useDataTable-examples.md`).
6. **Is this an Inertia page form?** → Use `useForm` from `@inertiajs/vue3`. No vee-validate.
7. **Is this a modal/drawer form?** → Use `vee-validate` + `zod` + `useApi`. No Inertia `useForm`.
8. **Does the URL use a literal string?** → Replace with `route()` or `systemRoutes.*`.
9. **Is a new page folder being created?** → Must be `PascalCase`.

If you're unsure which Module owns a feature, read `docs/rules/architecture.md`.
