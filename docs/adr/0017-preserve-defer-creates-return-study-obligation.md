---
id: ADR-0017
title: "Preserve defer creates a return-study obligation settled by preserved cash"
status: accepted
date: 2026-07-06
owner: "Academic + Finance"
last_verified: 2026-07-25
scope: architecture-decision
---

# Preserve defer creates a return-study obligation settled by preserved cash

## Context

When a student defers and the fee policy preserves tuition, Swinx must keep two
truths separate: the student's academic right to study later, and the money that
has actually been paid. Two models were considered:

- Treat the future return-study period as already paid and skip the future
  charge.
- Create a normal payable obligation for the return-study period, then settle
  it with preserved cash where available.

Skipping the future charge looks simple, but it hides the value of the return
study period from Finance reports, makes DNG collection ambiguous when the
preserved balance is partial, and blurs whether paid cash was actually applied
to a real obligation.

## Decision

For `PRESERVE` defer, the deferred original obligation is released or settled
according to the defer policy, and any preserved real paid cash remains
available on the student's Finance account.

For an auto-safe full-scope `PRESERVE` case, the original obligation for the
deferred scope is closed by the defer lifecycle and must no longer be
collectible, whether it was fully paid, partially paid, or unpaid. Only
already-paid real cash becomes preserved or otherwise available cash; unpaid
balance is not converted into preserved cash.

When the student returns to study the deferred scope, Swinx records a normal
payable obligation for that return-study period. Preserved or otherwise
unapplied cash is then used to settle the new obligation through the normal
allocation flow by default.

For term-level tuition, the settled obligation creates a term tuition
entitlement. Covered courses may be studied when classes become available,
including in a later term, without assigning fixed cash to each course; see
ADR-0023.

Inside the early-study defer window, paid fees may be preserved only after
Academic Affairs and Student Services confirm eligibility and covered scope.
Partial payment inside that window preserves only real paid cash as balance, not
a full term tuition entitlement; see ADR-0025.

If the curriculum changes before the student studies the covered scope,
Academic maps equivalent or replacement courses. Finance must not prorate money
by old/new course counts; see ADR-0024.

If preserved cash exceeds the return-study obligation, Swinx applies only the
amount needed to settle that obligation. The remainder stays as `Còn dư`; see
ADR-0022.

The return-study obligation is priced from the current return-study rules by
default, not from the old deferred obligation's price; see ADR-0021.

Preserved cash is real paid money only. Discount and scholarship entitlement
does not automatically carry forward to the return-study obligation; see
ADR-0020.

This means `PRESERVE` is not a "free future study" flag and not a "skip future
charge" rule. It is a carry-forward cash rule: the student keeps paid cash that
can pay the future obligation.

## Business Workflow

1. Academic staff records the defer scope.
2. The deferred original study scope becomes historical and non-billable.
3. Finance handles the original obligation according to the policy:
   - `PRESERVE`: close the original obligation for the deferred scope and
     release paid cash so it remains available for future use.
   - `FORFEIT`: consume only real paid cash the school may keep.
4. `PRESERVE` auto-settlement is allowed only when the case is auto-safe:
   full-scope defer, direct source-obligation mapping, no live DNG request, no
   discount/scholarship rule that must be recalculated, no ambiguous allocation,
   and no internal-transfer boundary.
5. Anything outside that auto-safe boundary stops for Finance review instead of
   mutating money automatically.
6. When the student returns, Swinx creates the return-study obligation.
7. Existing preserved/unapplied cash is applied to that obligation by default.
8. For term-level tuition, the settled obligation covers the deferred term scope;
   delayed covered courses do not create new tuition charges merely because they
   are offered later.
9. DNG may collect only the remaining amount after the balance-use choice is
   resolved.

## Boundaries

- This ADR covers defer/bảo lưu only. Internal academic transfer/chuyển trường
  nội bộ is an adjacent lifecycle settlement problem and should be decided
  separately. In this context, internal transfer means a campus transfer, a
  program transfer within the same campus, or a combined campus + program
  transfer. It may reuse the same vocabulary: academic right, obligation, real
  cash, and provider collection. It must not be collapsed into the
  defer-specific rules. ADR-0018 records a parked internal-transfer cash
  boundary, but it does not expand or block this defer decision.
- This decision does not introduce a parallel settlement ledger. Existing
  Finance charges, invoice lines, payments, payment applications, and DNG
  records remain the money source of truth.
- This decision does not invent unpaid debt. If a student has not paid, there
  is no preserved cash to carry forward and no cash to forfeit.
- Closing an auto-safe full-scope `PRESERVE` source obligation is a defer
  lifecycle closure, not an amount-based discount or manual reduction. It
  prevents the old obligation and the later return-study obligation from being
  collectible at the same time.
- Course-scope defer against semester-level tuition remains manual Finance
  review unless a later accepted decision defines a safe allocation formula.
- Term-level tuition must not be split into fixed per-course cash buckets.
  Course registrations are scope evidence, not a money allocation formula.
- `PRESERVE` auto-settlement must stay inside the accepted auto-safe boundary.
  If the defer scope, original obligation, DNG state, discount/scholarship
  meaning, allocation target, or transfer context is unclear, Finance review is
  required before money changes.
- Early-study preservation requires Academic Affairs and Student Services
  confirmation before Finance preserves paid cash. Partial early-study payment
  preserves cash only, unless an explicit review grants a fuller entitlement.
- Before DNG collection for a return-study obligation, Swinx must follow
  ADR-0015: staff chooses whether to use applicable balance; not using it
  requires a free-text reason.

## Consequences

- Finance reports can still show the value of the return-study period even when
  the student's net amount due is zero after preserved cash is applied.
- Student 360 and collection views can distinguish "paid by preserved balance"
  from "free/no obligation".
- Partial preserved balances are safe: Swinx applies the balance first and DNG
  collects only the remaining confirmed amount.
- Surplus preserved balances are safe: Swinx applies only what the
  return-study obligation can accept and leaves the rest visible as `Còn dư`.
- A class that is delayed to a later term is not `Còn dư`; it remains covered
  study scope until completed or until Academic + Finance review changes the
  scope.
- The internal transfer/chuyển trường nội bộ discussion should be modeled as a
  separate lifecycle settlement workflow rather than folded into FIN-REV-020
  directly. Defer/bảo lưu should be handled first.

## Implementation Boundary

This ADR records business semantics for a Finance concern (defer/bảo lưu, settlement, tuition entitlement, or transfer). It does not authorize Academic and Finance to share Eloquent models, foreign keys, or polymorphic source pointers.

Any implementation of this decision must enter Finance through the Finance Intake Contract defined in ADR-0026, using the neutral Obligation Source Reference (`source_system`, `source_kind`, `source_ref`). Academic may send pricing facts and may keep local projections for display, but Finance owns pricing, obligations, credits, discounts, settlement, DNG, and audit.

If current code uses legacy links such as `finance_charge_id`, `source_type/source_id`, or direct `App\Models\FinanceCharge` access, treat that as migration context only, not target architecture.

ADR-0015 through ADR-0025 define defer business rules; ADR-0026 defines the only accepted Academic/Finance implementation boundary.
