# Design

## Domain Model

Normalize these money rules:

- Discounts must not exceed eligible charge amount.
- Active charge detection must ignore voided charges.
- EGC amount source must be chosen once and reused by preview and execute.
- Installment sum must match net due after discounts.
- DNG pivot amount sum must match request/payment amount after rounding.
- A DNG pivot row (`dng_payment_request_charges`) is an allocation line of the
  **current request**, not "how much the student still owes". When a request is
  built from an installment plan, each pivot amount must equal the **linked
  installment being collected**, not a slice of `charge.balance`. Pivots
  proportional to balance desync allocation truth from installment truth (a 16M
  request covering A-installment 10M + B-installment 6M must pivot 10M/6M, not
  ~12.3M/3.7M by balance).
- `charge.amount`, `installment.amount`, and `dng_payment_requests.amount`
  represent different lifecycle moments and must not silently drift after a
  discount or void.
- Voiding a charge must either cancel/void linked installments or explicitly
  block with a clear reason; auto-reallocation must not be a hidden side effect.

## Application Flow

- Extract shared helpers only where they remove real duplication.
- Keep controllers thin and use Finance Actions/Queries.
- Keep preview and execute paths using the same rule object/action where
  practical.
- Recalculate or block installment/DNG push when net due changes after split.
- Add void/installment regression coverage for linked `finance_charge_installments`.
- Isolate auto-reallocate behavior after void so staff can see or control it.

## Interface Contract

Admin web forms may receive clearer validation errors. Existing route names
should remain unless a later UI story intentionally changes navigation.

## Data Model

Possible schema changes should be additive. Money casts must align PHP models
with DB `decimal(15,2)` columns.

Add nullable `finance_charge_installment_id` to `dng_payment_request_charges`
(FK to `finance_charge_installments`, `nullOnDelete`) so a pivot can record the
installment it collects. New pivot shape:

- `dng_payment_request_id`
- `finance_charge_id`
- `finance_charge_installment_id` (nullable — legacy / ad-hoc-override rows)
- `amount`

## UI / Platform Impact

Preview tables must show the same amounts that execute will create. If EGC
source selection changes labels, use Vietnamese terminology from the review.

## Observability

Tests should assert preview/execute parity and exact cents/VND totals.

## Alternatives Considered

1. Patch only the visible EGC page.
   - Rejected because batch and direct generation paths would still diverge.
2. Ignore rounding differences as small.
   - Rejected because payment application totals must reconcile exactly.
