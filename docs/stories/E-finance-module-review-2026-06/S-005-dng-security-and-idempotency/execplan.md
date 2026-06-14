# Exec Plan

## Goal

Harden DNG webhook and request processing against forged, duplicate, cancelled,
and racing events.

## Scope

In scope:

- `FIN-15`, `FIN-16`, `FIN-17`, `FIN-18`, `FIN-19`, `FIN-32`, `FIN-33`, `FIN-34`.
- `DB-04` after duplicate cleanup.
- Admin retry behavior for impossible/terminal webhook events.
- Treat `CampusCode` correctly: it is part of the checksum string, so correct
  checksum verification already guards it (`FIN-32`); keep an explicit
  business-field match as defense-in-depth.
- Make `ItemId` a collision-safe, stable correlation key (`FIN-33`) because the
  two-call flow and settle-once both depend on it.
- Preserve the working two-call settle-then-invoice flow and DNG checksum
  composition; only add verification, dedup, locks, and audit ordering around it.

Out of scope:

- New payment provider.
- BOD dashboard.
- Full charge-generation rewrite.

## Risk Classification

Risk flags:

- Audit/security.
- External systems.
- Public contracts.
- Existing behavior.
- Weak proof.

Hard gates:

- Public webhook security.
- External provider behavior.
- Payment settlement.

Portal impact:

- None unless student payment APIs are changed.

## Work Phases

1. Discovery: map root webhook route, checksum branch (including the Call-1
   no-serial skip), mismatch checks, the two-call settle/attach paths, the
   `ItemId` correlation, status order maps, and DNG bridge transactions.
2. Capture real production Call-1 and Call-2 payloads and reproduce the existing
   DNG checksum (with empty `InvoiceSerialNumber` for Call 1) offline; confirm
   the exact `amount` serialization. Do not enforce until reproduced checksums
   match real payloads.
3. Add provider payload fixtures for Call 1 (no serial, valid/invalid checksum),
   Call 2 (full serial), out-of-order Call 2 before Call 1, checksum mismatch,
   duplicate retry, `ItemId` collision, cancelled, and late webhook cases.
4. Require checksum on every mutation-capable event, reusing the existing formula
   with the empty serial segment for Call 1. Keep Call 1 = settle, Call 2 =
   attach-invoice-only; do not reject the no-serial call.
5. Make settle-once key on `ItemId`; restore `payload_hash` dedup (retries only)
   after duplicate payload cleanup.
6. Add row locks around installment settlement and allocation boundaries, keyed
   by the payment/charge correlation.
7. Add `cancel_pushed_to_dng` ordering in webhook and reconciliation services.
8. Remove or compensate external provider calls inside uncommitted transactions.
9. Fix lifecycle exception DNG cancel audit ordering so rollback cannot leave a
   misleading `CancelRequested` state transition.
10. Update admin retry UI for terminal invalid events.
11. Run DNG targeted tests and invariant audit.

## Stop Conditions

Pause for human confirmation if:

- The reproduced Call-1 checksum cannot be made to match real production payloads
  (e.g. unknown `amount` formatting or serial-segment handling) — enforcing would
  reject legitimate live settlements.
- A change would alter the working two-call settle-then-invoice semantics rather
  than only add verification/dedup/locks around them.
- Existing production events would be rejected by the new checksum rule.
- Provider side effects cannot be moved without an outbox design decision.
- Dedup restoration conflicts with existing duplicate rows.
- Lifecycle exception history wants attempted DNG cancel events; in that case,
  state-transition audit and attempt audit must be modeled separately.
