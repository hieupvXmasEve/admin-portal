# FIN-REV-020 - Defer Finance Settlement

## Status

planned

## Lane

high-risk

## Decision Sources

- Glossary: `CONTEXT.md` defines defer settlement, deferred study scope,
  course-scope defer, term tuition entitlement, early-study defer window,
  partial early-study payment, academic equivalence mapping, preserved cash,
  preserved cash surplus, forfeited cash, forfeit reason, discount entitlement,
  return-study obligation, return-study pricing basis, and balance-use choice.
- ADR-0015: preserved cash requires a balance-use choice before DNG collection.
- ADR-0016: partial defer is course-scope defer, not an amount policy.
- ADR-0017: preserve defer creates a visible return-study obligation settled by
  preserved cash.
- ADR-0018: internal transfer settlement is parked separately and must not block
  this defer story.
- ADR-0019: forfeit defer closes the original obligation without unpaid penalty
  debt.
- ADR-0020: defer does not automatically carry forward discounts or
  scholarships.
- ADR-0021: return-study uses current pricing by default.
- ADR-0022: preserved cash surplus remains student balance.
- ADR-0023: term tuition entitlement is not per-course cash.
- ADR-0024: curriculum changes use academic equivalence, not money proration.
- ADR-0025: early-study defer window requires Academic and Student Services
  confirmation.

## Current Behavior

Academic defer cases can preserve a whole semester or selected courses, but the
Finance meaning is not consistent enough for real operations.

Current gaps:

- `FULL` defer cases do not always have item-level course evidence.
- A deferred course registration can still be treated by some Finance exception
  logic as an enrolled registration that needs an active charge.
- Existing preserve logic can behave as "skip charge later", which conflicts
  with the practical Finance flow where a later re-enrollment should create a
  new charge and use any real paid balance already on the student's account.
  See ADR-0017.
- Voiding a source charge releases payments, discounts, installments, and may
  auto-reallocate cash unless the caller controls that behavior.
- DNG-linked charges cannot be voided safely without first resolving the live
  provider payment request.
- Earlier notes treated `PARTIAL` as an amount policy. That is no longer the
  accepted model: partial defer means selected-course defer, not preserving or
  consuming a percentage of money. See ADR-0016.

## Target Behavior

Defer is handled as a coordinated Academic + Finance transition using the
existing ledger as the source of truth.

Business target:

- `defer_cases` and `defer_case_items` express the academic right to preserve
  course participation.
- `course_registrations.registration_status = defer` means the original
  registration is non-billable for expected-fee and missing-charge logic.
- Finance charges, invoice lines, payment applications, discounts, DNG requests,
  and installments remain the money ledger. This story must not introduce a
  parallel settlement ledger by default.
- `PRESERVE` protects real paid cash by releasing it from the original deferred
  obligation and leaving it available for a future re-enrollment charge. Per
  ADR-0017, preserve is a carry-forward cash rule, not a free-future-study or
  skip-charge rule.
- `PRESERVE` may auto-settle only for auto-safe full-scope cases: the source
  obligation maps directly to the deferred scope, there is no live DNG request,
  no discount/scholarship rule that must be recalculated, no ambiguous
  allocation, and no internal-transfer boundary. Everything outside that
  boundary becomes Finance review before any money mutation.
- Early-study defer is a separate eligibility gate: during the first two weeks
  from the first class session of the covered study scope, fee preservation
  requires Academic Affairs and Student Services confirmation of eligibility and
  covered scope. If the relevant fee was fully paid, the paid fee is preserved
  for that scope; if it was partially paid, only the real paid cash is preserved
  as balance, the old obligation is closed, and the later return-study
  obligation is charged normally with that balance applied first; if it was
  unpaid, the old obligation is closed and no longer collected, and the student
  pays the new return-study obligation when they return. Ambiguous payment state
  stops for Finance review. See ADR-0025.
- In an auto-safe full-scope `PRESERVE` case, the original obligation for the
  deferred scope is closed by the defer lifecycle and must no longer be
  collectible, regardless of whether the student had paid in full, paid
  partially, or paid nothing. Already-paid real cash is released as
  preserved/available cash; any unpaid portion is not turned into preserved cash
  and is not kept as an old collectible balance.
- `FORFEIT` consumes only real paid cash. The consumed portion is represented
  through existing Finance charge/adjustment mechanics, not through a new table
  and not by inventing unpaid debt.
