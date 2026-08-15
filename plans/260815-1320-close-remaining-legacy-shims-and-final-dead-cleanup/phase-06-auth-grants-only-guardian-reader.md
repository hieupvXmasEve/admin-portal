---
phase: 6
title: "Auth: grants-only guardian reader"
status: completed
priority: P1
effort: "0.5d"
dependencies: []
---

# Phase 6: Auth — grants-only guardian reader

> Merged from `plans/260815-0942-auth-legacy-single-source/` phase 1 (audit phase 0 done 2026-08-15: `parent_student` 194 rows = `guardian_access_grants` 194 active, legacy-only pairs 0, grant-only 0, orphans 0 → NO backfill needed, dual-read = dead weight).

## Overview

`EloquentGuardianAccessGrantReader` merges legacy `parent_student` pivot into 4 methods while login/middleware methods read grants only → parent linked one way behaves differently per feature. Parity audit = 0 divergence, so legacy branches are dead code. Kill the dual-read.

## Related Code Files

- Modify: `app/Modules/Identity/Support/EloquentGuardianAccessGrantReader.php`
- Extend: `tests/Feature/Identity/GuardianAccessGrantTest.php`
- Extend: `tests/Feature/Architecture/GuardianOwnershipBoundaryArchTest.php` (parity guard: forbid `parent_student` string in Identity module sources)

## Implementation Steps

1. `primaryAccountForStudent`: rewrite → grants JOIN `student_guardian_relationships` ON `guardian_relationship_id`, filter `is_primary = 1`, active grant, join users. Drop `parent_student` query.
2. `accountsForStudent`: delete `$legacyAccounts` branch + concat/unique scaffolding; grants query stays (keep email-filled filter, user type/status filters).
3. `studentIdsWithAccounts`: delete `$legacyStudentIds` branch + merge.
4. `hasRelationshipForOtherStudent`: delete `parent_student` fallback exists().
5. Arch test: assert no Identity module file (except migrations) contains `parent_student`.
6. Feature tests: primary-account resolution via relationship `is_primary`; accountsForStudent returns grant-backed accounts only (student with pivot-only row — factory-made — must NOT appear).

## Validation

- `./scripts/dev.sh artisan test tests/Feature/Identity/ tests/Feature/Architecture/GuardianOwnershipBoundaryArchTest.php`
- Grep: `grep -rn parent_student app/Modules/Identity` → 0 hits.
- Blast radius: Finance reminder actions consume `accountsForStudent` / `studentIdsWithAccounts` → run `tests/Feature/Finance/` reminder-related tests (`SendParentPaymentRemindersAction`, `SendDueItemParentRemindersAction` coverage).

## Success Criteria

- [x] Zero `parent_student` reads in Identity module (arch-test enforced)
- [x] Identity + Finance reminder tests green

## Risk / Rollback

- Risk: prod rows drifting from audit snapshot between now and deploy → re-run parity SQL right before deploy; if non-zero, run one-time grant backfill (grants insert from missing pairs) first.
- Rollback: single commit revert; no schema change.
