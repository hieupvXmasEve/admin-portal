# PRD — Finance Obligation v2 Full Migration Program

**Status:** ready-for-human
**ADRs:** 0026 (target architecture), 0027 (code-owned registry), 0028 (charge = debit read model), 0029 (billing account), 0030 (credit-application ledger)
**Glossary:** CONTEXT.md (FinanceObligation, FinanceCreditEntitlement, FinanceDiscountEntitlement, Finance Intake Contract, Obligation Source Reference, Settlement state (derived), Financial Clearance, Obligation Type Registry, Billing Account, Finance Charge)
**Predecessor:** `.scratch/finance-obligation-intake/PRD.md` = **wave 0** of this program (retake/resit debit cutover). It stays as-is; this program does not modify its issues.

> This is a **program umbrella**, not a ticket. Each wave gets its own PRD/issue
> folder (`.scratch/finance-obligation-v2-wave-N-<slug>/`) created lazily when the
> wave starts. Do not action this document directly.

---

## Problem

Wave 0 proves the architecture on two fixed fees, but the rest of the fee system
still direct-writes `finance_charges`: Batch Studio (major tuition, EGC,
non-academic), the manual charge page, repair actions, and DNG auto-create paths.
Some legacy credit/discount reductions still live as negative charge rows with no
owning aggregate.
Until every generator routes through the Finance Intake Contract and every legacy
row is backfilled to an aggregate, `FinanceCharge` remains an integration point,
the Academic/Finance boundary cannot be locked, and "why does this student owe
this money" has no single answer.

## Solution

Migrate the entire fee system onto the ADR-0026 tri-aggregate architecture in
**seven waves**, ordered risk-first: infrastructure first, simple debits second,
the big tuition group third, credit/discount aggregates fourth, the most complex
group (EGC) fifth, the audit-needed tail sixth, hardening last. Each wave cuts one
fee group over completely — generator through intake, per-wave backfill with
hard-gate reconciliation, DNG auto-create removed for that group — so the program
never runs a big-bang migration and never leaves a group half-cut.

---

## Fee inventory (source of truth)

Local DB counts sampled 2026-07-09 (`asia`); **wave-6 audit re-sampled 2026-07-10** (same `asia` baseline). Live remote prod was not opened from the audit agent — re-sample there if ops knows `asia` diverges.

| charge_type | Local rows | Generator(s) today | DNG code | Target aggregate | Wave |
|---|---|---|---|---|---|
| `retake_fee` | 16 active, 5 void | Academic retake transition (wave-0 intake) | HL | FinanceObligation | **0 (done via slice-1)** |
| `exam_resit_fee` | 20 active, 1 void | Academic resit approval (wave-0 intake) | PTL | FinanceObligation | **0 (done via slice-1)** |
| `bhyt` | 4 active, 3 void | Non-academic Batch Studio / CSV | BHYT | FinanceObligation | 2 |
| `manual_fee` | 0 local | Manual charge page (direct `FinanceCharge` create) | KHAC | FinanceObligation | 2 |
| `tuition_term` | 280 active | Batch Studio `major` + legacy batch flows | HP | FinanceObligation | 3 |
| `defer_credit` | 0 local | Defer settlement flows | — (credit, never pushed) | FinanceCreditEntitlement | 4 |
| `egc_exempt_credit` | 0 local | EGC exemption flows | — | FinanceCreditEntitlement | 4 |
| `scholarship_credit` | 124 active, sum −1,668,000,000 | Scholarship application | — | audit: FinanceCreditEntitlement when grant-like, FinanceDiscountEntitlement when fee-specific | 4 |
| `voucher_credit` | 111 active, sum −555,000,000 | Voucher application | — | FinanceDiscountEntitlement | 4 |
| `egc_level_fee` | 370 active, 58 void | Batch Studio EGC (blocks, relevel, carry-forward) | HP | FinanceObligation (+ egc_retake FinanceDiscountEntitlement) | 5 |
| `admission_fee` | **0** (re-sample) | Manual form only (requires Student); no auto generator; Fee Monitor soft-expects intake students | PRE dropdown only; auto-map **KHAC** | **No-action wave 6** — post-Approve only; no pre-Approve ADR; keep dormant until business volume | **6 audit done** |
| `course_fee` | **0** (re-sample) | **None** — formally retired (registry + no DNG reverse map) | — (retired; historical enum kept) | **Formally retire (wave 6 audit)** | **6 done (issue 02)** |
| `adjustment` | **0** (re-sample) | Manual form (signed); defer FORFEIT positive debit | KHAC | **3-way split** (no legacy backfill on sample) | **6 → issue 03** |

