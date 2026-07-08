# Exec Plan

## Goal

Implement backend-safe defer Finance settlement so academic defer decisions,
course registration status, expected-fee logic, and real payment ledger behavior
agree without creating a parallel money ledger.

## Scope

In scope:

- Backfill item-level `defer_case_items` for existing full-scope defer cases.
- Runtime creation of item-level defer evidence for new full-scope defer cases.
- Mark affected original course registrations as `defer`.
- Treat deferred original registrations as non-billable across Finance
  expected-fee and exception logic.
- Replace preserve-as-skip behavior with normal future charge generation plus
  normal allocation of real preserved cash.
- Ensure re-enrollment creates new registrations for the deferred scope only,
  links them to the original deferred registrations, and never reactivates the
  historical deferred registrations.
- Price return-study obligations from the current return-study rules by default;
  keep old pricing only through explicit Finance review.
- Do not automatically carry forward original discounts or scholarships to the
  return-study obligation; recalculate from current rules or require Finance
  review.
- Apply Finance policy using existing ledger operations:
  - preserve paid cash;
  - consume only paid cash for `FORFEIT`;
  - close full-scope `FORFEIT` source obligations so unpaid balance does not
    remain collectible beside the later return-study obligation;
  - require a free-text Finance reason when `FORFEIT` consumes paid cash,
    distinct from the Academic defer reason and without a separate permission;
  - avoid synthetic unpaid debt.
- Auto-settle full-scope `FORFEIT` only when the source obligation maps directly
  to the deferred scope and there is no live DNG request,
  discount/scholarship ambiguity, allocation ambiguity, or delivered-service
  collection policy to review.
- Auto-settle `PRESERVE` only when the case is auto-safe: full-scope defer,
  direct source-obligation mapping, no live DNG request, no discount/scholarship
  recalculation, no ambiguous allocation, and no internal-transfer boundary.
  In that auto-safe case, close the original obligation for the deferred scope
  so it is no longer collectible regardless of whether it was fully paid,
  partially paid, or unpaid; release only already-paid real cash as
  preserved/available cash; and route every other case to Finance review before
  money mutation.
- For early-study defer inside the first two weeks from the first class session,
  require Academic Affairs and Student Services confirmation before preserving
  paid fees. If partially paid, preserve only actual paid cash as balance,
  close the old obligation, and charge the normal return-study obligation later
  with that balance applied first. If unpaid, close the old obligation without
  collection and charge the normal return-study obligation later. Keep
  ambiguous payment state in Finance review.
- Treat partial defer as course-scope defer per ADR-0016, not as a money amount
  policy. Legacy `fee_policy = PARTIAL` rows are classified as needs-review until
  migrated or reclassified.
- Treat term-level tuition as a study entitlement over the covered term scope,
  not as a fixed cash amount per course.
- Use Academic equivalence mapping, not Finance proration, when curriculum
  changes before the covered deferred scope is studied.
- Block or route live DNG-linked cases through the DNG cancellation lifecycle
  before charge void/release.
- Enforce ADR-0015: before DNG collection on a re-enrollment charge, require a
  staff choice to use or not use legally allocatable preserved/unapplied cash.
  Use-balance is the default; do-not-use requires an explicit free-text reason
  but no separate permission; ambiguous cash stops for Finance review.
- Preserve any excess cash after return-study allocation as `Còn dư`; do not
  auto-consume, auto-refund, or send surplus to DNG.
- Produce dry-run and post-run evidence for backfill and Finance mutation.

Out of scope:

- Creating a new defer settlement table or ledger by default.
- Creating unpaid penalty/forfeit debt.
- Rewriting the payment allocation engine.
- Changing DNG provider payloads or webhook processing.
- Student/lecturer portal API or UI changes.
- Broad Finance UI redesign.

## Risk Classification

Risk flags:

- Data model: the story backfills item-level defer data and may expose whether a
  later minimal storage link is needed.
- Audit/security: money mutations must remain attributable and reversible.
- External systems: DNG-linked cases must respect provider lifecycle state.
- Public contracts: staff-visible expected-fee and exception behavior changes.
- Existing behavior: current defer preserve and charge-generation rules change.
- Weak proof: historical defer data contains mixed states and must be dry-run
  classified before mutation.