- Unpaid early-study defer is not `FORFEIT`: there is no paid cash to consume,
  and the old obligation is closed by the early-study defer rule.
- In a full-scope `FORFEIT` case, the original obligation for the deferred scope
  is closed by the defer lifecycle and must no longer be collectible.
  Already-paid real cash is consumed as forfeited cash; unpaid balance is not
  preserved, not consumed, and not converted into penalty debt. Automatic
  full-scope `FORFEIT` settlement requires direct obligation mapping and no live
  DNG, discount/scholarship ambiguity, allocation ambiguity, or
  delivered-service collection policy to review. If Finance wants to keep
  collecting because a service was already delivered, that is a manual review
  decision outside automatic defer settlement. See ADR-0019.
- Any `FORFEIT` settlement that consumes paid cash requires a separate
  free-text Finance reason. This reason is distinct from the Academic defer
  reason, does not require a separate permission, and must not be restricted to
  a fixed reason list.
- Partial defer is course-scope defer (`scope_type = COURSES`) represented by
  selected `defer_case_items`, not an amount-based `fee_policy`. It never means
  "preserve/consume 50%" or "enter a partial amount".
- For term-level tuition, selected course registrations identify study scope
  only. They must not be used as a formula for splitting tuition into fixed
  cash amounts per course. See ADR-0023.
- If a course-scope defer touches a semester-level tuition obligation, Finance
  manual review has exactly three accepted outcomes: keep fee unchanged, manual
  release/adjustment with explicit amount and free-text reason capped by real
  paid cash, or follow up later without money mutation.
- When the student later returns to the deferred scope, Finance creates the
  normal return-study obligation at the correct fee granularity and allocates
  any available account balance through the normal allocation flow. This is the
  accepted return-study settlement model from ADR-0017.
- The return-study obligation is priced from the current tuition, fee, discount,
  scholarship, campus, program, and term rules that apply to the return-study
  period. The old deferred obligation's price or rules are not copied by
  default; keeping the old price requires explicit Finance review. See ADR-0021.
- If the return-study obligation is term-level tuition, settling it creates the
  right to study the covered term scope. Covered courses may be taken when
  classes become available, even in a later term, without creating a new tuition
  charge for that course. Retaking a failed course remains a separate obligation.
  See ADR-0023.
- If curriculum changes before the covered scope is studied, Academic maps the
  old covered scope to equivalent or replacement courses. Finance does not
  prorate tuition by old/new course count, credits, or fixed per-course amounts.
  Equivalent/replacement courses remain covered; outside-scope courses and
  failed-course retakes are separate obligations. Ambiguous mappings stop for
  Academic + Finance review. See ADR-0024.
- Return-study settlement carries forward only real paid cash. Discounts and
  scholarships from the original deferred obligation are not copied
  automatically; the return-study obligation must calculate discount/scholarship
  entitlement from current rules or stop for Finance review. See ADR-0020.
- Re-enrollment never reactivates or reuses the original deferred registration.
  The original registration stays historical and non-billable.
- Return study creates new `course_registrations` only for the deferred scope:
  full-scope defer returns the deferred term/course set or approved equivalents;
  course-scope defer returns only the selected deferred courses or approved
  equivalents. Completed or non-deferred courses are not registered again by
  this flow.
- The new return-study registrations should carry lineage to the original
  deferred registrations through `original_registration_id` or the current
  equivalent relationship in the repo.
- Before a re-enrollment charge can be collected through DNG, staff must choose
  whether to use available preserved or otherwise unapplied cash that can legally
  settle the obligation. The default path allocates the balance first and DNG
  collects only the remaining amount; the "do not use balance" path requires an
  explicit free-text reason. Blocked or ambiguous allocation becomes a Finance
  review item. See ADR-0015.
- If applicable preserved/unapplied cash exceeds the return-study obligation,
  allocate only up to the obligation's outstanding amount. The remaining paid
  cash stays visible as `Còn dư` for later allocation, refund, or Finance
  review; it is not consumed by defer settlement and is not pushed into DNG. See
  ADR-0022.
- A course that is covered by a settled term tuition entitlement but has no
  class yet is not `Còn dư`; it remains covered study scope until completed or
  reviewed.

## Affected Users

- Academic/Admin staff recording student defer actions.
- Finance staff reviewing charges, payments, exceptions, and Student 360.
- Finance leads auditing deferred students and historical carry-forward money.
- Students whose paid tuition is preserved or consumed by a defer policy.

