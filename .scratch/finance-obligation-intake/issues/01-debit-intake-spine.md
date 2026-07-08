# 01 — Debit intake spine

**Status:** ready-for-agent
**Depends on:** —
**PRD:** ../PRD.md · **ADR:** docs/adr/0026-…

## Goal

Stand up the Finance-owned debit intake spine: any source can submit pricing facts and Finance materializes a priced payable, with no cross-context coupling.

## Seam

`FinanceIntakeContract::request(intake)` (router) → `requestDebit(intake)` (debit branch). This is the highest write seam; tests drive it directly.

## Scope

- `FinanceObligation` aggregate (debit-only). `lifecycle_status ∈ {requested, accepted, rejected, cancelled, voided, superseded}`. Store source triple (`source_system`, `source_kind`, `source_ref`), `obligation_type`, priced `amount`, `currency`, `pricing_rule_version`, `pricing_snapshot`, timestamps. No settlement columns.
- Idempotency: `unique(source_system, source_kind, source_ref, obligation_type)`.
- Finance Intake Contract in `app/Shared/Contracts/Finance`. `request()` routes by `financial_effect`; `Debit` → `requestDebit`; `Credit`/`Discount` → throw `UnsupportedFinancialEffectYet`. Payload carries **facts only** (no amount/currency/pricing version).
- Minimal pricing catalog for `retake_fee` + `exam_resit_fee` (decide table vs config in this issue). Finance prices from payload facts + catalog with **zero** Academic reads. Stamp priced amount + rule version at acceptance.
- On accept (fixed/deterministic → auto-accept): create `FinanceCharge` + `InvoiceLine`. Charge keys on `finance_obligation_id`; charge stores no external source.

## Acceptance

- Same intake submitted twice → one obligation, one charge (idempotent by the quad).
- Debit intake → obligation `accepted` + `FinanceCharge` + `InvoiceLine`, amount = catalog price, `pricing_rule_version` stamped.
- `request()` with `financial_effect = credit|discount` → `UnsupportedFinancialEffectYet`.
- No Finance table stores an Academic class name/id; pricing path reads no Academic table.

## Testing

Feature tests through `request()`/`requestDebit()`. Assert observable outcomes (obligation/charge/line, price, idempotency, unsupported-effect throw). Prior art: `ExamResitChargeActionTest`, `ChargeDiscountCorrectnessTest`.

## Out of scope

Academic transition wiring (02), settlement reader/DNG (03), backfill (04), arch enforcement + model relocation (05), credit/discount branches.
