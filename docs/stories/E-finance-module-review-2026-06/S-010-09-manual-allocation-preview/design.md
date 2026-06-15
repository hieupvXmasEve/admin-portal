# Design

## Domain Model

The preview is a read model around an existing `Payment`:

- `payment_id`
- `unapplied`
- `candidates[]`
  - `invoice_line_id`
  - `charge_id`
  - `label`
  - `outstanding`
  - `would_apply`

`PreviewManualAllocationQuery` must delegate to `SettlementService` for:

- unapplied payment amount
- outstanding lines for the student
- line outstanding amount

## Application Flow

1. Staff opens the Student 360 allocation drawer.
2. The drawer calls `GET /finance/payments/{payment}/allocate-preview`.
3. The controller verifies `allocate_finance_payment` and campus visibility.
4. The query returns candidate applications in default priority order.
5. The drawer may later submit one selected candidate through the existing
   `finance.payments.allocate` route.

## Interface Contract

Route:

- `GET /finance/payments/{payment}/allocate-preview`
- Name: `finance.payments.allocate-preview`
- Middleware/gate: `can:allocate_finance_payment`
- Response: `ApiResponse::success($data)`

Route helpers:

- `FINANCE_ROUTE_NAMES.PAYMENT_ALLOCATE_PREVIEW`
- `financeRoutes.student360.allocatePreview(paymentId)`

Response data:

```text
{
  payment_id: number,
  unapplied: number,
  candidates: Array<{
    invoice_line_id: number,
    charge_id: number | null,
    label: string,
    outstanding: number,
    would_apply: number
  }>
}
```

## Data Model

No schema changes.

The preview reads from existing payment, invoice line, charge, and settlement
tables through model/service boundaries.

## UI / Platform Impact

This story adds route constants/helper coverage for the later drawer, but does
not build the drawer UI.

## Observability

No money state changes occur. Feature tests must cover success, permission
denial, and cross-campus not-found behavior.

## Alternatives Considered

1. Reuse the existing auto-allocation preview endpoint.
   - Rejected because this workflow is payment-scoped and manual.
2. Let the drawer calculate candidates client-side.
   - Rejected because settlement priority and outstanding amounts belong on the
     backend.
