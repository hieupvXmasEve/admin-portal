# Design

## Domain Model

Use the existing model boundaries and keep the concepts separate:

- Academic right: `defer_cases` and `defer_case_items`.
- Enrollment state: `course_registrations.registration_status`.
- Money obligation: `finance_charges` and `invoice_lines`.
- Real cash: `payments` and `payment_applications`.
- Provider collection state: DNG payment requests and charge/installment links.

The defer policy applies to real paid cash only:

- `PRESERVE`: the student keeps the paid amount that has not been consumed.
- `FORFEIT`: the school consumes paid cash up to the forfeited amount. If the
  student has not paid, there is nothing to consume.

Per ADR-0017, `PRESERVE` is a carry-forward cash rule. It does not make future
study free and it does not skip the future obligation.

Partial defer is not a money policy. Per ADR-0016, partial means course-scope
defer: selected course registrations are deferred, and the selected courses are
the business fact. Do not infer or ask for a partial money amount.

Registration state rule:

- A deferred original registration is not an active payable registration.
- A future re-enrollment is a new payable registration and should generate a
  normal charge.
- Re-enrollment must not reactivate or reuse the original deferred registration.
  The original registration remains historical and non-billable.
- Return study creates new registrations only for the deferred scope. Completed
  or non-deferred courses are not registered again by this flow.
- The link between the future registration and the original deferred course
  should rely on existing course-registration lineage where available
  (`original_registration_id` or equivalent current relationship), not on a
  Finance-only table.

Amount rule:

- Do not invent course-level money if the source charge is a semester-level
  tuition charge. Course-scope defer against semester-level tuition is manual
  Finance review; Swinx must not auto-split by credits, course count, duration,
  or any other formula.
- Full-scope defer can operate at the semester-charge level when the charge
  clearly represents the deferred term.
- Course-scope defer can auto-operate only when the source charge maps directly
  to the course registration.
- A legacy `fee_policy = PARTIAL` value is a data cleanup/classification concern,
  not an instruction to split money by percentage.
- Ambiguous amount cases must be reported for review, not silently settled.

Early-study payment rule:

- Early-study defer applies only inside the first two weeks from the first class
  session of the covered study scope and requires Academic Affairs plus Student
  Services confirmation before Finance preserves paid cash.
- Fully paid cases may preserve the confirmed covered scope under the term
  tuition entitlement model.
- Partially paid cases preserve only actual paid cash as available balance,
  close the old obligation, and later create the normal return-study obligation
  with that balance applied first. They do not grant full term entitlement
  without explicit review/top-up.
- Unpaid cases close the old obligation without collection and create the normal
  return-study obligation when the student returns.
- Ambiguous payment state remains Finance review before money changes.

Manual review outcome rule:

- `keep_fee_unchanged`: no Finance mutation; the semester-level obligation stays
  collectible.
- `manual_release_adjustment`: staff supplies an explicit amount and free-text
  reason; implementation must use approved Finance repair operations and must
  not derive the amount from an automatic split formula. The amount must not
  exceed real paid cash available on the student account. If no cash has been
  paid, there is nothing to release.
- `follow_up_later`: no Finance mutation; the case remains visible as a Finance
  follow-up with a note.

Reducing or voiding unpaid obligations is a different Finance repair decision.
It must not be smuggled into `manual_release_adjustment`.

## Application Flow

### Existing-data backfill

1. Find existing defer cases.
2. For each `FULL` case, create missing `defer_case_items` from the student's
   course registrations in the defer semester.
3. Classify Finance state without mutating money by default:
   - auto-safe preserve/forfeit candidate
   - no registration
   - no charge
   - charge already voided
   - course-scope defer backed by semester-level tuition charge, manual review
   - live DNG request
   - discount/scholarship involved
   - legacy amount-based partial row
4. Emit an operator-readable report and deterministic counts.
5. Only apply money mutations for cases proven auto-safe by tests and dry-run
   output.

### New runtime defer

1. Academic staff records a defer action.
2. Runtime creates `defer_case`.
3. Runtime snapshots item-level `defer_case_items` for all affected course
   registrations, including full-scope defer cases.
4. Runtime marks affected `course_registrations` as `defer`.
5. Finance policy action classifies the source charge and payment state.
6. If DNG is live, stop with a clear blocked state/instruction and route through
   the DNG cancellation flow before Finance mutation.
7. If the case is auto-safe:
   - void/release the original deferred obligation with auto-reallocation
     disabled where cash must remain available;
   - for `FORFEIT`, create or use an existing adjustment/manual fee style charge
     for the consumed paid amount, capped by actual paid cash;
   - allocate only the consumed paid amount to that consumed obligation;
   - leave the preserved paid balance unallocated for future normal allocation.
8. If the case is ambiguous, leave the academic defer state intact and report a
   Finance review item rather than corrupting the ledger.

### Future re-enrollment

1. Student resumes academic status.
2. Return study creates new course registrations for the deferred scope only:
   the deferred term/course set for full-scope defer, or the selected deferred
   courses for course-scope defer.
3. The new registrations link back to the original deferred registrations
   through `original_registration_id` or the current equivalent lineage
   relationship.
