# 06 — Remove legacy Finance source pointers

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Replace runtime dependencies on source-side charge pointers with canonical obligation identity and Finance-owned settlement linkage. DNG reconciliation, defer handling, billing exceptions, student fee summaries, and retake/resit lifecycle flows must resolve through exact canonical identifiers.

After every runtime reader and writer is migrated and the inventory proves there are no unresolved links, remove the obsolete source-side `finance_charge_id` columns and indexes through forward-only migrations. Never infer a link from amount, fee type, or timestamp proximity.

## Acceptance criteria

- [x] Every affected DNG, defer, billing-exception, summary, retake, and resit flow resolves Finance state through exact canonical obligation/source identity.
- [x] Source cancellation and handoff workflows preserve their domain state without owning Finance ledger pointers.
- [x] Inventory reports zero active charge without obligation and zero source workflow requiring an ambiguous link.
- [x] Exact historical DNG/payment/source evidence remains queryable after pointer removal.
- [x] Forward-only migrations remove the obsolete columns, constraints, and indexes without editing already-run migrations.
- [x] No runtime code or test fixture writes or reads the removed pointers.
- [x] Cross-module boundary and lifecycle integration tests pass for retake, resit, defer, and DNG reconciliation.

## Verification

- Local `asia` inventory after lifecycle closure and migrations: `safe_to_cutover=true`, `213/213` exact DNG links, zero non-exact links. Local-only closure preserved provider evidence for deferred/dropout DNG requests `160, 164, 174, 176, 179, 181, 182, 183, 186, 233`; no provider cancellation was sent.
- Applied five forward-only retire migrations, removing the DNG, EGC block, EGC retake target, voucher, course-retake, and exam-resit source charge pointers. Verified representative DNG pivots: request `234 -> 1170`, `250 -> 1160 + 1161`, `252 -> 1168 + 1169`, `262 -> 1184`.
- `finance:audit-invariants --sample`: INV-19 is zero; the only reported baseline rows were INV-13 `#1067` and INV-18 `#1443`.
- `./scripts/dev.sh test` passed. The focused DNG/EGC/retake/resit/cancellation suite also exited successfully; the targeted pointer-retirement regression set passed `41` tests / `149` assertions, including `CreateBatchDngFromChargesActionTest`, `CancellationOperationTest`, and `CreateRetakeCourseRegistrationActionTest`.
- `./scripts/dev.sh npm run type-check` passed. Pint and `git diff --check` passed.

## Blocked by

- [Issue 01 — Close known Finance data exceptions](01-close-known-data-exceptions.md)
- [Issue 03 — Converge DNG creation on guarded reservations](03-converge-dng-creation-on-guarded-reservations.md)
- [Issue 05 — Route settlement mutations through the guard](05-route-settlement-mutations-through-guard.md)
