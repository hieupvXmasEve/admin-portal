# Database Guidelines

> Database patterns and conventions for this project.

---

## Overview

- ORM baseline: Laravel Eloquent.
- Read-heavy flows often use dedicated Query classes that return paginated arrays for Inertia pages.
- Multi-write mutations should use `DB::transaction(...)`.
- Campus scoping is a real constraint in many reads; check for `app('campus')`, campus session state, or explicit campus checks before writing a query.

---

## Query Patterns

- Prefer `Model::query()` with eager loading, `whereHas`, `withSum`, and allowlisted sort fields.
- Shape read models in Query classes instead of leaking raw models directly to pages.
- Use pagination with `withQueryString()` for server-driven tables.
- For complex reporting, raw joins and `selectRaw` do exist, but they are exceptions, not the default.

Code-verified examples:

- Eloquent + eager loading + `withSum`: `app/Modules/Finance/Queries/ListPaymentsQuery.php`
- Campus-scoped query flow: `app/Modules/Finance/Queries/ListPaymentsQuery.php`
- Transactional mutation flow: `app/Modules/Academic/Actions/RecordStudentActionAction.php`

---

## Migrations

- Use normal Laravel migrations under `database/migrations`.
- Follow existing schema names exactly; this repo has strong contract sensitivity around finance and notification tables.
- When changing existing columns, preserve prior attributes explicitly.
- Prefer additive changes with clear compatibility notes when old and new models coexist.

Run paths already used in repo docs/scripts:

- `php artisan migrate`
- `php artisan test`

---

## Naming Conventions

- Tables: snake_case plural.
- Columns: snake_case.
- Route/query filter keys are also usually snake_case (`per_page`, `issued_from`, `decision_signer`).
- Keep contract field names exact when they are already established in docs/code, for example finance and student action fields documented in `docs/code-standards.md`.

---

## Common Mistakes

- Do not guess campus scoping; verify how the surrounding feature isolates campus data.
- Do not bypass Eloquent relationships when existing models already express the relation.
- Do not mix new finance writes with legacy `payment_allocations`; current baseline uses `payment_applications` and `discount_allocations`.
- Do not hide validation inside a query class unless that pattern already exists nearby; some legacy queries do this, but it is not the preferred pattern.