4. Completed and non-deferred original registrations are left untouched.
5. Charge generation creates a normal new charge for the new registration or
   term. The original deferred registration remains historical and non-billable.
6. Existing allocation logic applies available student cash to the new charge.
7. Defer policy must not skip the new charge merely because the prior defer was
   `FULL + PRESERVE`.
8. DNG collection is allowed only after staff chooses whether to use applicable
   preserved/unapplied cash. Use-balance allocates before collection; do-not-use
   leaves the cash unapplied and records a free-text override reason without
   requiring a separate permission or controlled reason list. Ambiguous cash
   still stops for Finance review. See ADR-0015.

Internal transfer/chuyển trường nội bộ is intentionally out of this flow. It
covers campus transfer, program transfer within the same campus, or combined
campus + program transfer. It may share the same conceptual layers: academic
right, obligation, real cash, and provider collection. It should be modeled as a
separate lifecycle settlement workflow. ADR-0018 records a parked
internal-transfer boundary, but FIN-REV-020 handles defer/bảo lưu first.

## Interface Contract

This is primarily backend/admin behavior.

Expected command/action surfaces:

- A dry-run backfill command or action that reports item-level and Finance
  classification before mutation.
- A runtime Finance action for defer policy application, called from the
  existing student action/defer flow after the academic defer case is created.
- Clear operator errors for DNG-live, ambiguous charge mapping, and legacy
  amount-based partial rows.
- A manual-review contract for course-scope defer on semester-level tuition that
  records exactly one outcome: keep fee unchanged, manual release/adjustment, or
  follow up later. Manual release/adjustment must be capped by real paid cash.
- A clear balance-use choice for DNG push when relevant unapplied cash exists,
  including a free-text override reason when staff chooses not to apply it. The
  override uses the normal DNG collection permission; it does not add a second
  permission gate or controlled reason list.

Existing public student/lecturer API contracts should not change in the first
slice. Admin web display may later show clearer defer/carry-forward evidence,
but this story's first acceptance target is backend correctness and reporting.

## Data Model

Baseline: no new tables.

Use existing data surfaces:

- `defer_cases`: case-level academic policy and preserve amount.
- `defer_case_items`: item-level academic preservation evidence.
- `course_registrations`: source and future registration lifecycle.
- `finance_charges`: original obligations, consumed adjustment/manual fee
  obligations, and normal future obligations.
- `invoice_lines`: charge-to-invoice state.
- `payments` and `payment_applications`: real cash and allocation/reversal
  truth.
- `dng_payment_requests`, `dng_payment_request_charges`, and
  `finance_charge_installments`: provider collection and installment state.
- Existing `source_type` / `source_id`, `void_reason`, actor, and action-log
  references should be used first for traceability.

Schema rule:

- Do not add a defer-settlement table in this story by default.
- If implementation proves that the existing source references and audit fields
  cannot connect a consumed/released Finance mutation back to a defer case, stop
  and create a separate ADR for the smallest additive link. That ADR must
  explain which existing feature would otherwise break and why the link is not a
  second ledger.

## UI / Platform Impact

No mandatory new UI in the first slice.

Potential later UI/reporting changes:

- Student 360 can label the original registration as deferred and show whether
  paid cash was preserved or consumed using existing ledger evidence.
- Billing Exceptions and Fee Monitor should stop flagging deferred original
  registrations as missing charges.
- Backfill report output should be usable by Finance operators before any
  mutation is approved.

## Observability

Required evidence:

- Dry-run counts by classification.
- Actor, reason, and source reference on every void/adjustment/allocation
  mutation.
- DNG-live blocked cases separated from general needs-review cases.
- Finance invariant audit before and after any mutation command.

## Known Code Mismatch

`DeferCaseService::calculatePreserveAmount()` still contains legacy
`PARTIAL = 50%` amount behavior. That is no longer accepted product semantics
after ADR-0016. A later implementation slice must remove or quarantine that path
so new partial/course-scope defer cannot auto-settle as an amount percentage.

## Alternatives Considered

1. Create a new defer settlement table immediately.
   - Rejected for the first slice because it risks creating a second ledger and
     can break existing Finance features that already derive money truth from
     charges, invoice lines, payments, allocations, and DNG records.
2. Keep old preserve behavior as "skip future charge".
   - Rejected because it makes a future re-enrollment look free instead of
     creating the correct charge and allocating the student's real preserved
     cash.
3. Leave original charge active after marking registration `defer`.
   - Rejected because the original registration is no longer a billable active
     registration; keeping the old obligation active creates conflicts in
     missing-charge and fee-display flows.
4. Create unpaid forfeit debt for `FORFEIT`.
   - Rejected by product decision. `FORFEIT` consumes only money already paid,
     capped by real payment evidence.
5. Treat `PARTIAL` as "consume/preserve part of the amount".
   - Rejected by ADR-0016. Partial defer is course-scope defer; money splitting
     requires a course-level obligation mapping. Semester-level tuition stays
     manual review in the current accepted model; any future automatic split
     formula requires a separate ADR/story.
