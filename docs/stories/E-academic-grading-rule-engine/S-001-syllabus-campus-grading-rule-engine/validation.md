# Validation

## Proof Strategy

Use unit tests for calculators, feature tests for aggregation/finalization, and
targeted API/resource tests only if response shape changes.

Default weighted-percentage tests must prove existing behavior remains stable
when `grading_scheme` is null.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Default weighted percentage; Metropolia linear grade; thresholds; pass/fail; requirement gates; expression final formula; rounding after component conversion. |
| Integration | Course completion updates `academic_records` and `course_registrations` for default and Metropolia syllabi. |
| E2E | Not required for first backend slice unless a portal response shape changes. |
| Platform | `./scripts/portal-status.sh` before portal edits if a response contract changes. |
| Performance | Bounded to one course offering; no broad recalculation query in this slice. |
| Logs/Audit | `academic_records.grade_breakdown` includes engine, gates, component conversions, and final result. |

## Fixtures

- A default weighted syllabus with assignment and exam components.
- A Metropolia Programming syllabus with assignment gate and exam linear grade.
- A Metropolia Database syllabus with pass/fail assignment threshold.
- A Metropolia Maths & Physics syllabus with threshold component grades.
- A Metropolia Cloud Computing syllabus with expression final formula.

## Commands

```bash
./scripts/dev.sh test tests/Feature/Academic/Grading
./scripts/dev.sh test tests/Feature/Academic/Gpa
./scripts/dev.sh artisan pint
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
```

## Acceptance Evidence

Acceptance evidence is recorded after implementation and validation commands
run.
