---
title: Legacy Migration Rules
status: active
owner: Platform Team
last_verified: 2026-07-26
scope: engineering-rules
applies_to:
  - app/Services
  - app/Http/Controllers
  - routes/web
  - routes/api
  - resources/js/pages
---

# Legacy Migration Rules

Migrate one owned workflow at a time. Preserve public behavior first, then move
ownership into the module without creating a second live implementation.

## Frozen locations

These are bug-fix-only unless an intentional migration is moving their behavior
out:

- `app/Services/`: no new service classes or new business-use-case methods.
- top-level `app/Http/Controllers/`: no new feature controllers or actions;
  the shared base controller is exempt.
- top-level `routes/web/` and `routes/api/`: no new feature routes.
- pages using `useInertiaFilters` or `useServerTableQuery`: do not introduce or
  extend either legacy composable.
- lowercase or kebab-case page directories: do not create another directory
  with legacy casing.

New code goes to the owning `app/Modules/{Domain}` paths, `useDataTable`, and
PascalCase page directories described by the other rule files.

## Machine-enforced debt contract

`app/Support/MigrationDebt/MigrationDebtContract.php` is the immutable source
for migration-debt rules, scan roots, runtime surfaces, shared-model ownership,
and baseline ceilings. The matching baselines in
`config/migration_debt.php` must equal those ceilings; when verified debt is
removed, ratchet both values down in the same change.

For frozen locations, `config/migration_debt_paths.php` must remain the exact
live path set. Remove stale paths when their debt is retired, and never replace
them with unrelated paths merely to preserve an aggregate count. Shadow
occurrence metrics are broader diagnostic evidence only and do not change the
guarded-count semantics.

Run `./scripts/dev.sh artisan migration-debt:inventory --check` as the
migration-debt gate. The executable checks and report contract live in
`app/Support/MigrationDebt/MigrationDebtGuard.php` and
`app/Support/MigrationDebt/MigrationDebtInventory.php`.

## Migration sequence

1. Identify the current route, controller, service, model ownership, page, and
   tests. Confirm consumers and stable public names.
2. Add a characterization test when the existing behavior is not already
   protected.
3. Extract writes into use-case Actions and reads into Queries in the owner
   module.
4. Add FormRequests, Policies, and contracts at their canonical boundaries.
5. Replace the legacy controller with a thin module controller.
6. Move route registration to the module while preserving route names and URLs
   unless the product change explicitly says otherwise.
7. Modernize touched list pages to `useDataTable`; use the form, route, and UI
   patterns in [frontend.md](frontend.md).
8. Update callers, prove no live references remain, and then delete the
   superseded code.
9. Run the scoped checks in [testing.md](testing.md).

Do not move a shared legacy model merely to make a folder look modular. Move
model ownership only when the context boundary is settled and all consumers use
the correct contract. Never move Academic or Finance models across their
boundary; ADR-0026 governs that cutover.

## Safe exceptions

- A strictly isolated bug fix may remain in a frozen file when migration would
  materially broaden the issue.
- Such a fix must not add a new endpoint, expand a legacy service's public
  surface, or introduce another use of a legacy frontend composable.
- If the touched workflow is being extended, migration is part of the change,
  not optional cleanup.

## Completion

A migration is complete only when the new module has one clear owner, old
routes and callers no longer target the legacy implementation, authorization
and validation remain enforced, and tests prove the preserved behavior.
