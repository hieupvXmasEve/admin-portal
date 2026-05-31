# Design

## Status

Superseded by `S-002-show-inactive-students-in-roster`.

This design remains relevant for active-roster eligibility, counts, and
attendance-marking guards. The UI/API visibility decision changed: inactive
students are returned with roster metadata and rendered separately, not omitted.

## Domain Model

The existing student status is the source of truth for DE state. A class roster
entry remains historical data, but it must be treated as inactive when the
related student is in a defer/dropout/DE state.

The filter must apply to both EGC and Major course offerings because both are
served through the same lecturer attendance/course APIs.

## Application Flow

1. Lecturer opens a class session or course roster.
2. Backend resolves the session/course offering.
3. Backend identifies registrations/enrollments whose related student is
   DE/inactive.
4. Lecturer portal receives active students as markable and inactive students
   as non-markable roster rows.

## Interface Contract

Affected lecturer API surfaces include:

- `GET /api/v1/lecturer/attendance/sessions/{session}`
- `POST /api/v1/lecturer/attendance/sessions/{session}/mark`
- `POST /api/v1/lecturer/attendance/bulk-mark`
- `GET /api/v1/lecturer/courses/{courseOffering}/students`
- any lecturer course/session detail payload that embeds attendance roster
  students.

The original contract was omission. `S-002` replaced that with explicit roster
metadata: `is_roster_active`, `roster_status`, `roster_status_label`, and
`can_mark_attendance`.

## Data Model

No migration should be required unless discovery shows there is no stable DE
status field. The implementation must avoid destructive updates.

## UI / Platform Impact

`FE/lecturer-nuxt` should continue to render the same attendance screens. If the
frontend has local filtering or assumptions that reintroduce inactive students,
update the affected composables/types/pages.

`FE/student-nuxt/.agent` is an obsolete local agent workflow pack and should be
removed so the FE repos follow the shared Swinx Harness workflow.

## Observability

No new logs are expected. Tests should prove DE students are omitted while
active students remain visible and markable.

## Alternatives Considered

1. Mark DE students as disabled in the lecturer UI. Initially rejected, then
   accepted by `S-002` after user review required lecturers to keep class
   control visibility.
2. Delete or mutate enrollment records. Rejected because historical enrollment
   and attendance evidence must remain intact.
