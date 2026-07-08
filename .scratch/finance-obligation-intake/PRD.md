# PRD — Finance Obligation Intake & Academic/Finance boundary (slice-1: retake/resit debit cutover)

**Status:** ready-for-agent
**ADR:** docs/adr/0026-academic-finance-boundary-and-retake-resit-candidate-cutover.md
**Glossary:** CONTEXT.md (FinanceObligation, Obligation Source Reference, Finance Intake Contract, Settlement state (derived), Financial Clearance, FinanceCreditEntitlement, FinanceDiscountEntitlement)

> This PRD is an umbrella. Implementation is split into the issues under `issues/` — do **not** action the whole PRD as one ticket. See the issue split at the bottom.

---

## Problem Statement

Today Academic and Finance cannot change independently. A retake registration or exam-resit attempt stores a direct `finance_charge_id` pointing at a Finance row; `finance_charges` stores the Academic class name and row id (`source_type = CourseRetakeRegistration::class`, `source_id`); Finance pricing reads an Academic column (`exam_resit_attempts.fee_amount`); and Academic actions call Finance's charge-void and DNG-cancel directly. Because the two contexts are wired straight into each other's tables, a business change on one side forces a schema or code change on the other, and money behaviour is hard to audit — settlement truth, charge existence, and Academic fee-status can disagree with no single source of truth. Staff and developers cannot reason about "has this obligation been paid?" without joining across the boundary.

## Solution

Academic and Finance become genuinely decoupled bounded contexts inside the same monolith. The two sides exchange only a neutral **Obligation Source Reference** (`source_system` + `source_kind` + `source_ref`); neither stores the other's identity. Finance gains a single, source-agnostic **Finance Intake Contract**: any source (Academic today; admission, bookstore, insurance later) submits pricing facts, and Finance prices from its own catalog and materializes the payable. Payable receivables live in a Finance-owned **FinanceObligation** aggregate whose `lifecycle_status` records only the intake decision; how much has been paid is always **derived** from the settlement ledger, never stored as status. Hard academic gates (exam sitting, registration) ask Finance for **Financial Clearance** synchronously rather than trusting a local projection.

The first slice cuts over exactly the two fixed debit fees — **Fixed Retake Fee** and **Fixed Resit Fee** — completely (no parallel write path), leaving credit/discount aggregates and all other obligation types for later slices.

## User Stories

1. As Academic Affairs staff, I want a retake registration's fee to be created the moment the registration becomes chargeable, so that I never have to remember to trigger billing separately.
2. As Academic Affairs staff, I want an exam-resit attempt's fee created automatically when the attempt is approved for charging, so that HQ no longer manually keys the amount.
3. As Finance staff, I want every retake/resit fee priced from a Finance-owned catalog, so that fee amounts are consistent and auditable rather than copied from an Academic column or typed by a caller.
4. As Finance staff, I want each obligation to record only its decision lifecycle (`requested/accepted/rejected/cancelled/voided/superseded`), so that "paid or not" is never a stale duplicated flag.
5. As Finance staff, I want settlement state derived from `invoice_lines`, `payment_applications`, `discount_allocations`, and `payments`, so that the ledger is the single source of truth.
6. As an exam invigilator/registrar, I want the "may this student sit the resit?" check to read Finance's authoritative settlement synchronously, so that a student who just paid is not wrongly blocked and an unpaid student is not wrongly admitted.
7. As a registrar, I want a chargeable source with no matching obligation to block as `missing_finance_obligation`, so that "no obligation yet" is never mistaken for "cleared".
8. As Finance staff, I want a genuine no-fee case to be an explicit decision (`waived`/`not_required`/`zero_amount`), so that free study is recorded, not inferred from absence.
9. As Academic Affairs staff, I want the student's fee badge/queue view to keep working via a local `hq_fee_status` projection, so that lists stay fast even though the authoritative check is elsewhere.
10. As a developer, I want Academic and Finance to correlate only through `source_system`/`source_kind`/`source_ref`, so that renaming or restructuring an Academic table never breaks Finance.
11. As a developer, I want Finance to never store an Academic class name or table id, so that Finance schema is independent of Academic internals.
12. As a developer, I want Academic business tables to never store `finance_charge_id`/`finance_obligation_id`, so that Academic schema is independent of Finance internals.
13. As Finance staff, I want the intake to be idempotent per `source_system + source_kind + source_ref + obligation_type`, so that a retried or duplicated source signal cannot create two charges.
14. As Finance staff, I want DNG to only create payment requests for charges that already exist, so that DNG stops silently minting charges as a side effect of a push.
15. As Finance staff, I want a DNG batch that meets a chargeable source without an obligation to block or offer an explicit repair, so that missing obligations are surfaced, not papered over.
16. As Finance staff, I want existing active retake/resit charges migrated into `accepted` obligations linked to the existing charge, so that historical data fits the new model without re-billing.
17. As Finance staff, I want existing voided retake/resit charges migrated into `voided` obligations, so that void history is preserved cleanly instead of as a "committed" contortion.
18. As Finance staff, I want backfill to leave scholarship/voucher/defer-credit charges untouched, so that credit/discount data is not misclassified as debit obligations.
19. As Academic Affairs staff, I want cancelling a retake/resit to route Finance side effects through a Finance-owned cancellation service, so that Academic never voids charges or cancels DNG directly.
20. As a developer, I want an architecture test that fails CI when Academic references Finance models (or vice versa), so that the boundary cannot silently erode again.
21. As a developer, I want the money models to live in `App\Modules\Finance\Models`, so that cross-context access is impossible by namespace, not just by convention.
22. As Finance staff, I want the intake router to accept a `financial_effect` and reject `credit`/`discount` for now with a clear "unsupported yet" error, so that the contract shape is future-proof without pretending to support unmigrated effects.
23. As a future integrator (admission/bookstore/insurance), I want the same intake door and source triple, so that adding a new fee source later requires no new integration architecture.
24. As Finance staff, I want the priced amount stamped at acceptance with `pricing_rule_version` and a pricing snapshot, so that an old obligation stays auditable after the catalog changes.
25. As Academic Affairs staff, I want the approve/commit use case to fail and roll back if Finance intake fails, so that a registration is never left "chargeable" without an obligation.
26. As Finance staff, I want `finance_charges.active_source_key` retired after backfill, so that charge idempotency lives on the obligation and a charge no longer knows any external source.
27. As BOD/analytics, I want reporting and Fee Monitor to keep reading `finance_charges` unchanged, so that the cutover does not break existing finance reports.

