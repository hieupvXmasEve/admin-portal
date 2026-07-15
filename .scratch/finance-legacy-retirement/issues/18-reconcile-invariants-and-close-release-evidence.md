# 18 — Reconcile invariants and close release evidence

Status: ready-for-human

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Run the final Finance retirement gate against the accepted canonical data truth. Classify and resolve every remaining invariant violation without clamping or silently rewriting money, then make the tracker and verification evidence accurately describe the code and data that can be released.

Use student codes in every exception report so each invoice, charge, installment, or DNG can be checked operationally. Production-derived records are verification evidence, not fixtures to embed in automated tests.

## Acceptance criteria

- [ ] The full Finance invariant audit returns zero unexplained violations, including active lines on void charges, pending installments on void charges, terminal DNG active slots, invalid Settlement Positions, orphan targets, and settlement-version inconsistencies. Current local audit has two explicit violations awaiting approved data correction: `INV-13` charge `#1067` and `INV-18` invoice `#1443`.
- [ ] Local exceptions for charge `#1067` / student `AUH120849` and invoice `#1443` / student `AUH111841` await an approved evidence-based disposition. DNG `#160` / student `AUH16667` and DNG `#233` / student `AUH111841` are verified `cancelled` with `active_slot_key = null`.
- [x] Every current exception output includes the student code, campus, semester, entity type and ID, raw ledger monetary components, lifecycle state, and stable reason code.
- [ ] Before/after gross, discount, cash, credit, remaining collectible, invoice totals, payment totals, and DNG evidence reconcile with no unexplained difference. This must be captured only after the two proposed corrections are reviewed and approved.
- [ ] Focused Finance, architecture, API contract, migration, concurrency, static-analysis, formatting, and affected student-portal checks pass from a clean process. The new audit regression test and Pint pass; the full wrapper exits `255` without output and type-check is terminated after starting `vue-tsc` in this environment.
- [x] Tracker statuses and verification notes for issues 04, 08, and 09–17 match current reproducible evidence: 04 and 09–16 are `completed`; 08 and 17 remain `ready-for-human` because their release gates are not reproducibly green.
- [x] Production verification steps are documented in the existing issue comments without creating a second source of architectural truth.
- [x] No automated cleanup mutates production data unless the exact affected student and monetary correction have been reviewed under the approved Finance safety rules.

## Verification

- `./scripts/dev.sh artisan finance:audit-invariants --sample` is read-only and currently reports only `INV-13` charge `#1067` (`AUH120849`, `HN`, `SUMMER2026`) and `INV-18` invoice `#1443` (`AUH111841`, `HN`, `SUMMER2026`). The command now prints entity, lifecycle, raw gross/discount/cash/credit evidence, unavailable remaining collectible for the invalid position, and a stable reason code for both exceptions.
- The same read-only graph check confirms DNG `#160` (`AUH16667`) and DNG `#233` (`AUH111841`) are `cancelled` with released active slots; neither has a remaining DNG target or slot violation.
- `./scripts/dev.sh artisan test --compact tests/Feature/Finance/Integrity/AuditFinanceInvariantsParityTest.php` passed: `5` tests, `15` assertions. `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed. `./scripts/portal-status.sh` reports clean student and lecturer nested repositories; this slice changes no portal API contract or portal code.
- The repository-level `./scripts/dev.sh test --compact` again exited `255` without output. `./scripts/dev.sh npm run type-check` started `vue-tsc --noEmit` but was terminated before returning diagnostics, so neither can be recorded as passing release evidence.

## Comments

- 2026-07-15: No data write was attempted. Human Finance approval is required before any correction: confirm the lifecycle-only disposition for void charge `#1067` / pending installment `#618`, and confirm the exact void/reconciliation disposition for active invoice line `#1167` on void charge `#1149` in invoice `#1443`. Record gross, discount, cash, credit, remaining collectible, invoice/payment totals, and DNG evidence immediately before and after each approved write; then rerun the audit and release gates from a clean process.

## Blocked by

- [Issue 09 — Restore a parseable Finance baseline](09-restore-parseable-finance-baseline.md)
- [Issue 10 — Make Student Finance API fail closed](10-make-student-finance-api-fail-closed.md)
- [Issue 11 — Push the next installment atomically](11-push-next-installment-atomically.md)
- [Issue 12 — Enforce the exact DNG reservation lifecycle](12-enforce-exact-dng-reservation-lifecycle.md)
- [Issue 13 — Recover DNG target drift without stranding installments](13-recover-dng-target-drift.md)
- [Issue 14 — Complete Settlement Position read convergence](14-complete-settlement-position-read-convergence.md)
- [Issue 15 — Close Settlement Mutation Guard blind spots](15-close-settlement-mutation-guard-blind-spots.md)
- [Issue 16 — Restore Academic–Finance module contracts](16-restore-academic-finance-contracts.md)
- [Issue 17 — Make the EGC migration restart-safe](17-make-egc-migration-restart-safe.md)
