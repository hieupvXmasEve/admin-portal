# 07 — Retire migration-only tools and legacy types

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Remove one-time Finance backfill, reconciliation, link-guessing, and schema-transition commands/actions after canonical data and source linkage are proven complete. Current code supports only the post-canonical-backfill data truth; restoring an older snapshot requires the pre-cleanup release.

Retire obsolete active charge-type identifiers such as `course_fee`, while preserving historical credit identifiers where the database still needs them. Move entitlement vocabulary out of the active charge model so new code cannot create legacy negative-charge credits.

## Acceptance criteria

- [x] One-time billing-account, tuition, EGC, BHYT, retake/resit, defer, voucher, scholarship, exemption, and discount backfill families have no runtime need and are removed.
- [x] Amount/time-based DNG link guessing and obsolete academic reconciliation/repoint tooling are removed.
- [x] Migration files that have already run remain unchanged and available as historical schema evidence.
- [x] The supported restore contract explicitly starts from a database backup taken after canonical backfill; no current command claims to upgrade older business data.
- [x] `course_fee` cannot be created as an active charge and is removed from the active registry/database constraint through a forward-only migration after confirming zero rows.
- [x] Credit-entitlement identifiers are owned by the entitlement domain rather than advertised as active charge creation types.
- [x] Historical void credit rows and legitimate ledger evidence remain readable and pass money-sign/invariant checks.
- [x] Command inventory and automated tests prove no deleted command/action has a production caller.

## Verification

- `./scripts/dev.sh artisan test --compact` on the retirement architecture, enum parity, money-sign, negative-charge guard, and obligation-type suites: 39 passed, 222 assertions.
- `2026_07_14_180000_retire_course_fee_charge_type` rolled back and reapplied locally. Before and after migration, `course_fee` rows were 0; the post-migration enum excludes `course_fee`; 235 void historical credit rows remain readable.
- `RetiredFinanceMigrationToolsTest` checks command registration, autoloadability, and scans production PHP callers. The only similarly named command retained is `academic:backfill-failure-reason`, an active academic workflow outside this retired-tool inventory.
- `finance:audit-invariants --sample` retains the known baseline INV-13 sample `1067` and INV-18 sample `1443`; INV-19 has no offenders.
- The full wrapper test command exited `255` without output, and container-wide `vue-tsc --noEmit` exhausted its 2 GiB Node heap (`134`). These environment-capacity gaps do not affect the passing focused PHP proof above.

## Blocked by

- [Issue 01 — Close known Finance data exceptions](01-close-known-data-exceptions.md)
- [Issue 06 — Remove legacy Finance source pointers](06-remove-legacy-source-pointers.md)
