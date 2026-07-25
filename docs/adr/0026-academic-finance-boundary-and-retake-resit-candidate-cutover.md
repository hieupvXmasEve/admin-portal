---
id: ADR-0026
title: "Academic/Finance boundary, source-agnostic Finance intake, and the retake/resit debit cutover"
status: accepted
supersedes: earlier candidate-centric draft
last_updated: "2026-07-15 (Academic source contracts restored for retake/resit/EGC)"
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Academic/Finance boundary, source-agnostic Finance intake, and the retake/resit debit cutover

Swinx stays a Laravel modular monolith, but Academic and Finance are separate bounded contexts that must be able to change independently: a business change on one side must not force a schema or code change on the other. `Student` is a Shared Kernel identity reference only (see **Student Identity** in `CONTEXT.md`). Academic owns the **Academic Student Lifecycle**; Finance owns all money — charges, credits, discounts, invoices, payments, DNG, settlement, review, and audit.

This ADR consolidates the whole boundary decision **and** defines the first implementation slice (retake/resit debit obligations). Glossary terms used below are defined in `CONTEXT.md` and are not redefined here.

---

## 1. Goal: real schema/code independence, not just an audit layer

The target is genuine decoupling. Neither context stores the other's identity, and neither reads the other's tables to make a decision. Adding a workflow/audit layer *on top of* the existing coupling (Academic `finance_charge_id` FK + Finance `source_type = CourseRetakeRegistration::class`) was **rejected** — it preserves exactly the ripple this ADR exists to remove.

**Rejected alternative:** keep `FinanceCharge.source_type`/`source_id` pointing at Academic operational rows and only bolt on a candidate table. This was the earlier draft of this ADR; it does not deliver independence and is explicitly reversed here.

---

## 2. Boundary invariants

These are the load-bearing rules. Everything else follows.

**2.1 — Correlation by neutral triple only.** The two contexts exchange only the **Obligation Source Reference**: `source_system` + `source_kind` + `source_ref`. The source owner mints `source_ref` (Finance mints it for finance-owned intakes).

- Finance MUST NOT store an external Eloquent class name or source table id (no `::class`, no polymorphic `source_type`/`source_id` pointing at Academic).
- Source modules MUST NOT store `finance_charge_id` / `finance_obligation_id` in business tables.
- Source modules MAY store a **local projection** of fee state (Academic keeps `hq_fee_status`) for display/queue only.

**2.2 — Finance owns pricing.** Source systems send **pricing facts** (immutable business facts), never final `amount`, `currency`, `discount amount`, `due amount`, or pricing rule version. Finance MUST be able to price using only (the intake payload) + (Finance-owned pricing catalog), and MUST NOT join/read source tables to price. If a fact is missing, the **contract** is expanded — not a join. `finance_obligations.amount` is the Finance-priced amount stamped at acceptance, with `pricing_rule_version` + `pricing_snapshot` retained for audit.

**2.3 — Lifecycle status never encodes settlement.** `finance_obligations.lifecycle_status` is limited to the obligation's decision lifecycle: `requested → accepted → rejected → cancelled → voided → superseded`. Settlement state (`unpaid`, `partially_paid`, `paid`, `overpaid`, `settled_by_discount_or_credit`) MUST be **derived** from the authoritative ledger — `invoice_lines`, `payment_applications`, `discount_allocations`, `payments`. Any stored settlement summary is a rebuildable projection; if it disagrees with the ledger, the ledger wins.

**2.4 — Boundary is enforced, not merely documented.** The money models (`FinanceCharge`, `Payment`, `StudentInvoice`, …) live in `App\Modules\Finance\Models`. Architecture tests forbid `App\Modules\Academic\*` from referencing Finance money models and forbid `App\Modules\Finance\*` from importing/querying Academic source Eloquent models for course registrations, retake registrations, exam-resit attempts, and EGC blocks. Cross-context access goes only through `app/Shared/Contracts/*`.

As of the 2026-07-15 restoration slice, Finance consumes retake/resit/EGC source facts through `AcademicFinanceChargeSourceGateway` DTOs and commands. Academic owns Academic source persistence (including retake/resit fee-state transitions, EGC block creation/result/relevel/discount-consumed updates, and EGC course-result resolution); Finance owns obligations, charges, invoices, DNG, settlement, and discounts.

