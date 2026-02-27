# Phase 01 - Schema and Domain Model

## Context links
- [StudentActionLog model](/Users/hunt2412/hieupvdev/project/swinx/app/Models/StudentActionLog.php)
- [DeferCase migration FK pattern](/Users/hunt2412/hieupvdev/project/swinx/database/migrations/2026_01_17_100004_create_defer_cases_table.php)
- [UploadRecord model](/Users/hunt2412/hieupvdev/project/swinx/app/Models/UploadRecord.php)

## Overview (date/priority/status)
- Date: 2026-02-26
- Priority: P1
- Status: completed (100%)

## Key Insights
- Current `student_action_logs` already stores legacy decision fields; safest path is additive link field (`decision_id`).
- Decision registry must be independent from student entity.
- FK `nullOnDelete` is consistent with existing upload and optional relations.

## Requirements
- Create `student_decisions` table (no `student_id`).
- Add `decision_id` nullable FK to `student_action_logs`.
- Add model relations:
  - `StudentDecision::actionLogs()`
  - `StudentActionLog::decision()`

## Architecture
- Additive schema change only, no destructive migration.
- Keep legacy decision fields for backward compatibility and staged migration.

## Related code files
- Create: [database/migrations](/Users/hunt2412/hieupvdev/project/swinx/database/migrations)
- Update: [app/Models/StudentActionLog.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/StudentActionLog.php)
- Create: [app/Models/StudentDecision.php](/Users/hunt2412/hieupvdev/project/swinx/app/Models/StudentDecision.php)

## Implementation Steps
1. Create migration `create_student_decisions_table` with required fields and indexes (`decision_number`, `issued_at`).
2. Create migration `add_decision_id_to_student_action_logs_table` with nullable FK + index.
3. Add `StudentDecision` model fillable/casts/relations.
4. Update `StudentActionLog` fillable + relation for `decision_id`.
5. Decide compatibility note: if `decision_id` present, UI prefers registry data.

## Todo list
- [x] New registry table created.
- [x] FK link added on action logs.
- [x] Model relations complete.
- [x] Backward-compatibility policy documented.

## Success Criteria
- Decision registry stores all required decision metadata.
- Action log can optionally link one decision.
- Existing action history screens do not break after migration.

## Risk Assessment
- Risk: data inconsistency between legacy fields and linked decision.
- Mitigation: define precedence rule and keep sync handling in write actions.

## Security Considerations
- Strict FK validation for `upload_record_id` and `decision_id`.
- Avoid mass-assigning unauthorized actor fields.

## Next steps
- Implement request/action/controller rule enforcement.

## Unresolved questions
None.
