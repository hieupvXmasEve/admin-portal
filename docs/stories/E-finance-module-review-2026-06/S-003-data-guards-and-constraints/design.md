# Design

## Domain Model

Use invariant failures as the remediation queue:

- INV-6: duplicate `student_invoices(student_id, semester_id)`.
- INV-11: duplicate `dng_webhook_events.payload_hash`.

After remediation, add guards for the reviewed DB findings without changing the
append-only ledger model.

## Application Flow

- Export duplicate invoice groups with charge, invoice line, payment, and DNG
  context.
- Human-review each duplicate group and choose canonical/remediation action.
- Restore or add safe constraints only after proof queries are zero.
- Add retry-on-collision where a unique value already exists but the generator
  can collide.

## Interface Contract

No public route changes expected. Any admin cleanup command must be explicit,
dry-run capable, and not run automatically.

## Data Model

Candidate guard areas:

- Unique `student_invoices(student_id, semester_id)` after cleanup.
- Restore unique `dng_webhook_events.payload_hash` after duplicate handling.
- FK guards for voucher application references.
- CHECK constraints for money signs and status enums where compatible.
- Model drift guards for Finance enum/value lists such as invoice statuses,
  charge types, installment statuses, and dead lifecycle review statuses.
- Composite indexes from the review that are not redundant with existing FK or
  unique indexes.
- Soft-delete safety only with raw query filters updated.
- Optional operation idempotency token storage if story 002 proves
  check-before-write under lock is not enough for a specific mutation boundary.
- Migration safety for historical money columns: avoid drop-and-readd patterns
  for live amount fields; use expand -> backfill -> contract and document
  rollback data risk (`DB-20`).
- Charset/collation verification for Vietnamese text fields before relying on
  environment defaults (`DB-25`).

## UI / Platform Impact

No required UI. Optional admin reports/commands may be added for cleanup
evidence.

## Observability

Migration and cleanup commands must log dry-run counts, changed ids, and
post-check results.

## Alternatives Considered

1. Add constraints immediately.
   - Rejected because current duplicate data would fail migrations.
2. Add unique constraints to ledger rows.
   - Rejected because one operation can validly write multiple signed ledger
     rows.
