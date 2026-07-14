# 04 — Converge settlement reads on Settlement Position

Status: ready-for-agent

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Move every authoritative Finance balance consumer to the canonical Settlement Position contract. Staff invoices, Student 360, due/reminder worklists, student summaries, allocation previews, reports, and model-facing presentation accessors must receive gross, discount, cash, credit, and remaining collectible without recomputing them locally.

Once all consumers have moved, remove the duplicate settlement snapshot, line-outstanding, charge-paid, and charge-balance formulas that can disagree with canonical credit-aware settlement.

## Acceptance criteria

- [ ] All authoritative current-balance consumers obtain complete settlement components from Settlement Position.
- [x] Applied credit reduces remaining collectible and is displayed separately from cash paid.
- [ ] No model accessor, query, resource, controller, export, reminder, or worklist calculates authoritative balance as local `amount - paid - discount` arithmetic.
- [ ] Invalid Settlement Positions fail closed for money-moving or student-facing amounts and surface stable staff review evidence.
- [x] Single-scope and batch reads remain mathematically equivalent and meet existing query/latency budgets.
- [x] Student API response shape and the matching student portal behavior are inspected and updated together if the contract changes.
- [x] Characterization and integration tests reconcile representative tuition, retake/resit, BHYT, credit, discount, partial-payment, void, and surplus cases.

## Blocked by

- None — can start immediately.

## Implementation notes

- 2026-07-14: Began the cutover for invoice, charge, Student 360, Student 360/Hub, fee-summary, and student portal reads. Canonical components now include gross, discount, cash, credit, and remaining, with unavailable student amounts represented as `null` plus Settlement Position review metadata.
- 2026-07-14: Converged the generated tuition checklist and Hub summary as well; fixture coverage for the Settlement Worklist now materializes Finance Obligations and confirms invalid void-line data is excluded rather than silently treated as collectible.
- 2026-07-14: Removed the final current-balance fallback arithmetic found in staff invoice lookup and Fee Monitor. Also repaired the allocation transaction closure so DNG-held-target handling receives its explicit policy flag, and updated allocation/worklist characterization fixtures to use canonical obligation-backed lines.
- 2026-07-14: Confirmed fee-type priority is the approved auto-allocation policy (not an oldest-invoice override), aligned the stale worklist characterization accordingly, and added fail-closed coverage for active legacy negative lines.
- 2026-07-14: Representative proof now covers BHYT intake, retake/resit actions, canonical cash/discount/credit/partial positions, void-line exclusion, and surplus disposition handling.
- 2026-07-14: Settlement Position performance baseline and aggregate parity tests pass: batch query count matches a single scope and remains within the six-query / 100ms p95 budget.
- 2026-07-14: Replaced the remaining local ledger aggregation in `ObligationLedgerSettlementReader` and installment-reminder balance arithmetic with Settlement Position. Invalid overpayment or reversed-discount evidence now fails closed with stable `settlement_position.*` review codes; the Academic retake gate and DNG installment reminder characterization tests pass.
- 2026-07-14: Removed stale DNG worklist and Collection Progress report bypass entries after confirming both use canonical batch Settlement Position reads. DNG worklist tests now materialize an obligation and payable line; unmaterialized HL sources remain visible only as review exceptions. Collection Progress excludes voided payable lines from current collectible totals.
- 2026-07-14: Student and parent due-item DNG reminders now derive `balance_formatted` from exact reservation-target Settlement Position scopes. Requests without a target or with invalid positions are not emailed and emit stable review codes; partial cash is characterized as a lower reminder balance.
- The issue remains `ready-for-agent`: allocation preview and the remaining read consumers still need final convergence proof. The existing architecture test is blocked by the unrelated pre-existing writer in `CloseKnownLegacyDataExceptionsAction` (issue 05).
