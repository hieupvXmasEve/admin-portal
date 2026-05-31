# S-001 Lecturer GPA Report Design

## Domain Model

Lecturer GPA is a reporting read model derived from course-scoped survey
targets:

- A course offering belongs to one semester, campus, unit, and lecturer.
- A survey target with `scope_type = course` and `scope_id = course_offering.id`
  represents survey results for that class section.
- Lecturer GPA is one lecturer-level average for a selected semester and campus.
- Courses taught are the distinct unit codes for the lecturer in the selected
  semester.

Assumption for implementation: "Lecturer Evaluation" means rating questions in
survey sections whose title contains `Lecturer Evaluation`. If the live survey
template uses a different stable marker, implementation must pause and adjust
the selector before shipping.

The GPA formula is class-level and equal-weighted:

```text
1. For each course offering survey target, calculate class Lecturer Evaluation
   score = average(answer_number)
   where answer question is a rating question
   and question's section is Lecturer Evaluation.

2. For each lecturer, calculate GPA = average(class Lecturer Evaluation score)
   across all selected-semester course offerings taught by that lecturer.
```

This means a lecturer teaching AU001.1, AU001.2, AU002.1, and AU002.2 gets one
GPA from the four class-level Lecturer Evaluation scores.

## Application Flow

- Add a read-only query class for the lecturer GPA report.
- Add a FormRequest to validate `semester_id`, `search`, `sort`,
  `direction`, `page`, and `per_page`.
- Add a controller endpoint that resolves the active semester by default,
  calls the query, and returns an Inertia page.
- Add an export endpoint that reuses the same query contract without
  pagination.
- Add a small Excel export class for the report rows.

## Interface Contract

Routes:

- `GET /lectures/lecturers-GPA`
- `GET /lectures/lecturers-GPA/export`

Proposed route names:

- `lectures.lecturers-gpa.index`
- `lectures.lecturers-gpa.export`

Page props:

- `rows`: paginated Lecturer GPA rows.
- `filters`: normalized filter/sort/pagination state.
- `semesters`: semester choices.
- `active_semester`: selected semester metadata.

Row contract:

- `lecturer_id`
- `lecturer_name`
- `email_account`
- `employee_id`
- `type`
- `courses`
- `gpa`
- `evaluated_classes_count`
- `responses_count`

## Data Model

No schema change. The query reads:

- `lectures`
- `course_offerings`
- `units`
- `form_targets`
- `student_form_assignments`
- `responses`
- `answers`
- `questions`
- `form_sections`
- `semesters`

## UI / Platform Impact

- New Inertia page under `resources/js/pages/lectures`.
- Use existing table, pagination, filter controls, route helpers, and lucide
  icons.
- Existing Survey Results aggregate page gets a compact link to the new report.

## Observability

- Export should log user id, selected semester, campus id, row count, and file
  name.
- No raw answer text or student identity should be logged.

## Alternatives Considered

1. Use the aggregate page's overall average for each class.
   Rejected because it mixes non-lecturer survey questions into Lecturer GPA.
2. Store denormalized lecturer GPA.
   Rejected for this slice because no schema change is needed and the report can
   be computed from existing survey answer data.