- Multi-domain: Academic defer state and Finance ledger behavior must align.

Hard gates:

- Audit/security.
- External provider behavior.
- Potential data migration/backfill.

Lane: high-risk.

Portal impact: none.

## Work Phases

1. Discovery
   - Trace current defer creation, item creation, course registration status
     updates, charge generation skip rules, billing exceptions, Fee Monitor,
     void/release behavior, DNG cancellation behavior, and allocation behavior.
   - Run a read-only local data classification for existing defer cases.
   - Confirm which registration lineage field should connect future
     re-enrollment to the original deferred registration.

2. Backfill design and dry-run
   - Build an idempotent item-level backfill plan for full-scope defer cases.
   - Produce classification counts without money mutation.
   - Separate auto-safe and needs-review cases.

3. Expected-fee and exception correction
   - Update every Finance expected-fee or missing-charge query touched by this
     behavior so `registration_status = defer` is non-billable.
   - Add regression tests before changing implementation behavior.

4. Runtime defer itemization
   - Update new defer creation so full-scope defer creates item-level course
     evidence in the same transaction as the defer case where practical.
   - Ensure repeated/action-retry behavior is idempotent.

5. Finance policy application
   - Implement policy action against existing ledger operations.
   - Use void/release without hidden auto-reallocation for preserved cash.
   - Allow auto-settlement only for auto-safe full-scope `PRESERVE` cases with
     direct obligation mapping and no DNG, discount/scholarship, allocation, or
     transfer ambiguity.
   - For those auto-safe full-scope `PRESERVE` cases, close the source
     obligation for the deferred scope so old unpaid balance does not remain
     collectible beside the later return-study charge, including partially paid
     or unpaid source obligations.
   - For early-study defer, require Academic Affairs and Student Services
     confirmation of the first-two-week eligibility and covered scope before
     preserving paid fees; preserve only actual paid cash for partial payments;
     close unpaid early-study obligations without collection; keep ambiguous
     payment state in Finance review.
   - For full-scope `FORFEIT`, close the source obligation for the deferred
     scope, consume only paid cash as forfeited cash, cap consumption by actual
     payment evidence, and do not create unpaid penalty debt. Auto-settle only
     when mapping and policy state are unambiguous; route delivered-service
     collection questions to manual Finance review.
   - Require a free-text Finance reason whenever `FORFEIT` consumes paid cash.
     Do not reuse the Academic defer reason as the audit reason for keeping
     money, and do not add a separate permission or controlled reason list.
   - Keep course-scope/legacy partial rows in needs-review unless their source
     obligations map directly to selected course registrations. Semester-level
     tuition plus course-scope defer is manual Finance review.
   - For term-level tuition, use course registrations as scope evidence only;
     do not split preserved cash into per-course buckets.
   - For curriculum changes, require Academic equivalence mapping before
     Finance treats a replacement course as covered or as a new obligation.
   - For manual review, support only the accepted outcomes: keep fee unchanged,
     manual release/adjustment with explicit amount and free-text reason capped
     by real paid cash, or follow up later without money mutation.
   - Block DNG-live cases with clear operator instructions.

6. Future charge behavior
   - Remove or narrow preserve skip logic so future re-enrollment creates the
     normal return-study obligation at the correct fee granularity.
   - Prove future re-enrollment uses new registrations linked to the original
     deferred registrations, without reactivating historical deferred
     registrations or registering completed/non-deferred courses again.
   - Prove return-study charges use current tuition/fee rules by default, and
     old pricing is applied only through explicit Finance review.
   - Prove settled term-level tuition covers delayed courses in the same
     deferred term scope without creating per-course tuition charges; retake
     after failure remains a separate charge path.
   - Prove curriculum changes use Academic equivalence mapping and do not
     calculate tuition differences by old/new course count, credits, or fixed
     per-course amounts.
   - Prove original discount/scholarship rows are not automatically copied to
     the return-study obligation; only current-rule entitlement or explicit
     Finance review may reduce the new obligation.
   - Prove available cash allocation happens through existing allocation logic.
   - Prove allocation caps at the return-study obligation's outstanding amount
     and any excess preserved/unapplied cash remains visible as `Còn dư`.
   - Guard DNG preview/commit paths so staff must choose whether preserved or
     otherwise unapplied cash is allocated before provider collection. Default
     to use-balance; record a free-text override reason when staff chooses not
     to use it; do not add a separate permission or controlled reason list for
     the override; block for Finance review when allocation is ambiguous.