## Implementation Decisions

- **Modules touched:** `App\Modules\Finance` (new intake, aggregate, pricing, settlement reader, cancellation service, backfill), `App\Modules\Academic` (transition calls intake, drops `finance_charge_id`, keeps `hq_fee_status` as projection), `app/Shared/Contracts/Finance` (new contracts alongside existing `HubStudentFinanceSummaryReader`).
- **Correlation:** the only cross-context token is the Obligation Source Reference `source_system` + `source_kind` + `source_ref`. Source owner mints `source_ref`. Finance stores no `::class`/`source_type`; Academic stores no `finance_charge_id`/`finance_obligation_id`.
- **Aggregate:** `FinanceObligation` is **debit-only**. `lifecycle_status ∈ {requested, accepted, rejected, cancelled, voided, superseded}`. Idempotency key: `unique(source_system, source_kind, source_ref, obligation_type)`.
- **Intake contract:** one door, `FinanceIntakeContract::request(intake)`, routes by `financial_effect`. Slice-1 wires only `Debit` → `requestDebit(...)`; `Credit`/`Discount` throw `UnsupportedFinancialEffectYet`. `requestDebit` receives debit only. Intake payload carries pricing **facts** only — never amount/currency/pricing version.
- **Pricing:** Finance owns a minimal pricing catalog for `retake_fee` and `exam_resit_fee`. Finance prices from (payload facts + catalog) with **zero** reads into Academic tables. `finance_obligations.amount` is the priced result stamped at acceptance with `pricing_rule_version` + `pricing_snapshot`.
- **Eager materialization:** at the Academic chargeable transition, intake upserts the obligation, prices it, auto-accepts (fixed/deterministic/evidence-free), and creates `FinanceCharge` + `InvoiceLine` before the source is "fully chargeable". Forward path is **synchronous** for slice-1; Finance failure rolls back the Academic transition.
- **Settlement is derived:** a `ObligationSettlementReader` contract computes settlement (`unpaid/partially_paid/paid/overpaid/settled_by_discount_or_credit`) from the ledger, keyed by `source_system + source_kind + source_ref + obligation_type`. No settlement flag is stored on the obligation.
- **Financial Clearance:** hard gates call the reader synchronously. Absence of an obligation → `missing_finance_obligation` (never "cleared"); genuine no-fee is an explicit Finance decision.
- **DNG:** DNG creates payment requests for existing payables only; the auto-create-on-push path (`ensureRetakeChargesExist`/`ensureExamResitChargesExist`) is removed. Missing obligation → block/repair, never silent create.
- **Backfill:** existing active retake/resit charges → `accepted` obligations linked to the charge (source triple synthesized from current `source_type`/`source_id`); voided → `voided` obligations; scholarship/voucher/defer-credit charge types untouched. Legacy `retake_fee`/`fee_amount` columns may seed historical `priced_amount` with provenance `legacy_backfill` only.
- **Cancellation:** Academic retake/resit cancel keeps its UI/use case but routes Finance effects through a Finance-owned cancellation service.
- **Boundary enforcement:** money models move to `App\Modules\Finance\Models`; a Pest arch test (or Deptrac) forbids `App\Modules\Academic\* ↔ App\Modules\Finance\Models\*`; `active_source_key` dropped after backfill. Enforcement is real only once CI (`D1`) is re-enabled.
- **Deferred aggregates (design-defined, not built):** `FinanceCreditEntitlement`, `FinanceDiscountEntitlement`, and the credit/discount router branches — slice-2+ when scholarship/voucher/defer-credit migrate.

