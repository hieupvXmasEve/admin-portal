# Design

## Domain Model

Reviewed cancel has two read/write boundaries:

- `BuildDngCancelImpactQuery`: read-only linked-charge impact plus
  `LifecycleDueExceptionRowMapper::blockingReasons($request)`.
- `DngPaymentRequestController::cancelReviewed`: validates reason/ack, enforces
  gates, and delegates to `CancelDngPaymentRequestAction`.

The permission model is conditional:

- `create_finance_payments` is required for DNG cancel intent.
- `void_finance_charges` is additionally required when the selected request has
  linked charges that the action would void.

## Application Flow

1. Staff opens a DNG cancel drawer from Student 360.
2. Drawer calls `GET /finance/dng/payment-requests/{dngPaymentRequest}/cancel-impact`.
3. Backend returns linked charge impact and blocking reasons.
4. Drawer blocks submit if blocking reasons exist or void permission is missing.
5. Staff enters a reason and acknowledges the destructive action.
6. Drawer posts to
   `POST /finance/dng/payment-requests/{dngPaymentRequest}/cancel-reviewed`.
7. Backend re-checks campus, permission, impact, status, reason, and ack.
8. Backend calls `CancelDngPaymentRequestAction`.

## Interface Contract

Routes:

- `GET /finance/dng/payment-requests/{dngPaymentRequest}/cancel-impact`
  - Name: `finance.dng.payment-requests.cancel-impact`
  - Gate: `create_finance_payments`
  - Response: `ApiResponse::success($impact)`
- `POST /finance/dng/payment-requests/{dngPaymentRequest}/cancel-reviewed`
  - Name: `finance.dng.payment-requests.cancel-reviewed`
  - Gate: `create_finance_payments`
  - Conditional gate: `void_finance_charges` when linked charges exist

Request:

- `reason`: required string
- `acknowledged`: accepted

Impact response:

- `dng_request_id`
- `status`
- `amount`
- `linked_charges[]`
- `blocking_reasons[]`
- `requires_void_permission`

## Data Model

No schema changes are planned.

The implementation must confirm the current DNG-to-charge linkage shape:

- single `finance_charge_id`
- and/or any existing pivot relation

If the reason cannot be stored durably through an existing audit/log sink, pause
and decide whether a small audit extension is required before implementation.

## UI / Platform Impact

This story supplies the backend contract for the later `CancelDngDrawer`.

## Observability

DNG cancel should use the existing DNG cancel audit payload/response sink and
any existing finance action log if present. Because this story can change money
and DNG state, final M2 validation must record `finance:audit-invariants`
evidence after exercising the flow.

## Alternatives Considered

1. Add `void_finance_charges` to the old cancel route unconditionally.
   - Safer, but it could unexpectedly break existing workflows before the new
     reviewed UI is ready.
2. Only hide the button in Vue when void permission is missing.
   - Rejected because the backend route must enforce destructive permissions.
3. Reimplement cancellation logic in the controller.
   - Rejected because `CancelDngPaymentRequestAction` already owns the state
     transition and linked-charge void behavior.
