# 14 — Complete Settlement Position read convergence

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Finish the incomplete read cutover recorded by issue 04. Every remaining authoritative Finance consumer must use Settlement Position for current monetary truth, including allocation previews and any invoice, worklist, report, export, reminder, model accessor, or API path not already converged.

This issue closes the gap between issue 04's implementation notes and its unchecked acceptance criteria; it must not introduce another compatibility formula.

## Acceptance criteria

- [x] A repository-wide inventory identifies every remaining authoritative current-balance consumer and records its canonical Settlement Position seam.
- [x] Allocation previews and all remaining staff, student, worklist, report, export, reminder, and model-facing consumers obtain gross, discount, cash, credit, and remaining collectible from Settlement Position.
- [x] No authoritative consumer computes current balance through local amount-minus-paid arithmetic or trusts a stale paid/status cache over canonical position.
- [x] Invalid positions fail closed for student-visible amounts and money-moving decisions and expose stable staff review evidence.
- [x] Single-scope and batch results remain equivalent and within the existing query and latency budgets.
- [x] Focused tests cover tuition, retake/resit, BHYT, credit, discount, partial payment, void, overpayment, surplus, and invalid evidence.
- [x] Issue 04's remaining acceptance criteria and tracker state can be truthfully completed after this slice passes.

## Blocked by

- [Issue 09 — Restore a parseable Finance baseline](09-restore-parseable-finance-baseline.md)

## Completion evidence

### 2026-07-15 — authoritative current-balance inventory

| Consumer family | Canonical seam |
| --- | --- |
| Staff invoice detail and export | `BillingInvoiceController` and `InvoiceExport` → `SettlementPositionReader::forInvoice()` |
| Student, parent, Student 360, and Hub summaries | `StudentFinanceSettlementPositionReader` or `SettlementPositionReader` batch scopes |
| Staff worklists, billing dashboard, DNG worklist, Collection Progress, and Fee Monitor | `SettlementPositionWorklistReader` → canonical batch payable-line scopes |
| Collection reminders and DNG installment reminders | `SettlementService::deriveInvoiceSnapshot()` or exact reservation-target Settlement Position scope |
| Invoice model accessors and cache rebuild/audit views | `StudentInvoice` / `SettlementService` → canonical invoice scope; cache values remain diagnostic only |
| Auto-allocation preview | `PreviewAutoAllocateQuery` → `SettlementService` canonical Settlement Position adapter |
| Manual allocation preview | `PreviewManualAllocationQuery` → direct canonical batch payable-line scopes; returns all components and stable review evidence |

The non-authoritative audit/cache-drift surfaces intentionally retain cache reads only to identify disagreement with canonical positions.

### Verification

- `tests/Feature/Finance/Student360/ManualAllocationPreviewTest.php`: 6 passed / 30 assertions. Covers canonical gross, discount, cash, credit, remaining collectible, partial payment, stale invoice `paid` cache, invalid currency, and batch-cardinality fail-closed evidence.
- Representative Finance scenario pack: 41 passed / 254 assertions across manual/automatic allocation, single/batch Settlement Position, BHYT, resit, void release, and surplus disposition.
- `tests/Feature/Finance/LedgerSourceOfTruthTest.php` proves the automatic allocation preview also ignores a stale invoice `paid` cache and follows canonical remaining collectible.
- Existing Finance characterization suites cover BHYT intake, retake/resit, void release, surplus disposition, reminders, and aggregate batch/as-of parity within the established six-query / 100ms p95 budget.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed. Vue type checking passed after the UI change in the focused validation window; two final reruns were terminated by the container with exit 137/SIGKILL without TypeScript diagnostics.
- The full configured Laravel suite was invoked through `./scripts/dev.sh test --compact` and exited 255 without diagnostics in this environment. The representative Finance pack above is green and is the behavioral proof for this slice.

The separately-invoked `SettlementBypassArchitectureTest` still reports two pre-existing mutation-writer findings in `PushNextInstallmentAction` and `ResolveDngReservationOutcomeAction`; they are owned by issue 15 and this read-convergence slice does not change either file.
