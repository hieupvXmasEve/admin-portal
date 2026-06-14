# Overview

## Current Behavior

DNG webhook/payment processing has security and idempotency gaps. Some events
can bypass checksum checks, duplicate payload hashes are not guarded uniquely,
cancelled statuses are not ordered consistently, and external DNG calls can run
inside a transaction that may later roll back.

The provider's two-call settlement protocol is already working correctly in
production and must be preserved:

- For one successful payment DNG calls the webhook twice. Call 1 (payment
  success) arrives with empty `InvoiceSerialNumber`/`InvoiceDate`; Call 2
  arrives after the e-invoice is issued with both fields populated.
- The checksum string is
  `AccessCode + ClientCode + amount + InvoiceSerialNumber + StudentId + FeeType + CampusCode`,
  where `AccessCode`/`ClientCode` are server-side secrets. On Call 1 the
  `InvoiceSerialNumber` segment is the empty string.
- `ItemId` is identical across both calls and is the stable correlation key for
  the same payment.

The gap is that Call 1 currently skips checksum verification (because it has no
`InvoiceSerialNumber`) rather than verifying the checksum with an empty serial
segment. The settle-then-invoice behavior itself is correct; only the security
and idempotency hardening around it is missing.

Lifecycle exception DNG cancellation also has an audit-ordering residual:
`CancelRequested` can be written before the state mutation transaction, making
review state and audit state diverge on rollback (`FIN-34`).

## Target Behavior

DNG integration treats provider input as untrusted boundary data, verifies every
processable event, deduplicates safely, locks settlement-sensitive rows, and
does not revive or process cancelled requests accidentally.

Hardening must keep the working two-call flow intact: Call 1 still settles the
payment, Call 2 still only attaches invoice metadata (no second settlement), and
the existing DNG checksum formula is reused verbatim. The only change is that
Call 1 is now checksum-verified (using the empty serial segment) instead of
skipped, validated against real production payloads before enforcement.

DNG cancellation audit must be transactional or explicitly modeled as an
attempt/final-outcome event pair so rollback cannot look like a completed state
transition.

## Affected Users

- Finance staff monitoring DNG requests and webhook events.
- Students paying through the DNG gateway.
- Operators responding to provider retries or mismatches.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/features/finance/dng-payment-integration.md`
- `docs/system-architecture.md`
- `docs/code-standards.md`

## Portal Impact

None for admin DNG operations. Re-check portal impact if student-facing DNG API
response contracts are changed.

## Non-Goals

- Do not redesign charge generation.
- Do not build a new payment provider adapter.
- Do not weaken existing business-field mismatch checks.
- Do not change the working two-call settle-then-invoice semantics or the DNG
  checksum string composition; only add verification, dedup, and locks around it.
- Do not reject the no-serial Call 1 event type; verify its checksum instead.