## Affected Product Docs

- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/project-overview-pdr.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`
- `docs/stories/E-finance-module-review-2026-06/S-002-ledger-source-of-truth/`
- `docs/stories/E-finance-module-review-2026-06/S-004-charge-discount-installment-correctness/`
- `docs/stories/E-finance-module-review-2026-06/S-005-dng-security-and-idempotency/`
- `docs/stories/E-finance-module-review-2026-06/S-017-finance-reporting-fee-monitor/`

## Portal Impact

Portal impact: none for the first implementation slice.

This story targets admin/staff Academic + Finance behavior. If implementation
changes `/api/v1/student/*` finance responses or student-visible payment
display, that follow-up must be split or explicitly update the student portal
contract.

## Acceptance Criteria

- Backfill item-level `defer_case_items` for existing full-scope defer cases
  where course registrations are known.
- Produce a dry-run/backfill report that separates auto-safe cases from cases
  needing review: no registration, no charge, multi-course tuition charge,
  discount, live DNG request, legacy amount-based partial rows, or inconsistent
  paid amount.
- Update runtime defer recording so new full-scope defer cases also capture
  item-level course evidence.
- Treat `registration_status = defer` as non-billable across expected-fee,
  missing-charge, retake-no-charge, Fee Monitor, and billing exception logic.
- Replace "skip charge later" behavior for preserve with "create the normal
  return-study obligation at the correct fee granularity and allocate preserved
  real cash".
- Future re-enrollment creates new return-study registrations linked to the
  original deferred registrations; it must not revive original deferred
  registrations or re-register completed/non-deferred courses.
- Future return-study charges use current return-study pricing rules by
  default. Old tuition/fee rules are applied only through an explicit Finance
  review decision, never by inferring them from `PRESERVE`.
- Future return-study settlement must treat term-level tuition as a term study
  entitlement, not per-course cash. Delayed covered courses do not create new
  tuition charges; failed-course retakes do.
- Early-study defer settlement must require Academic Affairs and Student
  Services confirmation before preserving paid fees. If unpaid inside the early
  window, close the old obligation without collection and create the normal new
  return-study obligation when the student returns. If partially paid, preserve
  only actual paid cash as balance, close the old obligation, and apply that
  balance to the later return-study obligation first. Ambiguous payment state
  requires Finance review.
- Future return-study settlement under curriculum changes must use Academic
  equivalence mapping. Do not calculate tuition differences from old/new course
  count, credits, or a fixed per-course amount.
- Future return-study charges must not auto-copy original discount/scholarship
  rows. Apply only current-rule discount/scholarship entitlement or an explicit
  Finance review decision.
- Apply defer Finance policy using existing ledger operations:
  - `PRESERVE`: release real paid cash from the deferred obligation without
    hidden auto-reallocation.
  - Early-study `PRESERVE` is allowed only when Academic Affairs and Student
    Services confirm the first-two-week eligibility and covered scope.
  - Early-study partial payment preserves only actual paid cash as balance; it
    does not grant the full term tuition entitlement unless review explicitly
    approves a top-up or exception.
  - `PRESERVE` auto-settlement is allowed only for auto-safe full-scope cases
    with direct obligation mapping, no live DNG request, no discount/scholarship
    recalculation, no ambiguous allocation, and no transfer boundary.
  - In those auto-safe full-scope `PRESERVE` cases, close the source obligation
    for the deferred scope so it is no longer collectible, even when it was only
    partially paid or unpaid; release only already-paid real cash as
    preserved/available cash.
  - `FORFEIT`: close the source obligation for the deferred scope, consume only
    already-paid cash as forfeited cash, and cap consumption at actual payment
    evidence. Do not keep unpaid balance collectible or create unpaid penalty
    debt unless a separate manual Finance review decision says the school still
    needs to collect for a delivered service. Automatic full-scope `FORFEIT`
    settlement also requires direct obligation mapping and no live DNG,
    discount/scholarship, allocation, or delivered-service ambiguity.
  - A `FORFEIT` settlement that consumes paid cash must record a free-text
    Finance reason, separate from the Academic defer reason. No separate
    permission or controlled reason list is required.
  - Course-scope defer (`COURSES`) is handled by selected registrations and can
    auto-settle only when Finance can map the source obligation directly to
    those courses. If the obligation is semester-level tuition, the case must go
    to manual Finance review; do not auto-split by credits/course count/duration.
- For term-level tuition, course registrations are scope evidence, not a money
  allocation formula. Do not bind a fixed preserved amount to each course.
- For early-study unpaid defer inside the first two weeks, close the old
  obligation without collection; do not classify it as forfeited cash or
  preserved cash.
- For early-study partial payment inside the first two weeks, close the old
  obligation, preserve only actual paid cash as balance, and charge the normal
  return-study obligation later. Do not treat partial payment as full term
  entitlement without explicit review/top-up.
- For curriculum changes, use Academic equivalence mapping to decide whether
  the return-study registration is covered by the existing term entitlement. Do
  not prorate money by course count or credits.
- Manual Finance review for course-scope defer on semester-level tuition records
  one of: keep fee unchanged, manual release/adjustment with explicit amount and
  free-text reason capped by real paid cash, or follow up later without money
  mutation. Reducing unpaid obligations is a separate Finance repair decision,
  not part of this manual release outcome.
- Do not create a new settlement table or money ledger in the first
  implementation. If existing source references, charge metadata, action logs,
  and void reasons are not enough to prove auditability, pause and raise a
  separate design decision before adding storage.
- Keep DNG lifecycle safe: live DNG requests must be cancelled through the
  DNG-centric flow before a linked charge is voided or consumed.
- Keep DNG collection safe after re-enrollment: do not create or push a DNG
  request while applicable preserved/unapplied cash exists unless staff has
  explicitly chosen whether to use that balance. Allocate first by default, or
  record a free-text reason for not using the balance; do not require a separate
  permission for that choice. Block for Finance review when the cash cannot be
  matched safely.
- When preserved/unapplied cash is greater than the return-study obligation,
  allocate only the amount that settles the selected obligation and leave the
  remaining paid cash as `Còn dư`; do not auto-consume, auto-refund, or include
  the surplus in DNG collection.
- Do not preserve paid fees for early-study defer without both Academic Affairs
  and Student Services confirmation. Do not keep collecting an unpaid
  early-study deferred obligation once that confirmed case is closed.
- Do not grant full term tuition entitlement from a partial early-study payment
  without explicit Finance and Student Services review.
- Do not classify delayed-but-covered courses as surplus cash. They remain part
  of the settled study entitlement unless Academic + Finance review changes the
  scope.
- Do not classify replacement/equivalent courses as new tuition obligations
  merely because the curriculum changed. Use Academic equivalence mapping first;
  unresolved mappings become Academic + Finance review.

## Non-Goals

- Do not introduce a parallel settlement ledger.
- Do not create unpaid forfeit/penalty debt for `FORFEIT`.
- Do not treat `PARTIAL` as a money policy for new behavior; it is a legacy name
  for course-scope defer data that must be classified before any mutation.
- Do not rewrite the Finance ledger, payment allocation, or invoice snapshot
  model.
- Do not change DNG provider payloads, webhook handling, or checksum behavior.
- Do not add DB constraints until a dry-run proves historical data is clean or a
  human-approved remediation plan exists.
- Do not change student or lecturer portal APIs in this story.
- Do not fold internal transfer/chuyển trường nội bộ settlement into this defer
  story. Internal transfer covers campus transfer, program transfer within the
  same campus, or combined campus + program transfer. It may reuse the same
  lifecycle-settlement vocabulary but needs its own cases and decision record.
  ADR-0018 records the first accepted transfer boundary, but transfer work is
  parked and must not block FIN-REV-020.

## Implementation Slices

Slice 1 (item-level evidence, non-billable deferred registrations, backfill
dry-run) is implemented — see `validation.md`. The money-settlement slice is
decomposed into ordered high-risk child stories (see the epic README
"FIN-REV-020 Money-Phase Child Stories"):

- `FIN-REV-020-01-defer-policy-settlement-action` — `ApplyDeferFinancePolicyAction` (FULL PRESERVE/FORFEIT), isolated.
- `FIN-REV-020-02-defer-generation-non-billable` — defer-aware generation; remove preserve-skip.
- `FIN-REV-020-03-defer-runtime-auto-apply` — runtime auto-apply for auto-safe full-scope cases only.
- `FIN-REV-020-04-defer-reenrollment-allocation` — re-enrollment charge + preserved-cash allocation.

COURSES-scope settlement stays manual-review only when the source obligation is
semester-level tuition. Legacy `fee_policy = PARTIAL` rows stay needs-review
until they are migrated or reclassified under ADR-0016.
