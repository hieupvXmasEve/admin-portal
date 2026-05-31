# Design

## Domain Model

Add `student_action_logs.egc_defer_from_block_number` as an explicit nullable
audit snapshot.

Rules:

- Allowed values are `1` and `2`.
- The value is required when a new `ACADEMIC_DEFER` action is recorded for a
  student whose current status is `intake_pre_uni_gc`.
- The value remains null for major-program defer records.
- Existing EGC defer records are identified by
  `action_type = ACADEMIC_DEFER` and
  `previous_status = intake_pre_uni_gc`.
- Existing identified records with no value are backfilled to `1`.

The new field intentionally does not reference `egc_blocks`. The audit log owns
the report snapshot, while EGC block rows continue to serve finance and
progression workflows.

## Application Flow

1. The migration adds the nullable column, index, and deterministic historical
   backfill.
2. The student-action create dialog shows a Block 1/Block 2 dropdown only for
   EGC `ACADEMIC_DEFER`.
3. `StoreStudentActionRequest` validates the field and
   `RecordStudentActionAction` persists it.
4. The import mapper accepts an EGC defer block column. Blank legacy import
   values default to Block 1 so the import boundary follows the historical
   backfill policy.
5. The student-action detail page displays the source block and allows staff to
   correct it for EGC defer records.
6. `UpdateStudentActionRequest` and `UpdateStudentActionAction` validate and
   persist corrections.
7. The audit report uses `from_semester_id` and
   `egc_defer_from_block_number`; list and export share the same query builder.

## Interface Contract

Routes remain unchanged:

- `GET /reports/student-actions`
- `GET /reports/student-actions/export`
- `POST /students/{student}/actions`
- `PUT /reports/student-actions/{actionLog}`
- Existing student-action import routes.

New request/filter field:

```text
egc_defer_from_block_number: nullable integer in [1, 2]
```

The audit page changes the visible filter label and key:

```text
Semester (semester_id) -> From Semester (from_semester_id)
```

The existing generic `semester_id` query support may remain internally for
backward compatibility, but the report page and export path use the narrower
source-semester contract.

## Data Model

Add a migration for:

```text
student_action_logs.egc_defer_from_block_number nullable unsigned tiny integer
index(student_action_logs.egc_defer_from_block_number)
```

The migration backfills null values to `1` where:

```text
action_type = 'ACADEMIC_DEFER'
previous_status = 'intake_pre_uni_gc'
```

No finance tables, defer-case tables, or EGC block tables change.

## UI / Platform Impact

Browser surfaces:

- Student-action audit report: source semester and source block filters.
- Student-action audit table: EGC source-block column.
- Student action create dialog: required EGC source-block dropdown.
- Student action history/detail: display source block.
- Student action detail edit dialog: correction dropdown.
- Student action import template/preview: EGC source-block metadata.

No mobile, API, queue, or deployment behavior changes.

## Observability

The existing student-action audit log remains the observability surface.
Migration verification must count:

- EGC defer logs with Block 1.
- EGC defer logs with Block 2.
- EGC defer logs still missing a source block.
- Major-program defer logs incorrectly populated with an EGC source block.

## Alternatives Considered

1. Infer Block 1/Block 2 from `egc_blocks`. Rejected because runtime block
   numbering is not a stable audit snapshot and historical inference is
   incomplete.
2. Add the value only to `defer_cases`. Rejected because older action logs do
   not all have defer cases and reporting is action-log based.
3. Change finance semantics together with reporting. Rejected because staff
   confirmed tuition is already handled and this request is reporting-only.
