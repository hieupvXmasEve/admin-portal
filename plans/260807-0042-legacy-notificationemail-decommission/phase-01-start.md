---
phase: 1
title: "Delete dead services"
status: completed
priority: P1
effort: "0.5d"
dependencies: []
---

# Phase 1: Delete dead services

## Overview

Delete the 6 services referenced only by `config/migration_debt_paths.php`'s frozen list (no live callers). Zero product risk — pure debt cleanup. No sign-off needed.

<!-- Updated: Validation Session 1 - ship together with Phase 2 as a single PR (both zero-risk, no soak needed) -->

## Requirements

- Functional: services removed, no remaining references, debt inventory reflects removal.
- Non-functional: `MigrationDebtInventoryTest` (and any placement arch tests) stay green.

## Related Code Files

- Delete: `app/**/Services/BillingCycleService.php` (confirm exact module path before deleting)
- Delete: `app/**/Services/HoldsManagementService.php`
- Delete: `app/**/Services/ScheduledNotificationService.php`
- Delete: `app/**/Services/StudentCodeGenerationService.php`
- Delete: `app/**/Services/UserExcelExportService.php`
- Delete: `app/**/Services/UserExcelImportService.php`
- Modify: `config/migration_debt_paths.php` (remove the 6 entries from `frozen_services`)

## Implementation Steps

1. `grep -rn "BillingCycleService\|HoldsManagementService\|ScheduledNotificationService\|StudentCodeGenerationService\|UserExcelExportService\|UserExcelImportService" app tests routes` to re-confirm zero live callers (only `migration_debt_paths.php` + the service file itself should match).
2. Delete each service file and its dedicated test file (if any) — one commit per service or one PR for all 6 (user said "mỗi cái 1 PR" for tables; services are lower risk, batch is fine unless reviewer wants split).
3. Remove the 6 entries from `config/migration_debt_paths.php` `frozen_services`.
4. Run `./scripts/dev.sh artisan test --filter=MigrationDebtInventoryTest` (or repo's actual test path) — must pass.
5. Run full test suite for the touched modules; confirm no new failures vs baseline.

## Success Criteria

- [ ] 6 service files deleted, zero remaining references in `app/`, `routes/`, `tests/`
- [ ] `config/migration_debt_paths.php` no longer lists the 6 services
- [ ] `MigrationDebtInventoryTest` passes
- [ ] No new test failures vs pre-change baseline

## Risk Assessment

Low, but the rationale must be stated correctly. [Red-team F6] `config/migration_debt.php` defines `frozen_services` as **live runtime paths still awaiting cutover**, not "known dead" — the list also contains genuinely live services (`StudentService`, `ScholarshipService`, `TuitionPlanService`). The 6 services targeted here are safe only because step 1's grep independently proves zero callers, NOT because they're on the frozen list. Do not generalize this phase's method to other `frozen_services` entries without the same per-service grep proof.

## Execution Evidence (2026-08-07)

- Grep confirmed zero external callers for all 6 services before deletion.
- All 6 deleted, no dedicated test files existed for them.
- `frozen_services` reduced 66 → 60 (exactly the 6 targets removed, verified by code-reviewer diff).
- `MigrationDebtInventoryTest`: same 2 pre-existing failures with/without this change (baseline-drift on unrelated debt rules: `shared_model_imports`, `direct_json_responses`, `inline_request_validation`, `literal_frontend_urls`) — zero `frozen_services`-related errors, zero regression.
- Shipped combined with Phase 2 in one PR per Validation Session 1.
- **Deploy note:** run `artisan optimize:clear` after deploy — `bootstrap/cache/services.php` caches the provider list and must not resolve a stale reference (see Phase 2 evidence, same deploy window).
