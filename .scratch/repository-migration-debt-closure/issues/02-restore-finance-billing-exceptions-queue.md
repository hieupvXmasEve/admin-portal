# Restore the Finance Billing Exceptions queue

Status: completed

Portal impact: none

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Restore the staff Billing Exceptions queue as a complete, trustworthy Finance workflow. The default queue and every individual exception filter must use the correct query abstraction, resolve tuition timing through the accepted Program Enrollment boundary, and preserve the current staff-facing contract.

## Acceptance criteria

- [x] The default/all queue and every individual exception type load without an Eloquent-only method being called on Query Builder.
- [x] Counts, rows, campus and Academic Period scope, ordering, pagination, encoded identifiers, and fixability semantics remain consistent.
- [x] Zero-tuition-waiver pricing resolves through the canonical Program Enrollment reader rather than a partial Student object.
- [x] Characterization coverage includes the default/all union path and the focused Billing Exceptions suite passes.
- [x] No application data is changed by this slice.

## Blocked by

None - can start immediately.

## Comments

- 2026-07-22: Completed the Billing Exceptions baseline slice. `BillingExceptionCollector::retakeNoChargeUnionQuery()` now selects the union's three-column shape directly and no longer calls `toBase()` on an `Illuminate\Database\Query\Builder`. Zero-tuition row mapping now passes `ProgramEnrollmentReader::forStudentId()` output to `StudentChargeTimingResolver`, removing the partial `Student` workaround. Added default/all, `defer_no_case`, campus/semester scope, ordering, pagination, encoded-ID, and fixability characterization coverage. Evidence: `./scripts/dev.sh test tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php --compact` → 16 passed / 64 assertions; pagination plus related owner-reader architecture checks → 5 passed / 14 assertions; Pint and `git diff --check` passed. `./scripts/dev.sh test` remains blocked by a reproducible 256 MB PHP memory fatal during global discovery. No application data or portal files were changed; portal impact none.
