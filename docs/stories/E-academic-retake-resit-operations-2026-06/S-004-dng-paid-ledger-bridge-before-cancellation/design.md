# Design

## Domain Model

The canonical local paid state remains:

- `payments`: one local payment bridge for a confirmed provider payment.
- `payment_applications`: allocation and reversal rows for that payment against
  invoice lines.
- `finance_charges.balance`: derived from active invoice line payments and
  discounts; voided source charges must stop being collectible.
- `dng_payment_requests`: provider request and payment evidence.

Paid DNG statuses are provider evidence:

- `paid_uninvoiced`
- `paid_invoiced`
- `reconciled`

Awaiting DNG statuses are still collectible and may be cancelled when the source
is cancelled unpaid:

- `pending`
- `pushed_to_dng`

## Application Flow

1. Cancellation flow locks the Academic source and linked `FinanceCharge`.
2. A shared Finance action finds DNG requests linked to the charge by direct
   `finance_charge_id` or `dng_payment_request_charges` pivot rows.
3. If any linked DNG request is paid and not bridged, the action calls the
   existing `DngPaymentService::bridgeToPayment()`.
4. The bridge creates/reuses a canonical `Payment`, allocates by pivot amount or
   direct `finance_charge_id`, and sets `dng_payment_requests.payment_id`.
5. The cancellation flow refreshes the charge and classifies paid state from
   either `FinanceCharge::is_fully_paid` or linked paid DNG evidence.
6. Paid/no-refund cancellation keeps DNG/payment evidence intact, voids the
   cancelled source charge, and releases its payment allocations with
   `autoReallocate=false`.
7. Unpaid cancellation still voids the active charge and cancels only linked
   awaiting DNG requests.

## Interface Contract

No student API response shape changes. Existing student Finance endpoints
continue to derive unpaid state and unapplied credit from the local ledger.

Staff-facing cancellation confirmation remains the ACAD-RET-003 contract:

- paid evidence requires no-refund acknowledgement;
- unpaid active charge requires explicit confirmation before voiding pending
  fee/DNG collection.

## Data Model

No new migration is expected. This fix writes existing tables:

- `payments`
- `payment_applications`
- `finance_charges.status`, `voided_at`, `voided_by_user_id`, `void_reason`
- `dng_payment_requests.payment_id`
- invoice snapshot/cache fields through existing settlement recalculation paths.

## UI / Platform Impact

The `/exam-resit` worklist must classify linked paid DNG evidence as paid even
before the bridge has run, so staff sees the no-refund confirmation rather than
the unpaid-charge confirmation.

Course-retake cancellation has no new UI in this slice; backend cancellation
must apply the same provider-paid bridge before deciding whether to void local
charge/DNG. After paid cancellation, the voided source charge must no longer be
collectible and the released amount must appear as student unapplied credit.

## Observability

Log bridge attempts with charge id, DNG request id, payment id, and whether the
source then followed paid/no-refund or unpaid-void cancellation.

## Alternatives Considered

1. Change student Finance queries to hide cancelled-source charges. Rejected
   because it would fork balance semantics and risk report/portal drift.
2. Keep paid charge active and fully allocated. Rejected after UI validation
   because the cancelled retake/resit fee then disappears from unpaid lists but
   also cannot surface as student fee surplus (`phí dư`), which is the expected
   local treatment when there is no refund.
3. Add a new charge status such as `closed_no_refund`. Deferred because the
   existing ledger bridge already aligns with current portal/report formulas.
