# Design

## Domain Model

Expose a `grade_display` object derived from `AcademicRecord`:

```json
{
  "scheme_engine": "metropolia_v1",
  "scale": "numeric_0_5",
  "final_label": "5",
  "final_numeric": 5,
  "pass_status": "passed",
  "components": [
    {
      "code": "EXAM",
      "label": "Exam",
      "raw_percentage": 88,
      "converted_grade": 5,
      "requirement_status": "passed"
    }
  ]
}
```

For default weighted grading, `scheme_engine` is `default_weighted_percentage`
and existing percentage fields remain present.

## Application Flow

1. Backend presenter reads `academic_records.grade_breakdown`.
2. Presenter returns only display-safe keys.
3. Student grade endpoints include `grade_display` beside existing fields.
4. Lecturer grade matrix/report endpoints include scheme metadata and final
   display labels.
5. Student and lecturer portal types consume the new optional keys.
6. Portal components prefer `grade_display.final_label` when present and keep
   current display for older API payloads.

## Interface Contract

Student API:

- `/api/v1/student/grades`
- `/api/v1/student/grades/course/{courseOfferingId}`
- `/api/v1/student/course/available/{courseOfferingId}`

Lecturer API:

- `/api/v1/lecturer/courses/{courseOffering}/assessments/report/grade-matrix`
- `/api/v1/lecturer/courses/{courseOffering}/assessments/report/statistics`

## Data Model

No migration. This story reads `academic_records.grade_breakdown`.

## UI / Platform Impact

Student portal:

- `FE/student-nuxt/shared/types/grade.ts`
- `FE/student-nuxt/shared/types/course.ts`
- `FE/student-nuxt/app/components/grade/CurriculumUnitCard.vue`
- `FE/student-nuxt/app/components/course-enrolled/CourseGrades.vue`

Lecturer portal:

- `FE/lecturer-nuxt/shared/types/grade-matrix.ts`
- `FE/lecturer-nuxt/app/components/course/GradeBookTab.vue`
- `FE/lecturer-nuxt/app/components/grade/GradeTable.vue`

## Observability

API tests snapshot the new optional contract. No new log stream is required.

## Alternatives Considered

1. Expose raw `grade_breakdown` directly.
2. Keep portals percentage-only.
3. Add separate endpoints for Metropolia courses.

The selected design keeps one backwards-compatible contract and limits exposed
data to display-safe fields.
