---
title: "Phase 1: Trustworthy zero contract"
status: completed
priority: P0
effort: M
dependencies: []
---

# Phase 1: Trustworthy zero contract

## Overview

Make the inventory a truthful ratchet and freeze production cleanup before
structural changes. This phase must make `--check` green by removing at least
the 19 excess module findings; it must not raise the 568 baseline or migrate
endpoint behavior.

## Requirements

- [x] Freeze the immutable 14-rule ID set, required roots, and full manifest.
- [x] Prune 31 stale frozen-path approvals.
- [~] Make path/baseline drift fail CI — **moved to [phase 1b](./phase-01b-enforce-ratchet-in-ci.md)**.
  Recorded as satisfied here, but verification on 2026-08-16 found no test or check
  workflow in the repository at all; the guard has only ever run by hand, and the
  baseline drifted upward unnoticed.
- [x] Add shadow occurrence/coverage metrics without changing guarded file-count semantics.
- [x] Reconcile the current Academic compatibility consumer manifest (15 versus 18).
- [x] Suspend automatic cleanup rollout; pin an immutable candidate SHA.

Canonical rule IDs: `shared_model_imports`, `frozen_services`,
`frozen_controllers`, `frozen_routes`, `direct_json_responses`,
`inline_request_validation`, `missing_strict_types`,
`missing_route_strict_types`, `legacy_filter_stacks`,
`literal_frontend_urls`, `legacy_page_directories`, `migration_commands`,
`cross_context_concrete_imports`, and `removed_inertia_apis`.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/config/migration_debt.php` | Replace stale maxima with a monotonic per-slice ratchet |
| `/Users/hunt2412/hieupvdev/project/swinx/config/migration_debt_paths.php` | Remove stale approvals; forbid resurrection |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Support/MigrationDebt/MigrationDebtInventory.php` | Fix frontend/comment/static-asset/runtime classification gaps |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Support/MigrationDebt/MigrationDebtGuard.php` | Enforce current count/path equality and exact-zero terminal mode |
| `/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Architecture/MigrationDebtInventoryTest.php` | Add scanner/ratchet fixtures |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Delivery/Http/Api/Lecturer` | Remove 19 `Lecture` FQCN/PHPDoc dependencies via model-free teaching references |

## Interface Checklist

- `MigrationDebtInventory::inventory()` keeps guarded file counts and reports shadow
  occurrence/coverage counts separately.
- `MigrationDebtGuard` rejects count growth, unknown paths, stale approvals, and false zero.
- Immutable rule/root tests fail if a config key or required runtime surface is removed.
- `owned_shared_models` is frozen; additions require accepted ownership evidence and an
  independent test that non-owner runtime code does not import/query the model.

## Dependency Map

`inventory truth + work-package ledger → every later phase`

## Implementation Steps

1. Export current JSON inventory and route/schedule snapshots as the starting ledger.
2. Add scanner fixtures for comments, static assets, wrapper calls,
   `ApiResponse::compatible()`, `Validator::make`, `request()->validate`,
   `new JsonResponse`, `HttpResponseException`, and removed Inertia APIs.
3. Prune stale path approvals; make configured path sets equal live sets.
4. Replace the 19 known `Lecture` FQCN/PHPDoc findings in Lecturer Student/Timetable
   controllers with model-free teaching reference types; verify the exact manifest.
5. Lower baselines to actual counts after each accepted slice; never increase them.
6. Generate the exact-one-owner work-package ledger with before/delta/after values.
7. Reconcile all live Academic compatibility consumers and assign each to a package.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| New or resurrected debt path | Guard fails |
| Deleted approved path remains configured | Guard fails |
| Comment/static asset literal | Shadow and guarded classifications are explicit |
| Rule key or runtime root removed | Guard fixture fails |
| Ownership exemption added without proof | Independent architecture test fails |
| Inventory check | Green at a value no greater than 568 shared imports |

## Success Criteria

- [x] Live inventory is reproducible.
- [~] `--check` green without baseline growth — **regressed, recovery moved to phase 1b**.
  On 2026-08-16 the guard failed with `shared_model_imports` at 398 against baseline 289
  and `legacy_filter_stacks` at 24 against 22.
- [x] Route and schedule baselines exist for later compatibility comparisons.
- [x] All current findings have exactly one owner package and measurable debt delta.
- [x] Production schema cleanup remains disabled until phase 11.

## Risks and Security

- Scanner changes can manufacture a false zero. New/broader metrics stay shadow-only
  until their current population is remediated in an owner package.
