# 17 — Make the EGC migration restart-safe

Status: ready-for-human

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Make the existing EGC schema transition safe when deployment is retried after partial DDL completion. The migration must reach the same final schema whether it starts from the pre-migration schema, the completed schema, or an identified partially applied state, without deleting legitimate historical data.

## Acceptance criteria

- [x] Each destructive or additive schema step is independently guarded by the actual current schema state rather than one coarse migration-level condition.
- [x] Retrying after every supported partial-completion checkpoint reaches the intended final schema without duplicate-column, missing-column, or missing-index errors.
- [x] The migration preserves legitimate EGC blocks, canonical obligations, charges, invoice lines, and historical evidence.
- [x] Rollback behavior is explicitly defined and intentionally forward-only for this production cutover.
- [x] Migration tests exercise a clean pre-migration database, an already-completed database, and representative partial DDL states.
- [x] The resulting schema matches the canonical Finance architecture and contains no restored source-side Finance pointer.
- [x] Deployment verification can run non-interactively through the project wrapper and reports a clear failure for an unknown schema state.

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Egc/RetireEgcBlockFinanceChargePointerMigrationTest.php` passed: `5` tests, `20` assertions. It covers the clean pre-cutover schema, already-completed schema, post-foreign-key and post-index partial checkpoints, and a missing `egc_blocks` table that fails with an explicit unsupported-schema error.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed.
- `git diff --check` passed for the migration and its regression test; standards/spec review found no actionable findings.
- `./scripts/dev.sh test` exited `255` immediately after Pint without diagnostics from the wrapper. This repository-level gate needs human/environment triage before the issue can truthfully move to `completed`.

## Blocked by

- [Issue 09 — Restore a parseable Finance baseline](09-restore-parseable-finance-baseline.md)
