# Design

## Domain Model

The recalculation flow uses the S-001 aggregation action and calculator. It
compares the current `AcademicRecord` fields with recalculated values:

- `final_percentage`
- `final_letter_grade`
- `grade_points`
- `quality_points`
- `credit_points_earned`
- `credit_hours_earned`
- `is_passed`
- `completion_status`
- `grade_breakdown`

## Application Flow

1. Operator runs `academic:recalculate-grades --dry-run` with a target filter.
2. Command builds a preview report and writes a JSON snapshot.
3. Operator reviews count and changed records.
4. Operator reruns with `--commit --confirm=<report-id>`.
5. Command rechecks the target set and applies changes inside per-course
   transactions.

## Interface Contract

Console command:

```text
academic:recalculate-grades
  --course-offering-id=123
  --semester-id=45
  --syllabus-template-id=67
  --dry-run
  --commit
  --confirm=grading-recalc-YYYYMMDD-HHMMSS
```

At least one target filter is required. `--commit` is rejected unless
`--confirm` references a dry-run report generated for the same filters.

## Data Model

No migration. Snapshots are written to `storage/app/academic-grading-recalculation/`
and are treated as operator evidence.

## UI / Platform Impact

CLI and runbook only.

## Observability

The command logs report id, target filters, scanned records, changed records,
skipped records, failed records, and snapshot path.

## Alternatives Considered

1. Recalculate automatically on every scheme save.
2. Add a web button for recalculation first.
3. Mutate records without snapshots.

The selected design makes recalculation explicit and reviewable.