7. Verification and evidence
   - Run targeted unit/integration tests.
   - Run Finance invariant audit before and after mutation tests.
   - Record dry-run/backfill counts and unresolved needs-review buckets.
   - Update story evidence and add backlog items for any intentionally deferred
     UI/reporting repair.

## Stop Conditions

Pause for human confirmation if:

- The implementation needs a new table, new column, or new persistent
  relationship beyond existing ledger/source/audit fields.
- A course-level defer would require splitting a semester-level tuition charge.
  Current decision: manual Finance review only; no automatic split formula.
- A term-level tuition flow would bind fixed cash amounts to individual courses
  or classify a delayed covered course as surplus cash.
- An early-study defer would preserve paid fees without Academic Affairs and
  Student Services confirmation, or would keep collecting an unpaid
  early-study obligation after the confirmed defer closes it.
- An early-study partial payment would be treated as full term tuition
  entitlement without explicit review/top-up.
- An early-study defer with ambiguous payment state would mutate money without
  Finance review.
- A curriculum change would trigger Finance proration by course count/credits
  before Academic equivalence mapping is resolved.
- A `PRESERVE` case would auto-settle outside the accepted auto-safe boundary:
  full-scope defer, direct obligation mapping, no live DNG request, no
  discount/scholarship recalculation, no ambiguous allocation, and no
  internal-transfer boundary.
- An auto-safe full-scope `PRESERVE` implementation would release unpaid money
  as preserved cash, or would leave the old source obligation collectible beside
  the later return-study obligation because it was only partially paid or
  unpaid.
- Manual review would introduce an outcome outside the accepted set: keep fee
  unchanged, manual release/adjustment, or follow up later.
- Manual release/adjustment would exceed real paid cash, or would silently
  reduce/void an unpaid obligation instead of routing that as a separate Finance
  repair decision.
- A legacy `fee_policy = PARTIAL` row would be auto-settled as a percentage or
  amount-based money policy.
- A historical case would require consuming unpaid money or creating unpaid
  penalty debt.
- A full-scope `FORFEIT` implementation would leave the old source obligation
  collectible beside the later return-study obligation, or would keep collecting
  unpaid balance without a separate manual Finance review decision that the
  school has delivered a service and still needs to collect.
- A full-scope `FORFEIT` case would auto-settle despite a live DNG request,
  discount/scholarship ambiguity, allocation ambiguity, or delivered-service
  collection question.
- A `FORFEIT` settlement would consume paid cash without a free-text Finance
  reason, or would treat the Academic defer reason as sufficient audit evidence
  for keeping money.
- A live DNG request must be mutated outside the existing DNG cancellation flow.
- A DNG push would collect from a student while legally allocatable preserved or
  unapplied cash can settle the selected obligation, without a recorded
  staff choice and free-text reason for any do-not-use-balance override.
- A return-study allocation would consume or hide preserved cash beyond the
  selected obligation's outstanding amount, or would include surplus cash in DNG
  collection.
- A delayed class under a settled term tuition entitlement would trigger a new
  tuition charge merely because it is offered in a later term.
- A replacement/equivalent course would be charged as new tuition, or treated as
  covered, without an Academic equivalence mapping.
- Re-enrollment behavior would reactivate/reuse an original deferred
  registration instead of creating a linked new registration for the deferred
  scope only.
- Discount/scholarship behavior cannot be resolved from current Finance rules.
- A return-study flow would auto-copy old discount/scholarship rows instead of
  recalculating entitlement or stopping for Finance review.
- A return-study flow would reuse old tuition/fee pricing by default, or treat
  `PRESERVE` as a guarantee of the old price instead of only a carry-forward of
  real paid cash.
- Validation must be weakened for money, DNG, or expected-fee behavior.
