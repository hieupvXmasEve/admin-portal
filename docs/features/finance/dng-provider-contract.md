# DNG Provider Contract Evidence

Last updated: 2026-07-11
Owner: Finance Module
Status: release gate for Finance Settlement Position Convergence

This is the contract for new collection work. It separates local adapter schema
examples from provider behavior that has not yet been independently confirmed.
The legacy DNG adapter is evidence of what Swinx expects to send and receive; it
is not proof that DNG offers an idempotency, cancellation, or per-item lookup
SLA. No later wave may infer a missing provider guarantee from its current
behavior.

## Evidence pack

Sanitized request/response examples live in
[`tests/Fixtures/Finance/DngProviderContract`](../../../tests/Fixtures/Finance/DngProviderContract).
`DngProviderContractFixturesTest` asserts that every fixture remains parseable,
correlatable, and free of credentials or real-student markers.

| Capability | Evidence retained | Confidence | Safe interpretation |
| --- | --- | --- | --- |
| Single debt push | `insert-new-record.json`; `DngClient::insertNewRecord` | Local schema only | A response is modeled with `TransactionID` and `PaymentId`; preserve both with the exact outgoing `ItemId` if DNG returns them. |
| Incoming payment | `callback-payment-succeeded.json`; webhook inbox implementation | Local schema only | Correlate with campus + student + `ItemId`; `PaymentId` is a payment identity, not the only correlation key. |
| Daily reconciliation | `daily-reconciliation-payment.json`; `checkPaidOfDay` adapter | Local schema only | The adapter expects the fields required to correlate a paid transaction. It is daily reconciliation, not a proven per-item terminal lookup. |
| Cancellation-shaped push | `cancel-record.json`; legacy `Amount = -1` adapter | Confirmation required | An HTTP/business success response does not prove that the original debt is unpaid or cancelled. |
| Pay-all access | `pay-all-virtual-account.json`; `FeeTypes[]` adapter | Confirmation required | The observed response lacks a transaction-to-`ItemId` map. Do not enable a combined settlement flow from it. |

`local_schema_only` fixtures are deliberately synthetic and prove only a safe,
stable test shape. They are not provider evidence. `confirmation_required`
fixtures define the safety scenario that must be proved when DNG evidence
arrives, including same-reservation retry and payment after cancellation.

Provider confirmation means one of: a current vendor document, a reproducible
non-production response signed off by DNG, or a dated staff-to-DNG result that
contains the request, response, campus, `ItemId`, payment identity, and the
named provider contact. Store only a sanitized copy in the repository.

## Money at the DNG boundary

1. The Settlement Position contract supplies VND as an exact decimal value;
   application code must not use binary floats to decide a payable amount.
2. Until DNG confirms a fractional-VND rule, outbound `Amount` is a positive
   integer number of VND. Reject a fractional value at the provider boundary;
   never round it silently.
3. The one permitted cancellation-shaped legacy message has `Amount = -1`; it
   is a state operation, never a negative cash amount or a rounding mechanism.
4. The checksum must be calculated from exactly the serialized `Amount` sent
   to DNG. The stored outbound payload is the audit source for later callback
   verification.

Before a future change permits a non-integer amount or a rounding conversion,
attach DNG evidence for accepted scale, rounding direction, and checksum
serialization, then add an exact fixture and automated assertion.

## Reservation, `ItemId`, retry, and unknown outcomes

For one logical reservation, Swinx creates one immutable `ItemId` that is
scoped locally to the provider rail, campus, Billing Account, and exact target
fingerprint. It must be deterministic/recoverable from that reservation, not a
new timestamp generated for each HTTP attempt.

- Retry the same logical reservation with the same `ItemId` and payload
  identity. A timeout, connection failure, or unreadable response is **Unknown
  Collection Outcome**, not a push failure that permits a fresh record.
- Never create a second debt record or a new `ItemId` until DNG supplies a
  terminal result for the original item.
- DNG-side uniqueness scope and retry behavior are not yet confirmed. The
  local reservation rule is therefore a money-safety requirement, not a claim
  about a DNG uniqueness constraint.

## Cancellation and terminal verification

Cancellation cancels collection only; it never voids the Finance obligation or
its source workflow. A request stays held after a cancel push until one of the
following has evidence for its exact campus and `ItemId`:

1. a DNG lookup says it is terminal/unpaid; or
2. Finance stores a staff-to-DNG confirmation with the provider's evidence.

The daily reconciliation response can prove a payment, but it does not by
itself prove absence of payment or completion of cancellation. If a payment is
reported during or after cancellation, capture its actual cash exactly once,
then route it to target allocation or **Còn dư** / an unmatched-receipt
exception. Cancellation is never permission to discard attributable money.

## Pay one and pay all

Pay-one sends exactly one DNG fee type and retains its local request, `ItemId`,
and target breakdown. Pay-all begins with one such local request per selected
fee type.

The current `FeeTypes[]` access call does not return an observed mapping from a
provider transaction to the individual `ItemId`s. Therefore a combined pay-all
payment page is disabled until DNG confirms all of the following:

1. whether it creates one transaction or many;
2. every returned payment/transaction identity; and
3. an unambiguous mapping from each identity to the exact fee-type request and
   `ItemId`.

If DNG confirms one provider receipt for several fee types, Swinx must still
store the provider-issued mapping. It must not split the amount using fee type,
total amount, or timing heuristics.

## Callback and reconciliation correlation

Every inbound callback and daily-reconciliation row used for settlement must
retain: payer (`StudentId` and local Billing Account once resolved), campus,
`ItemId`, fee type, actual amount, `PaymentId`, and available transaction/PSP
identity. Process these through one idempotent receipt-capture command.

The receipt-capture command first records attributable cash exactly once. It
then checks the current target fingerprint and Settlement Position before
allocation. A mismatch, a late receipt, or an allocation failure does not erase
cash; it blocks recollection and creates a Finance exception.

## Required provider confirmation before Wave 1 release

The following questions remain open and block release of a new DNG collection
flow, even though the fixtures let tests exercise the observed shapes:

- accepted VND scale and rounding semantics;
- DNG uniqueness scope for `ItemId` and its response to an identical retry;
- an item-level terminal-status lookup, or the audited equivalent manual
  verification procedure;
- pay-all transaction cardinality and request mapping; and
- payment behavior after a cancellation-shaped request.

When each answer arrives, replace `confirmation_required` in the matching
fixture with a provider-evidence status and source/date, then add the provider
response needed to prove the behavior without credentials or real student data.
