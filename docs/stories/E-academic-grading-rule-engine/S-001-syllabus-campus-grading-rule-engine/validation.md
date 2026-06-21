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

**Date:** 2026-06-21

### Test results

```
Tests: 24 passed (49 assertions) — tests/Feature/Academic/Grading
Tests: 58 passed (144 assertions) — tests/Feature/Academic/Grading + Gpa (regression check)
```

### Test coverage

| Suite | Count | Status |
| --- | --- | --- |
| GradingCalculatorResolverTest | 4 | ✅ all pass |
| DefaultWeightedPercentageCalculatorTest | 4 | ✅ all pass |
| MetropoliaV1CalculatorTest | 13 | ✅ all pass |
| GradingAggregationIntegrationTest | 3 | ✅ all pass |
| Academic/Gpa (regression) | 34 | ✅ no regressions |

### Key assertions verified

- Null `grading_scheme` → `DefaultWeightedPercentageCalculator` → existing behavior unchanged.
- `metropolia_v1` linear: 88% → grade 5, 40% → grade 1, 64% → grade 3.
- Gate failure: ASSIGNMENT 30% < 40% gate → `passed=false`, `gate_failures` populated in breakdown.
- `pass_fail` scale: 85% → P/passed, 70% → F/failed.
- `direct` conversion: 100% → grade 5, 60% → grade 3.
- `grade_breakdown` written to `academic_records` with engine, gates_passed, component details, final_grade, grade_points.
- Canvas total skip: `CanvasGradeSyncService` does NOT overwrite `final_percentage` when custom engine active.
- `finalizeAcademicRecords` reads grade_points from breakdown (not recalculated) when custom engine detected.

### Pint

3 files fixed (phpdoc_align, ordered_imports, minor style) — no logic changes.
