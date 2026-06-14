# Design

## Domain Model

DNG request/event records are provider audit records, not the Finance ledger
truth. Provider events must bridge into `payments` and `payment_applications`
only after checksum and business-field validation pass.

### Provider protocol (existing, working in production — preserve)

- One successful payment produces two webhook calls:
  - **Call 1** (`payment success`): `InvoiceSerialNumber` and `InvoiceDate` are
    empty. This call **settles** the payment.
  - **Call 2** (after e-invoice issued): full fields. This call only **attaches**
    `InvoiceSerialNumber`/`InvoiceDate` to the already-settled payment; it must
    not create a second payment or re-settle.
- Checksum string (do not change the order or composition):
  `AccessCode + ClientCode + amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode`.
  `AccessCode`/`ClientCode` are config secrets. For Call 1 the
  `InvoiceSerialNumber` segment is the empty string, which is how DNG itself
  signs Call 1 — so Call 1 is verifiable, not unverifiable.
- `ItemId` is the same on both calls and is the stable correlation/settle-once
  key. `InvoiceSerialNumber` and `payload_hash` are not stable across the two
  calls and must not be used as the settle-once key.
- `amount` must be serialized into the checksum string with the exact format DNG
  uses (confirm against real payloads, e.g. `10000.0`); a different decimal
  rendering produces a different hash and would reject a legitimate event.

## Application Flow

- Verify checksum for every event type that can settle or mutate state,
  **including Call 1**, by reusing the existing DNG checksum formula with the
  empty `InvoiceSerialNumber` segment. Do not skip and do not reject the
  no-serial call.
- Keep `hash_equals` and config-secret behavior; do not alter the checksum
  string composition.
- Preserve two-call semantics: Call 1 settles once; Call 2 only attaches invoice
  metadata to the existing payment and is idempotent (a repeated Call 2 changes
  nothing).
- Make settle-once key on the stable `ItemId` correlation, not on `payload_hash`
  (which legitimately differs between Call 1 and Call 2).
- Deduplicate `payload_hash` only to drop exact retries of the same call, after
  known duplicates are handled. Dedup must not collapse Call 1 and Call 2.
- Apply row locks around installment settlement and payment allocation, keyed by
  the payment/charge correlation so concurrent Call 1/Call 2 or webhook +
  reconciliation cannot double-process.
- Treat cancelled DNG requests as terminal in webhook and reconciliation status
  ordering.
- Move external side effects out of uncommitted DB transaction windows where
  feasible, or add an explicit outbox/compensation pattern.
- Fix DNG cancellation audit ordering for lifecycle exception resolution:
  attempted events are allowed, but current review state must not advance unless
  the mutation commits or a paired failure event is written.

## Interface Contract

Root public webhook route remains a boundary endpoint, but accepted payloads and
failure states may become stricter. Admin DNG pages should show clear terminal
vs retryable failure reasons.

## Data Model

Restore unique `payload_hash` only after story 003 handles duplicates. Note that
Call 1 and Call 2 of the same payment have different `payload_hash` values, so a
`payload_hash` unique guard correctly keeps both rows; it dedups retries only,
not the two-call pair. Add indexes or status CHECK constraints only after schema
inspection confirms they are not redundant and match current values.

## UI / Platform Impact

DNG webhook event detail may hide impossible retry actions for checksum-invalid
events. Use existing route helpers and confirmation patterns.

## Observability

Record checksum failures, duplicate skips, terminal cancelled skips, bridge
locks, and DNG cancellation attempt/final outcomes through existing DNG
request/event audit fields.

## Alternatives Considered

1. Trust events without `InvoiceSerialNumber` (keep skipping checksum on Call 1).
   - Rejected because the public webhook route is an untrusted boundary; verify
     the checksum with the empty serial segment instead.
2. Reject the no-serial Call 1 entirely, or change the checksum string to make it
   verifiable.
   - Rejected because Call 1 is the legitimate first half of the working
     production flow and DNG already signs it; rejecting it or changing the
     formula would break live settlement.
3. Use `payload_hash` (or `InvoiceSerialNumber`) as the settle-once key.
   - Rejected because both differ between Call 1 and Call 2; `ItemId` is the
     stable key.
4. Deduplicate only in the queue job.
   - Rejected because duplicate event rows still create retry noise and races.
