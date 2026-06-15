# Design

## Domain Model

This story creates a command adapter, not new domain logic:

- `RecordManualPaymentRequest` parses and validates operator input.
- `FinanceStudentPaymentController::store` enforces campus visibility and
  delegates to `PaymentService::recordPayment`.
- The created payment is expected to land as unapplied credit unless the
  existing service has documented allocation behavior.

The plan assumes "record first, allocate deliberately" so staff can review the
manual allocation preview.

## Application Flow

1. Staff opens Student 360 and chooses "Ghi nhận thanh toán".
2. The drawer posts to `POST /finance/students/{student}/payments`.
3. Route middleware enforces `create_finance_payments`.
4. The controller verifies campus visibility for the student.
5. `PaymentService::recordPayment` creates the payment.
6. Controller uses `Inertia::flash()` and redirects back.

## Interface Contract

Route:

- `POST /finance/students/{student}/payments`
- Name: `finance.students.payments.store`
- Middleware/gate: `can:create_finance_payments`

Request:

- `amount`: required numeric min `0.01`
- `method`: required string
- `paid_at`: nullable date
- `external_ref`: nullable string
- `notes`: nullable string

Service payload keys:

- `student_id`
- `amount`
- `method`
- `source = manual`
- `external_ref`
- `paid_at`
- `notes`
- `received_by_user_id`

Inertia v3 requirement:

- Use `Inertia::flash('success', ...)`; do not use removed/legacy flash
  patterns.

## Data Model

No schema changes.

The write is owned by the existing `payments` model/service behavior. Any audit
or event behavior must come from `PaymentService::recordPayment` unless a gap is
explicitly documented before implementation.

## UI / Platform Impact

This story enables the later `RecordPaymentDrawer`, but does not build it.

## Observability

Because this story changes money state, validation must later feed into the M2
invariant evidence story. The feature test for this story proves the adapter
creates a payment and enforces validation/authorization/campus scope.

## Alternatives Considered

1. Auto-allocate immediately after recording.
   - Rejected for M2 because the UX requires a preview and deliberate apply.
2. Add a new payment action.
   - Rejected because `PaymentService::recordPayment` already owns the write.
