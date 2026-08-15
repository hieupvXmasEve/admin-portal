# Phase 1 — Grants-only reader

## Context

`EloquentGuardianAccessGrantReader` merges legacy `parent_student` into 4
methods while login/middleware methods read grants only → parent linked one way
behaves differently per feature. Parity audit (Phase 0) = 0 divergence, so
legacy branches are dead code.

## Files

- `app/Modules/Identity/Support/EloquentGuardianAccessGrantReader.php` — edit
- `tests/Feature/Identity/GuardianAccessGrantTest.php` — extend
- `tests/Feature/Architecture/GuardianOwnershipBoundaryArchTest.php` — extend
  (parity guard: forbid `parent_student` string in Identity module sources)

## Steps

1. `primaryAccountForStudent`: rewrite → grants JOIN
   `student_guardian_relationships` ON `guardian_relationship_id`, filter
   `is_primary = 1`, active grant, join users. Drop `parent_student` query.
2. `accountsForStudent`: delete `$legacyAccounts` branch + concat/unique
   scaffolding; grants query stays (keep email-filled filter, user
   type/status filters).
3. `studentIdsWithAccounts`: delete `$legacyStudentIds` branch + merge.
4. `hasRelationshipForOtherStudent`: delete `parent_student` fallback exists().
5. Arch test: assert no Identity module file (except migrations) contains
   `parent_student`.
6. Feature tests: primary-account resolution via relationship `is_primary`;
   accountsForStudent returns grant-backed accounts only (student with
   pivot-only row—factory-made—must NOT appear).

## Validation

- `./scripts/dev.sh artisan test tests/Feature/Identity/ tests/Feature/Architecture/GuardianOwnershipBoundaryArchTest.php`
- Grep: `grep -rn parent_student app/Modules/Identity` → 0 hits.
- Blast radius: Finance reminder actions consume `accountsForStudent` /
  `studentIdsWithAccounts` → run
  `tests/Feature/Finance/` reminder-related tests
  (`SendParentPaymentRemindersAction`, `SendDueItemParentRemindersAction`
  coverage).

## Risk / rollback

- Risk: prod rows drifting from audit snapshot between now and deploy →
  re-run parity SQL right before deploy; if non-zero, run one-time grant
  backfill (grants insert from missing pairs) first — script in phase file
  only if needed.
- Rollback: single commit revert; no schema change.
