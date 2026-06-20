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
- `PARTIAL`: the school consumes the paid portion that corresponds to the
  already-used amount; the remaining paid amount stays available.

Registration state rule:

- A deferred original registration is not an active payable registration.
- A future re-enrollment is a new payable registration and should generate a
  normal charge.
- The link between the future registration and the original deferred course
  should rely on existing course-registration lineage where available
  (`original_registration_id` or equivalent current relationship), not on a
  Finance-only table.

Amount rule:

- Do not invent course-level money if the source charge is a semester-level
  tuition charge and no accepted allocation rule exists.
- Full-scope defer can operate at the semester-charge level when the charge
  clearly represents the deferred term.
- Course-scope defer can auto-operate only when the source charge maps to the
  course registration or when an accepted course-fee allocation rule exists.
- Ambiguous amount cases must be reported for review, not silently settled.

## Application Flow

### Existing-data backfill

1. Find existing defer cases.
2. For each `FULL` case, create missing `defer_case_items` from the student's
   course registrations in the defer semester.
3. Classify Finance state without mutating money by default:
   - auto-safe preserve/forfeit/partial candidate
   - no registration
   - no charge
   - charge already voided
   - multi-course or semester-level charge without safe split rule
   - live DNG request
   - discount/scholarship involved
   - partial amount missing or greater than paid amount
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
   - for `FORFEIT` or `PARTIAL`, create or use an existing adjustment/manual fee
     style charge for the consumed paid amount, capped by actual paid cash;
   - allocate only the consumed paid amount to that consumed obligation;
   - leave the preserved paid balance unallocated for future normal allocation.
8. If the case is ambiguous, leave the academic defer state intact and report a
   Finance review item rather than corrupting the ledger.

### Future re-enrollment

1. Student gets a new course registration.
2. Charge generation creates a normal new charge for that registration or term.
3. Existing allocation logic applies available student cash to the new charge.
4. Defer policy must not skip the new charge merely because the prior defer was
   `FULL + PRESERVE`.

## Interface Contract

This is primarily backend/admin behavior.

Expected command/action surfaces:

- A dry-run backfill command or action that reports item-level and Finance
  classification before mutation.
- A runtime Finance action for defer policy application, called from the
  existing student action/defer flow after the academic defer case is created.
- Clear operator errors for DNG-live, ambiguous charge mapping, and partial
  amount issues.

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
4. Create unpaid forfeit debt for `FORFEIT` or `PARTIAL`.
   - Rejected by product decision. These policies consume only money already
     paid, capped by real payment evidence.