---

## 3. Finance intake is source-agnostic and tri-aggregate

Academic is **one source among many**. The same door serves admission, bookstore, insurance, scholarship, voucher, and finance-owned scheduled fees. But a single aggregate with a `direction = debit|credit|discount` column was **rejected**: "obligation" means something owed; credits and discounts are not owed by the student, and settlement/clearance is meaningless for them.

**Decision:** one **Finance Intake Contract**, three correctly-named aggregates. The intake router dispatches by `financial_effect`:

```
debit    → FinanceObligation            (payable receivable)
credit   → FinanceCreditEntitlement     (scholarship/defer/overpayment/sponsor credit)
discount → FinanceDiscountEntitlement   (voucher/campaign/policy discount)
```

- **Settlement and Financial Clearance apply only to `FinanceObligation` (debit).**
- Credit/discount aggregates have a **consumption/allocation** lifecycle, not a payment-settlement one. They reduce a debit obligation's outstanding when applied/allocated and never appear as unpaid blockers.

The target architecture defines all three aggregates so the debit design is not built down a path that later has to be patched for credit/discount. Implementation is sliced by financial effect (§5).

---

## 4. Read/consistency model

**4.1 — Two read modes.** A local projection (Academic `hq_fee_status`) serves display, lists, badges, and queues (eventually consistent). A **hard gate** — allowing exam sitting, active course participation, final registration, or irreversible academic progression — MUST obtain **Financial Clearance** via a *synchronous* Finance contract that reads the authoritative ledger fresh. A projection is never the sole condition of a hard gate.

The full Settlement Position contract remains internal to Finance. Shared
cross-context readers expose only narrow, source-keyed clearance or settlement
answers and delegate to Finance's canonical reader; they never expose payable
lines or payment/discount/credit application structure to Academic.

**4.2 — Absence is not clearance.** A chargeable source with no matching obligation blocks as `missing_finance_obligation`. A genuine no-fee case must be an explicit Finance decision (`waived` / `not_required` / `zero_amount`), never inferred from absence.

**4.3 — Event durability is asymmetric.** The **forward** intake (source → Finance) is correctness-critical: losing it means a lost receivable, so it is durable + idempotent (§5.2). The **reverse** settlement event (Finance → source projection) is *not* correctness-critical, because hard gates query synchronously; it may be best-effort, with light retry/reconciliation so projections do not go permanently stale.

---

## 5. First slice: full cutover of retake/resit debit obligations

"Full cutover, not pilot" means **no parallel write path for the chosen obligation types** — it does not mean building the whole Finance domain at once. Slice 1 cuts over exactly `retake_fee` and `exam_resit_fee` (debit), end to end.

