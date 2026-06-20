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
- Apply Finance policy using existing ledger operations:
  - preserve paid cash;
  - consume only paid cash for `FORFEIT`;
  - consume only paid cash for `PARTIAL`;
  - avoid synthetic unpaid debt.
- Block or route live DNG-linked cases through the DNG cancellation lifecycle
  before charge void/release.
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
   - Consume only paid cash for `FORFEIT` and `PARTIAL`, capped by actual
     payment evidence.
   - Block DNG-live cases with clear operator instructions.

6. Future charge behavior
   - Remove or narrow preserve skip logic so future re-enrollment creates a
     normal new charge.
   - Prove available cash allocation happens through existing allocation logic.

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
- A course-level partial defer requires splitting a semester-level tuition
  charge without an accepted course-fee allocation rule.
- A historical case would require consuming unpaid money or creating unpaid
  penalty debt.
- A live DNG request must be mutated outside the existing DNG cancellation flow.
- Discount/scholarship behavior cannot be resolved from current Finance rules.
- Validation must be weakened for money, DNG, or expected-fee behavior.
