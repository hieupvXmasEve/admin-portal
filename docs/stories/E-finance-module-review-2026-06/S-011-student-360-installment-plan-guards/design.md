# Design

## Domain Model

The implementation must separate two concepts:

- Technical installment row: a compatibility row created by backfill so DNG and
  settlement code can use one collection shape.
- Staff-approved installment plan: a business decision that a charge should be
  collected in multiple scheduled installments or an explicitly approved plan.

An actionable installment must be tied to an active positive charge, have a
ledger balance greater than zero, and belong to a staff-approved plan. A
backfilled one-row installment is not automatically a staff-approved plan.

## Application Flow

Reads:

- `GetStudent360StatusCardsQuery::installmentsCard()` must no longer select the
  first pending installment across all student charges without eligibility
  checks.
- The query should derive collectability from the same ledger-backed balance
  source used by DNG Worklist or the canonical settlement service.
- The card should expose enough metadata for UI copy to distinguish "no approved
  installment plan" from "approved plan with next collectable installment".

Writes:

- `PushNextInstallmentAction` must refuse to create DNG when the target
  installment is not eligible, the charge balance is zero, the charge is voided,
  or the row is only a backfilled technical row.
- The Student 360 endpoint currently named retry-push must be reconciled with
  the product action. Either make it a true retry-only endpoint requiring
  `last_push_error`, or introduce a separate reviewed "push next approved
  installment" command.
- DNG batch/worklist behavior must stay ledger-balance based and must not be
  weakened by Student 360 UI fixes.

## Interface Contract

Admin/staff Inertia UI:

- The `Trả góp` card must not show `Đẩy kỳ tới` for students without an approved
  installment plan.
- If a student has unpaid charges but no approved installment plan, the page
  should route staff toward the normal DNG Worklist/batch collection path or the
  explicit staff plan action, not the installment card.
- Action visibility must align with backend permissions. The UI currently checks
  `can_record_payment`, while the route requires
  `split_installment_finance_charges`; the final implementation must remove this
  mismatch.

Errors:

- Unsafe pushes should fail with a clear operator-facing reason such as
  `installment_not_approved`, `charge_has_no_balance`, or
  `installment_not_collectable`.

## Data Model

The implementation must decide how to represent staff approval. Candidate
options:

1. Add explicit metadata to installment plans or rows, such as `plan_source`,
   `approved_at`, and `approved_by_user_id`.
2. Introduce a separate installment plan table and keep installment rows as plan
   children.
3. Use a temporary heuristic such as `COUNT(installments) > 1`, but only as a
   short-lived bridge because one-row approved plans and backfilled rows would
   remain ambiguous.

Data repair must cover:

- Pending or awaiting installments on charges with ledger balance `0`.
- Installments attached to voided charges.
- DNG-linked installments whose DNG/payment state already proves they are paid
  or cancelled.
- Students such as `AUH110281` where historical settled charges still expose
  `pending` installment rows.

## UI / Platform Impact

Affected admin surfaces:

- `resources/js/components/finance/student360/StatusCards.vue`
- `resources/js/pages/Finance/Student360/Show.vue`

Affected backend paths:

- `app/Modules/Finance/Queries/Student360/GetStudent360StatusCardsQuery.php`
- `app/Modules/Finance/Actions/PushNextInstallmentAction.php`
- `app/Modules/Finance/Http/Web/Admin/FinanceChargeController.php`
- `app/Modules/Finance/Actions/CreateBatchDngFromChargesAction.php`

## Observability

The final implementation should log rejected unsafe installment pushes with
charge id, installment id, student id, reason, and actor id. Data repair should
produce before/after row counts and preserve target-row evidence in the story.

## Alternatives Considered

1. Hide the button only when `installments.total === 1`. Rejected as the final
   shape because it is UI-only and does not protect backend writes.
2. Treat every pending backfilled row as collectable. Rejected because settled
   charges can still have pending installment rows.
3. Keep using Student 360 for all DNG pushes. Risky unless the action is
   explicitly ledger-balance and approval guarded.