**5.1 — Eager creation at the Academic chargeable transition.** When a `CourseRetakeRegistration` / `ExamResitAttempt` reaches its chargeable transition (the first transition that creates a real financial obligation — `committed`/`approved` per the source's own workflow, or `commit` if an `approved_waiting_commit` state exists), Academic calls the Finance Intake Contract, which upserts the `FinanceObligation` (idempotent by `unique(source_system, source_kind, source_ref, obligation_type)`), prices it, creates the `FinanceCharge`, and attaches/creates the `InvoiceLine` — **before** the source is considered fully chargeable. Because these fees are fixed, deterministic, and need no staff evidence, Finance auto-accepts and materializes the charge (auto-commit is allowed here; DNG is still never allowed to auto-commit).

**5.2 — Forward path is synchronous for slice 1.** The intake call runs inside the Academic approve/commit use case; if Finance fails, the Academic transition fails/rolls back. (An async transactional outbox is acceptable only with an intermediate `approved_waiting_finance` state; slice 1 chooses sync to minimise moving parts.)

**5.3 — DNG is lazy, and only for payment requests.** DNG creates external payment requests for *existing* Finance payable records only. DNG MUST NOT create a `FinanceObligation` or `FinanceCharge` from Academic source data — the current auto-create-on-push behaviour is the bypass being removed. A DNG batch that meets a chargeable source without an obligation blocks (`missing_finance_obligation`) or offers an explicit "repair missing Finance obligation" action; it never creates silently.

**5.4 — Backfill (debit retake/resit only).** Existing active `retake_fee`/`exam_resit_fee` charges become `accepted` `FinanceObligation`s linked to the existing charge, synthesising the source triple from the current `source_type`→`source_kind` and `source_id`→`source_ref`. Voided charges become obligations with `lifecycle_status = voided` (this cleanly replaces the earlier "voided → committed candidate" contortion). Backfill MUST NOT touch scholarship/voucher/defer-credit `charge_type`s — those are future `FinanceCreditEntitlement` / `FinanceDiscountEntitlement` migrations. Legacy `course_retake_registrations.retake_fee` / `exam_resit_attempts.fee_amount` may seed historical `priced_amount` with provenance `legacy_backfill`, but are never a pricing source for new obligations.

**5.5 — Bypass removal / conversion.** After cutover: the manual charge form cannot create `retake_fee`/`exam_resit_fee`; `CreateRetakeCourseChargeSimpleAction` and `CreateExamResitChargeSimpleAction` (which today take a caller-supplied amount and, for resit, read the Academic `fee_amount` column, and mutate `hq_fee_status` directly) are retired or converted into idempotent reconcile commands that never create charges directly; Academic retake/resit cancellation keeps its UI/use case but routes Finance side effects through a Finance-owned cancellation service (Academic never voids charges, mutates invoice lines, recalculates allocation, or cancels DNG). Idempotency moves onto the obligation; `finance_charges.active_source_key` is dropped after backfill (a charge no longer knows any external source — it keys on `finance_obligation_id`).

When cancellation requires an external DNG transition, the source enters
**Finance-Pending Cancellation** instead of becoming terminal inside the request
transaction. Finance resolves the collection request and obligation first; only
confirmed completion may move the source to cancelled. Failed or unknown DNG
outcomes leave the source pending with review evidence. No cross-context
transaction holds database locks across the provider call.

The forward cancellation request is durable without a cross-context DB
transaction. In the **same Academic transaction** that marks the source
Finance-Pending, Academic writes a durable **handoff outbox** row
(`academic_finance_cancellation_handoffs`). After commit, a worker delivers that
handoff into Finance, which firstOrCreates a source-keyed Finance Cancellation
Operation and processes it. A crash after pending therefore still has a
recoverable handoff (retry/re-drive), not a silent lost Finance request.

Finance processes the operation idempotently (claim + provider-attempt ledger so
stale reclaim never double-calls the provider) and publishes **append-only**
completion events through the outbox after collection and obligation effects are
committed. Late verified cash (webhook/receipt/bridge) re-enters the processor
and may append a paid-disposition upgrade event. Academic consumes each event
idempotently to finish or upgrade its local lifecycle. UI polling may show
progress but is never the completion mechanism, and Finance never calls an
Academic model callback directly.

**5.6 — Router opens the enum, enables only debit.** The intake `FinancialEffect` enum defines `Debit`, `Credit`, `Discount`; slice 1 wires only `Debit` and throws `UnsupportedFinancialEffectYet` for the others, so the interface points the right direction without pretending to support unmigrated effects.

**Slice 2+** builds `FinanceCreditEntitlement` / `FinanceDiscountEntitlement` and their router branches when scholarship, voucher, EGC discount, and defer credit are actually migrated.

---

## 6. Out of scope

Tuition, defer/resume settlement, program-change adjustment, course-drop refund, scholarship/voucher/credit and discount aggregates (design-defined here, not implemented), manual finance override, a database rule engine, and a full Finance rewrite. Each is a later obligation-type slice or a separate ADR. EGC money remains Finance-owned; only the Academic EGC block/source facts and state transitions now sit behind the Academic contract.

---

## Consequences

- The Academic↔Finance seam becomes namespace-enforceable: one side's refactor cannot ripple into the other because neither stores the other's identity and an arch test blocks the import.
- Slice 1 is larger than a table addition — it stands up the debit intake spine (obligation + pricing catalog + intake contract + sync clearance + durable forward + projection reverse + backfill + boundary enforcement). That cost is paid once; later effect-types reuse the spine.
- Two idempotency guards coexist transiently during migration (`active_source_key` vs the obligation unique key); §5.4/§5.5 resolve this by moving idempotency to the obligation and dropping `active_source_key` after backfill.
- Reporting, Fee Monitor, and DNG continue to read `finance_charges`; they now reach source context (if ever needed) through the obligation's source triple + a contract, not a polymorphic join.
