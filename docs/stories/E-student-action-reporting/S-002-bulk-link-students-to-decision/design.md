# Design

## Domain Model

The feature links existing `student_action_logs` records of a selected
`action_type` to an existing `student_decisions` record through
`student_action_logs.decision_id`.

Student identity input is by `students.student_id` code. Codes are normalized
from pasted text by splitting on whitespace, commas, semicolons, and new lines.
Duplicate codes in one paste are collapsed for matching and surfaced in preview
counts.

## Application Flow

1. Staff opens `/reports/student-decisions/{studentDecision}`.
2. Staff opens the bulk add dialog and pastes student codes.
3. Staff chooses the action type that corresponds to the decision.
4. Preview request validates the action type and codes and returns:
   - matched students in current campus scope,
   - unmatched codes,
   - linkable action logs of the selected type for each matched student,
   - skipped students with reasons.
5. Staff confirms.
6. Bulk link action updates only the preview-valid action log ids to the target
   decision inside a transaction.
7. Decision detail is refreshed with linked action/student counts.

## Interface Contract

Add web routes under existing Academic module routes:

- `POST reports.student-decisions.students.preview`
- `POST reports.student-decisions.students.bulk-link`

Both routes keep existing `auth`, `verified`, and `can:change_student_status`
middleware expectations. Requests use FormRequest validation and require
`action_type` plus `student_codes`.

The UI uses Inertia form submission from the existing decision detail page and
does not introduce student or lecturer API contracts.

## Data Model

No migration is planned. The write path updates only the existing nullable
`student_action_logs.decision_id` column.

Campus scope is enforced through the current `session('current_campus_id')`
when matching students and eligible action logs.

Action-type scope is enforced in both preview and confirm so a decision for
major enrollment cannot be linked to an unrelated dropout, defer, or transfer
action log.

## UI / Platform Impact

The decision detail page gains a compact bulk add dialog:

- textarea for pasted student codes,
- action type select,
- preview table grouped by student,
- unmatched/skipped summary,
- confirm button disabled until there are valid linkable action logs.

## Observability

Bulk confirmation logs the decision id, matched code count, linked action log
count, skipped count, and acting user id.

## Alternatives Considered

1. Link by student ids only without preview. Rejected because staff needs to
   verify the student list before a bulk audit-record mutation.
2. Create new action logs from the decision page. Rejected because the existing
   product contract says decisions link to existing action logs.
