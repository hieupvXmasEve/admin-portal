# Exec Plan

## Goal

Add a 4-layer reviewed DNG cancel backend flow for Student 360 and close the
linked-charge void permission gap.

## Scope

In scope:

- `app/Modules/Finance/Queries/Student360/BuildDngCancelImpactQuery.php`
- `app/Modules/Finance/Http/Requests/Student360/ReviewedCancelDngRequest.php`
- `app/Modules/Finance/Http/Web/Admin/DngPaymentRequestController.php`
  with `cancelImpact` and `cancelReviewed`
- `app/Modules/Finance/routes/web.php`
- route constants/helpers for cancel impact/reviewed routes
- `tests/Feature/Finance/Dng/ReviewedCancelDngTest.php`

Out of scope:

- Drawer UI.
- DNG webhook/reconciliation changes.
- New DNG status values.
- Removing the legacy cancel route.
- Lifecycle exception action history changes.

## Risk Classification

Risk flags:

- Authorization: conditional destructive permission.
- External provider adjacency: DNG request state.
- Data loss: linked charges may be voided.
- Audit/security: destructive operator action needs reason/ack and audit.
- Public contract: new staff-web JSON/POST endpoints.
- Existing behavior: wrapper around existing cancel action.
- Weak proof: full smoke/invariants land in the final M2 evidence story.

Hard gates:

- Linked-charge cancel must require `void_finance_charges`.
- Backend must enforce the gate, not only the frontend.
- Must reuse `CancelDngPaymentRequestAction`.
- Must reuse existing blocking-reason logic where available.
- Must not write lifecycle exception events unless a separate decision approves
  that audit sink change.

## Work Phases

1. Write failing feature tests for impact, validation, successful cancel, and
   linked-charge void-permission denial.
2. Add `BuildDngCancelImpactQuery`.
3. Add `ReviewedCancelDngRequest`.
4. Add controller methods and conditional void gate.
5. Register routes and route helpers.
6. Run the targeted DNG feature test.
7. Record validation evidence; final invariant proof happens in
   `FIN-REV-010-14`.

## Stop Conditions

Pause if:

- The DNG-to-charge relation shape is unclear or differs from the plan.
- No durable audit/log sink exists for the operator reason.
- The existing cancel action would void broader data than the impact endpoint
  can preview.
- A late webhook/reconciliation behavior needs to change to support this flow.
