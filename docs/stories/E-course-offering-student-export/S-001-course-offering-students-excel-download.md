# S-001 Course Offering Students Excel Download

## Status

planned

## Lane

normal

## Product Contract

Staff users who can view a course offering can download an Excel workbook of the students currently registered in that course offering from the Course Offering Students tab.

## Relevant Product Docs

- `docs/project-overview-pdr.md`
- `docs/code-standards.md`
- `docs/design-guidelines.md`
- `docs/inertiajs-vue-info.md`

## Acceptance Criteria

- The Course Offering Students tab exposes a Download Excel action when staff are viewing a course offering.
- The download route is authenticated, verified, campus-scoped, and uses the existing `view_course_offering` permission.
- The exported workbook includes course metadata and one row per registration with student identity, registration status, retake attempt details, registration date, and registration method.
- The export must not mutate registrations, academic records, or course offering state.
- The UI uses existing route helpers, button primitives, and lucide icons.

## Design Notes

- Commands: none.
- Queries: reuse the course offering registration relation with eager-loaded student and academic-record attempt metadata.
- API: add a GET web download route under `course-offerings/{courseOffering}`.
- Tables: no schema changes expected.
- Domain rules: export only the selected course offering in the active campus context.
- UI surfaces: `resources/js/pages/course-offerings/components/tabs/StudentsTab.vue`.

## Validation

| Layer | Expected proof |
| --- | --- |
| Unit | Export mapping is covered through a feature test using `Excel::fake()`. |
| Integration | Feature test confirms authorized download route dispatches an Excel export for a campus-owned course offering. |
| E2E | Not required for this slice. |
| Platform | Targeted frontend lint/format checks on the touched Students tab. |
| Release | Targeted Pint, route listing, and diff checks. |

## Harness Delta

No harness changes planned.

## Evidence

Add commands, reports, screenshots, or links after validation exists.
