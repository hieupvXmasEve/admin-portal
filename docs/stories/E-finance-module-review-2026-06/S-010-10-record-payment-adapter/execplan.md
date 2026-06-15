# Exec Plan

## Goal

Add a safe thin adapter for recording a manual payment from Student 360.

## Scope

In scope:

- `app/Modules/Finance/Http/Requests/Student360/RecordManualPaymentRequest.php`
- `app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php`
  with `store`
- `app/Modules/Finance/routes/web.php`
- route constants/helpers for `finance.students.payments.store`
- `tests/Feature/Finance/Student360/RecordManualPaymentTest.php`

Out of scope:

- Allocation preview or apply logic.
- DNG cancellation.
- Payment drawer UI.
- New audit tables or payment schema changes.

## Risk Classification

Risk flags:

- Authorization: `create_finance_payments`.
- Public contract: new staff-web POST route.
- Existing behavior: new entry point into `PaymentService`.
- Audit/security: manual payment creates financial state.
- Weak proof: full browser/write smoke lands in M2 evidence story.

Hard gates:

- Must use `RecordManualPaymentRequest` for validation.
- Must enforce current campus visibility before write.
- Must delegate to `PaymentService::recordPayment`.
- Must not auto-allocate or duplicate settlement rules.
- Must use Inertia v3 flash API.

## Work Phases

1. Write failing feature tests for create, validation, forbidden, and
   cross-campus not-found cases.
2. Add the FormRequest.
3. Add `store` to the Student 360 payment controller.
4. Register the route and route helper.
5. Run the targeted feature test.
6. Record validation evidence and leave invariant proof to the final M2 evidence
   story.

## Stop Conditions

Pause if:

- Product confirms manual payments should auto-apply instead of becoming
  unapplied credit.
- `PaymentService::recordPayment` requires extra audited metadata not available
  in the drawer.
- The existing service bypasses a required audit/event sink.
- Campus scope cannot be enforced before payment creation.