DNG codes with **no** internal charge_type (manual-only): `THHB` (thu hồi học bổng
— scholarship clawback), `F1` (phí giữ chỗ học bổng), `GC` (legacy, frozen).
**Wave 6 audit (2026-07-10):** PRE/THHB/F1 have **0** `dng_payment_requests` on `asia` → **stay manual DNG**; do not invent obligation types.

## Wave map

Dependencies: 1 → {2,3}; 4 → 5; {2,3,4,5,6} → 7. Waves 2 and 3 may overlap once 1 lands; 6 may start its audit any time.

### Wave 1 — Obligation Type Registry + Pricing Operations (infrastructure)
- Code-owned registry (ADR-0027): one declarative entry per obligation/entitlement
  type — financial effect, allowed source kinds, pricing strategy, DNG fee code,
  mandatory/missing-inference, installment support, cancel policy, permission.
  Converges `FeeMonitorExpectedFeeCatalog` + `DngFeeTypeOptions` facts; those files
  stop growing per-type knowledge independently.
- Pricing Operations UI, minimal CRUD: list per obligation_type; create immutable
  rule versions (fix price = new version, never edit-in-place); activate/deactivate;
  effective dating; `facts_match` as raw JSON; seed command for local/prod parity;
  **coverage warning** banner when a registry type has no active pricing rule
  (prevents the `retake_fee` prod-500 class of failure). No approval workflow, no
  structured rule builder.
- `billing_accounts` (ADR-0029): table, Student auto-provisioning, add
  `billing_account_id` to existing `finance_obligations`, and backfill it from
  the materialized charge/source resolver/facts. The current obligation table is
  source-keyed; `student_id` only exists in intake facts used by the materializer.

### Wave 2 — BHYT + manual_fee (simple debits, close the side door)
- Non-academic Batch Studio / CSV BHYT generation → intake (`source_system=finance`,
  Finance mints `source_ref`).
- Manual charge page stops calling `FinanceCharge::create` — it becomes an intake
  client (`source_kind=manual_fee`). This forces the finance-owned-source pattern
  every later wave reuses.
- Backfill bhyt + manual rows; DNG auto-create (if any) removed for these types.

### Wave 3 — tuition_term (largest group)
- Batch Studio `major` and remaining legacy batch flows → intake. Pricing facts
  (program/term/…) priced from catalog, zero reads into Academic tables.
- Installment support must be preserved (registry declares it; materializer keeps
  `finance_charge_installments` behaviour).
- Backfill 280 active rows with hard-gate reconciliation; remove tuition DNG
  auto-create.

### Wave 4 — Credit/Discount Entitlement aggregates
- Build `FinanceCreditEntitlement` + `FinanceDiscountEntitlement` and open the
  intake router's `credit`/`discount` branches (remove `UnsupportedFinancialEffectYet`).
- **No negative `FinanceCharge` rows on the new path (ADR-0030).** Discounts keep
  their existing carrier (`invoice_discounts`/`discount_allocations`). Credits get
  a new **credit-application ledger** (entitlement → invoice line, amount);
  settlement derivation gains it as a fourth source:
  `outstanding = line − payments − discount_allocations − credit_applications`.
  The settlement reader update ships in this wave.