## Testing Decisions

- **What makes a good test here:** exercise external behaviour through the boundary contracts and existing entry points, not Finance internals. Assert observable outcomes — an obligation exists, a charge+line materialized, settlement derived from ledger matches expectation, a gate blocks/clears, a duplicate intake is idempotent, backfill produces the right lifecycle — not private method calls or table-row shapes beyond the contract.
- **Seams (test map):**
  1. `FinanceIntakeContract::request(intake)` / `requestDebit(...)` — write door; idempotency by the source quad; `request()` throws `UnsupportedFinancialEffectYet` for credit/discount.
  2. `ObligationSettlementReader::getSettlement/isSettled(source_system, source_kind, source_ref, obligation_type)` — read/clearance door; derived from ledger; absence → `missing_finance_obligation`.
  3. Academic approve/commit action (retake/resit) — existing seam; chargeable transition yields an obligation; Finance failure rolls back.
  4. DNG batch action — existing seam; no auto-create; blocks missing obligation.
  5. Backfill artisan command — existing command seam; active/void charges → obligations; credit/discount untouched.
  6. Pest arch/Deptrac — enforcement seam; Academic↔Finance model import fails CI.
- **Modules tested:** Finance (intake, pricing, settlement, backfill, cancellation), Academic (transition integration, projection), Shared contracts.
- **Prior art:** `tests/Feature/Finance/ExamResitChargeActionTest.php`, `ChargeDiscountCorrectnessTest.php`, `DngLedgerReconciliationInvariantTest.php`, and the `tests/Feature/Finance/Cutover/` + `Defer/` suites — same style: feature tests that drive an action/command and assert ledger/observable outcomes.

## Out of Scope

Tuition, EGC, defer/resume settlement, program-change adjustment, course-drop refund, scholarship/voucher/credit and discount **aggregates** (design-defined in ADR-0026, not implemented here), manual finance override, a database rule engine, `billing_accounts` for pre-student payers, and any full Finance rewrite. Async transactional-outbox delivery for the forward path (slice-1 uses a synchronous contract). Reverse settlement projection may be added but is best-effort and not correctness-critical.

## Further Notes

- Respect ADR-0026 throughout; the defer ADRs 0015–0025 are business-semantics-only and their implementation must later route through this same intake — do not wire defer through shared `DeferCase`/`FinanceCharge`.
- Open questions carried from grilling: exact chargeable-transition status name on `CourseRetakeRegistration`/`ExamResitAttempt` (`approved` vs `approved_waiting_commit`) must be confirmed against the current status machine before Issue 02. Pricing-catalog storage shape (table vs config) is an Issue-01 decision. CI (`D1`) is currently disabled — the arch test only bites once re-enabled.

---

## Issue split (do not action the PRD as one ticket)

- **01 — Debit intake spine** (`ready-for-agent`): FinanceObligation, minimal pricing catalog, Finance Intake Contract debit branch + router, idempotency, materialize FinanceCharge + InvoiceLine.
- **02 — Retake/resit Academic transition integration** (depends on 01): approve/commit calls intake; Finance failure rolls back Academic; Academic no longer creates/voids charges.
- **03 — Settlement/clearance reader + DNG missing-obligation guard** (depends on 01): derived settlement; absence ≠ cleared; DNG no auto-create.
- **04 — Legacy backfill** (depends on 01): active/voided retake/resit charges → obligations; credit/discount untouched.
- **05 — Boundary hardening** (depends on 01–04): retire legacy source pointers on the target path, arch test/Deptrac, model-namespace relocation, drop `active_source_key`.

Merging 01+02 is acceptable; do **not** fold DNG/backfill/arch into the first ticket.
