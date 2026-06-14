# Validation

## Proof Strategy

Prove dry-run mode writes a report without changing records, commit mode
requires a matching confirmation id, and committed recalculation updates only
the selected records.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Diff builder reports changed, unchanged, and skipped records. |
| Integration | Recalculation action updates selected course records and preserves unrelated records. |
| E2E | Console dry-run and commit paths enforce filters and confirmation id. |
| Platform | Snapshot path is written under Laravel storage and can be inspected after command exit. |
| Performance | Command chunks course offerings and records; no unbounded all-database run. |
| Logs/Audit | Snapshot JSON plus model audit activity prove before and after values. |

## Fixtures

- One custom-scheme course with two academic records.
- One default-scheme course in the same semester.
- One dry-run report fixture with matching filters.
- One dry-run report fixture with mismatched filters.

## Commands

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading/GradingRecalculationCommandTest.php
./scripts/dev.sh test tests/Feature/Academic/Grading/RunGradingRecalculationActionTest.php
./scripts/dev.sh artisan academic:recalculate-grades --course-offering-id=1 --dry-run
./scripts/dev.sh artisan pint app/Modules/Academic app/Console/Commands/Academic tests/Feature/Academic/Grading
git diff --check -- app docs/stories/E-academic-grading-rule-engine docs/superpowers/plans docs/runbooks
```

## Acceptance Evidence

The story is complete when tests pass and a sample dry-run report proves record
counts, before values, after values, and target filters.
