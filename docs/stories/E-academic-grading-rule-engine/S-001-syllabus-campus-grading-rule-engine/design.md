# Design

## Domain Model

Add `syllabus_templates.grading_scheme` as a nullable JSON contract.

Supported engines:

- `default_weighted_percentage`: preserves the current weighted average model.
- `metropolia_v1`: interprets declarative component rules from the syllabus.

The rule engine produces a `GradingResult` with:

- `final_percentage`: 0-100 diagnostic score when available.
- `final_grade`: transcript display value such as `5`, `P`, or `F`.
- `grade_points`: numeric 0-5 or 0-4 value used by pass/fail and optional GPA.
- `passed`: boolean pass outcome.
- `grade_breakdown`: auditable JSON explanation of component inputs, gates, and
  conversions.

## Application Flow

Manual courses:

1. `CourseCompletionService::aggregateManualGrades()` delegates to
   `AggregateCourseOfferingGradesAction`.
2. The action loads the course offering syllabus, registered students,
   assessment components, component details, and entered scores.
3. `GradingCalculatorResolver` chooses the default calculator or
   `metropolia_v1`.
4. The selected calculator returns `GradingResult` per student.
5. Academic records are updated with result fields before finalization.
6. Finalization uses the rule-engine pass outcome when present; otherwise it
   keeps the existing threshold behavior.

Canvas-synced courses:

1. Canvas detail scores continue syncing.
2. If the syllabus uses the default engine, Canvas total grade can keep writing
   the academic-record total as it does today.
3. If the syllabus has a custom grading engine, local score details become the
   source for final grade calculation and Canvas total no longer overwrites the
   rule-engine result.

## Interface Contract

No new route is required in the first slice.

Existing syllabus create/update requests accept optional `grading_scheme` JSON.
Existing student and lecturer APIs continue returning current grade fields. A
new `grade_breakdown` field is not exposed to portals in this slice unless an
existing resource already includes it.

Portal impact is `both` because final grade semantics can affect student course
views and lecturer assessment views, even if the response shape stays stable.

## Data Model

Migration:

- Add nullable JSON `grading_scheme` to `syllabus_templates`.

No new table is required for the first slice because schemes are versioned with
the syllabus template. `academic_records.grade_breakdown` stores calculation
evidence per student and course.

## UI / Platform Impact

The first slice is backend/configuration only. Academic operators can seed or
import `grading_scheme` per database. A later story can add a guarded syllabus
UI editor.

## Observability

Every custom calculation writes `academic_records.grade_breakdown` with:

- engine name.
- scheme version.
- component percentages and points.
- requirement gate results.
- converted component grades.
- final rounding mode.

Existing model audit logging records academic-record updates.

## Alternatives Considered

1. Hardcode Metropolia rules by unit name or course code. Rejected because this
   would be brittle across school databases and syllabus versions.
2. Build a full visual rule-builder. Rejected for the first slice because it is
   larger than needed to prove correctness.
3. Add a small JSON-backed engine per syllabus. Accepted because it preserves
   the current default path and supports school-specific databases without
   introducing tenant coupling.
