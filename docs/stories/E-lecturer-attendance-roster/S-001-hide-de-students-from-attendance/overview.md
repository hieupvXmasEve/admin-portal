# Overview

## Status

Superseded by `S-002-show-inactive-students-in-roster`.

The active-roster counting and attendance-marking guard from this story remain
current. The visibility rule changed after user review: inactive/DE students
must remain visible to lecturers in a separate inactive section instead of
being omitted from the UI.

## Current Behavior

Students can enter an EGC or Major class and later receive a DE academic status
such as defer or dropout. After that status change, the lecturer-facing
attendance roster may still include the student because the class enrollment
query does not consistently exclude inactive/deferred/dropped students.

## Target Behavior

After a student is marked DE, the student is inactive for every EGC and Major
class roster. Lecturer attendance screens and lecturer course-student views do
not include that student in active attendance counts or marking actions.

## Affected Users

- Lecturer: sees only active students when taking attendance.
- Lecturer: after `S-002`, also sees inactive students in a separate locked
  section for roster control.
- Academic/admin staff: DE status remains the source of truth for roster
  exclusion.

## Affected Product Docs

- `docs/portal-repos.md`
- `docs/api/lecturer/`
- `routes/api/v1/lecturer.php`
- `FE/lecturer-nuxt/AGENTS.md`

## Portal Impact

lecturer

## Non-Goals

- Do not delete historical enrollment, attendance, grade, or registration
  records.
- Do not remove students from admin/staff reports.
- Do not change student portal finance or academic APIs.
- Do not introduce Khuym, ClaudeKit, or other agent workflow systems in FE
  repos.

## Implementation Notes

- Active class roster eligibility is centralized on `Student`,
  `CourseRegistration`, and `CourseOffering` helpers.
- Lecturer attendance/session/course/dashboard payloads now compute visible
  rosters and counts from active class roster registrations.
- `FE/student-nuxt/.agent` was removed; both FE repos now rely on the shared
  Swinx Harness guidance rather than local agent workflow packs.