- Migrate scholarship, voucher, defer_credit flows to intake. **Wave 4 opens with
  a scholarship carrier audit/classifier:** grant-like scholarship rows become
  credit entitlements; fee-specific scholarship rows become discount
  entitlements. Do not assume every `scholarship_credit` row is the same shape.
  **Legacy conversion splits by carrier — never both for one reduction:**
  - `defer_credit` → `FinanceCreditEntitlement` + **credit applications**;
    negative invoice line voided in the same transaction (load-bearing via the
    `deriveInvoiceSnapshot` negative-line fallback — this is the careful part).
  - `voucher_credit` and fee-specific `scholarship_credit` rows whose reduction
    already lives in **existing** `invoice_discounts`/`discount_allocations`
    → `FinanceDiscountEntitlement`; void the duplicate negative line; create
    **no credit applications** for those reductions.
  - Grant-like `scholarship_credit` rows with no discount-allocation carrier
    → `FinanceCreditEntitlement` + credit applications; void the negative line
    in the same transaction.
  - Net unchanged under the Q8 reconciliation hard gate either way; wave 4 must
    ship a test proving one reduction is never counted in both
    `discount_allocations` and credit applications (no double reduction).
- Rework the student charges API summary (`total_credits`/`net_amount` in
  `StudentFinanceController::charges`) to read entitlements; rewrite
  `CreditMemoPaymentTest` fixtures.

### Wave 5 — EGC (most complex; needs waves 1 + 4)
- Batch Studio EGC → intake: `egc_level_fee` debits; EGC block/relevel semantics as
  pricing facts; `egc_retake` discount as FinanceDiscountEntitlement; carry-forward
  handling made explicit (respect ADR-0020: no silent carry-forward).
- Backfill 370 active + 58 void rows — the largest reconciliation of the program.
- Convert legacy `egc_exempt_credit` negative rows (incl. the live −15M
  `ApplyEgcMajorEntryCreditAction` path) to entitlement + credit applications,
  per ADR-0030 — load-bearing via the settlement fallback, same care as
  `defer_credit` in wave 4.

### Wave 6 — admission_fee + course_fee + adjustment (audit-first)
- **Audit done 2026-07-10** (`.scratch/finance-obligation-v2-wave-6-audit-tail/issues/01-…`):
  zero rows for admission/course/adjustment; zero PRE/THHB/F1 DNG; post-Approve
  only for admission (no pre-Approve ADR); course_fee → retire; adjustment →
  3-way split with no legacy backfill; THHB/F1/PRE stay manual DNG.
- `admission_fee`: **no-action** implement this wave (dormant manual/student path).
- `adjustment` 3-way split on **new** writes — issue 03.
- `course_fee`: **formally retire (wave 6 audit)** — issue 02 done (registry retired, HP free-pass closed).

### Wave 7 — Hardening (endgame)
- Arch tests: no `FinanceCharge::create` in production app code outside the
  Finance materializer; tests use dedicated ledger fixture helpers; no
  Academic↔Finance model imports; every DB `charge_type` has a registry entry.
- Drop `finance_charges.source_type` / `source_id` (all rows now carry
  `finance_obligation_id` for debit charges; converted legacy credit/discount
  negative rows are voided/historical and are not part of the active charge read
  model).
- DNG: assert zero auto-create paths globally; missing obligation → block/repair.
- Retire the credit legacy backstop: assert zero active negative invoice lines,
  delete the `deriveInvoiceSnapshot` negative-line netting fallback
  (SettlementService), rewrite `LedgerSourceOfTruthTest`, drop dead
  `scopeCredits`/`CREDIT_CHARGE_TYPES` paths, and add an arch/data guard that no
  new negative charge row can appear.

### Per-wave standard closure checklist
1. Every generator for the wave's types routes through the Finance Intake Contract.
2. Legacy active/void rows backfilled to aggregates with `legacy_backfill` provenance.
3. **Reconciliation hard gate:** derived settlement of every backfilled row matches
   the old computed balance. Mismatches go to an exception list; the wave closes only
   when each is fixed or explicitly accepted with a recorded reason and reviewer.
