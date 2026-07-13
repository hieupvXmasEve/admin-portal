# Exec Plan

> **Superseded rule notice (2026-07-12):** Không thực hiện cleanup hoặc unique constraint chỉ vì một student có nhiều invoice trong cùng kỳ. Hạng mục DB-03/INV-6 theo nghĩa cũ đã bị loại khỏi kiến trúc hiện tại; `INV-17` bảo vệ student/semester scope của invoice line.

## Goal

Clean known duplicate/idempotency data and add DB guards that match the Finance
ledger model.

## Scope

In scope:

- `FIN-13`, `FIN-14`, `FIN-25`, `DB-03`, `DB-04`, `DB-05`, `DB-06`, `DB-08`, `DB-10`.
- `DB-09` only where operation idempotency requires schema support; the
  business lock/idempotency behavior itself belongs to story 002.
- Relevant `DB-16`, `DB-17`, `DB-18`, `DB-19`, `DB-21`, `DB-24` follow-ups if
  they fit the same migration window.
- `DB-20` migration safety for historical money columns and rollback data loss.
- `DB-25` charset/collation verification for Vietnamese finance text.
- Retry-on-collision for existing unique invoice numbers.

Out of scope:

- Ledger calculation refactor from story 002.
- DNG checksum behavior from story 005.
- BOD dashboard.

## Risk Classification

Risk flags:

- Data model.
- Audit/security.
- Existing behavior.
- Weak proof.

Hard gates:

- Migrations and data cleanup.
- Potential data loss if duplicate invoices are mishandled.

Portal impact:

- None.

## Work Phases

1. Discovery: run invariants and schema/index inspection against the target DB.
2. Export remediation candidate sets for duplicate invoices and payload hashes.
3. Create dry-run cleanup command or manual runbook with exact post-checks.
4. Apply human-approved cleanup on the target dataset.
5. Add additive migrations for safe unique/FK/CHECK/index guards.
6. Align Finance model enums/value lists with DB guards, including charge types
   and lifecycle review statuses.
7. Verify charset/collation and document any non-UTF8 production drift before
   adding text-sensitive constraints or seed labels.
8. Rewrite any money-column migration plan away from drop-and-readd if it would
   lose historical data or block zero-downtime deployment.
9. Add optional operation-token storage only if story 002 needs it.
10. Add application guards where DB partial uniqueness is not practical.
11. Add regression tests for migration assumptions and idempotency boundaries.
12. Run full post-check invariant audit.

## Stop Conditions

Pause for human confirmation if:

- Duplicate invoices represent real split obligations rather than duplicates.
- Cleanup would void, merge, or reassign payments.
- MySQL CHECK behavior differs in the target environment.
- A proposed index duplicates an existing FK/unique index.
- Soft-delete changes would require broad raw-query review outside this story.
- Product has not decided whether dead statuses such as lifecycle review
  `Ignored` should remain or be removed.
- Re-applying EGC exemption/scholarship after void is a policy decision rather
  than a schema cleanup.
- Target DB charset/collation is not UTF-8 compatible for Vietnamese labels or
  notes.
- A proposed migration would drop and recreate amount columns containing
  historical values.
