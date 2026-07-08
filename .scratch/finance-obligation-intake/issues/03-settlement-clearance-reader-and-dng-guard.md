# 03 — Settlement/clearance reader + DNG missing-obligation guard

**Status:** ready-for-agent
**Depends on:** 01 (debit intake spine)
**PRD:** ../PRD.md · **ADR:** docs/adr/0026-…

## Goal

Settlement is derived from the ledger and exposed as a synchronous clearance contract; DNG stops minting charges and surfaces missing obligations instead.

## Seam

`ObligationSettlementReader::getSettlement(...)` / `isSettled(...)` (new read/clearance door) + existing DNG batch action.

## Scope

- `ObligationSettlementReader` in `app/Shared/Contracts/Finance`, keyed by `source_system + source_kind + source_ref + obligation_type`. Derive settlement (`unpaid/partially_paid/paid/overpaid/settled_by_discount_or_credit`, `payable/paid/discount/outstanding` amounts) from `invoice_lines`, `payment_applications`, `discount_allocations`, `payments`. No settlement flag stored anywhere.
- Absence of an obligation → `missing_finance_obligation` (never "cleared"). A genuine no-fee case is an explicit Finance decision (`waived`/`not_required`/`zero_amount`).
- DNG: remove auto-create-on-push (`ensureRetakeChargesExist`/`ensureExamResitChargesExist`). DNG creates payment requests only for existing payables. A chargeable source without an obligation → block `missing_finance_obligation` (or an explicit repair action), never silent create.

## Acceptance

- Reader returns settlement matching the ledger across unpaid/partial/paid/discount cases; disagreeing cache never wins.
- Query for a source with no obligation → `missing_finance_obligation`, not cleared.
- DNG batch over a chargeable-but-obligation-less source blocks; DNG never creates a charge.
- DNG over an existing payable pushes as before.

## Testing

Feature tests: seed ledger rows → assert reader output; DNG batch behaviour (block vs push). Prior art: `DngLedgerReconciliationInvariantTest`, `BatchDngInstallmentAwareTest`.

## Out of scope

Academic hard-gate call sites consuming the reader (can follow once the reader exists), backfill (04), arch enforcement (05).