4. DNG auto-create removed for the wave's types.
5. Fee Monitor output unchanged (or its catalog updated deliberately in the same wave).
6. Feature tests per seam (intake, settlement reader, generator, backfill command).
7. Prod row counts re-sampled and inventory table above updated.

## Cross-cutting rules

- **One intake door (Q6):** every generator — including Finance-owned ones (Batch
  Studio, manual page, repair actions) — calls `FinanceIntakeContract` with
  `source_system=finance` and a per-flow `source_kind`; Finance mints `source_ref`.
  In production app code, only the materializer touches `FinanceCharge::create`.
- **Registry is code (ADR-0027):** adding a type = code change + deploy; staff
  runtime control is pricing rules only.
- **Payer key (ADR-0029):** new aggregates key by `billing_account_id`; legacy
  ledger stays student-keyed; sources never pass a billing account — Finance
  resolves it.
- **Charge = debit read model (ADR-0028):** only debit obligations materialize
  charge rows; aggregate/ledger wins over a disagreeing materialized row; no
  business meaning on `finance_charges` absent from the aggregate.
- **No new negative charges (ADR-0030):** credit reduction flows through the
  credit-application ledger (fourth settlement source); discounts keep
  `invoice_discounts`/`discount_allocations`; legacy negative rows convert in
  their owning wave and the settlement netting fallback dies at wave 7.
- **Adjustment 3-way split (Q10):** positive adjustment → debit FinanceObligation
  (`adjustment` type); negative adjustment → FinanceCreditEntitlement (manual credit
  memo); settlement correction → ledger reallocation operation, never a charge row.
  New adjustments must declare which of the three they are.
- **DNG is a collection channel (Q7):** DNG fee codes are presentation for
  collection; per-wave removal of auto-create; wave 7 asserts globally.
- **Defer ADRs 0015–0025** stay business semantics; their implementation enters
  through this same intake when the relevant wave (4 for credits, 3/5 for
  obligations) provides the machinery.

## Out of scope

- Re-keying the legacy ledger (`finance_charges`, `student_invoices`, `payments`)
  to billing accounts.
- Deleting `finance_charges` (ADR-0028 keeps it permanently).
- A database rule engine or staff-editable type registry.
- Async transactional-outbox intake delivery — synchronous contract stays unless a
  wave demonstrably needs otherwise.
- Internal transfer settlement (parked per glossary).

## Program-close acceptance

- All three arch tests green **and enforced in CI** (requires re-enabling CI — debt
  item D1): materializer-only charge writes; no cross-context model imports; full
  registry coverage of DB charge_type values.
- `source_type`, `source_id`, `active_source_key` dropped from `finance_charges`.
- DNG has zero auto-create paths; missing obligation blocks with repair.
- Every debit charge row (active + void) carries a `finance_obligation_id`;
  converted legacy negative rows are voided or otherwise excluded from the active
  read model with migration provenance; per-wave reconciliation exception lists
  all resolved or accepted.
- Fee Monitor, reports, and CSV exports run unchanged against the read model.

## Unresolved questions

1. ~~`course_fee`: no generator or data located — real feature or dead enum value?~~
   **Resolved (wave 6 audit + issue 02):** formally retire; historical enum kept.
2. DNG `PRE`: is admission-fee collection actually happening in prod today, and
   pre- or post-Approve? Also `fromChargeType()` does not map `admission_fee` → PRE
   (falls through to KHAC) — bug or unused path? If pre-Approve collection is a
   real business need, a dedicated ADR must define the applicant-scoped
   collection path — ADR-0029 explicitly does not support it. (Wave 6 audit.)
3. THHB (scholarship clawback) and F1 (scholarship seat-hold): manual-only DNG codes
   today — do they become obligation types? (Wave 6 audit; THHB likely a debit
   obligation candidate.)
4. `scholarship_credit`: grant-level credit vs fee-specific discount split per
   student cohort needs data audit before wave 4 backfill classification.
5. CI (D1) is disabled — hardening arch tests only bite once CI is re-enabled;
   re-enablement is owned outside this program but blocks program close.
6. Prod row counts unknown for all groups (local counts above are directional only).
