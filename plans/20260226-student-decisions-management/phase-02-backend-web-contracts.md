# Phase 02 - Backend Web Contracts

## Context links
- [Academic routes](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/routes/web.php)
- [StudentActionController](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Web/Admin/StudentActionController.php)
- [StoreStudentActionRequest](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Requests/StoreStudentActionRequest.php)
- [UpdateStudentActionRequest](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Requests/UpdateStudentActionRequest.php)

## Overview (date/priority/status)
- Date: 2026-02-26
- Priority: P1
- Status: completed (100%)

## Key Insights
- Existing action write flow already centralized in request + action classes, ideal for injecting `decision_id` rule.
- Reports layer can host decision registry endpoints with existing permission strategy.

## Requirements
- Add report routes for decision registry:
  - list/create/update decisions
  - decision detail with linked action logs/students
- Extend student action write contracts to accept nullable `decision_id`.

## Architecture
- New `StudentDecisionController` for registry screens.
- New query for decision list/detail stats and linked rows.
- Extend existing `RecordStudentActionAction` and `UpdateStudentActionAction` to persist `decision_id`.

## Related code files
- Update: [app/Modules/Academic/routes/web.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/routes/web.php)
- Create: [app/Modules/Academic/Http/Web/Admin/StudentDecisionController.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Web/Admin/StudentDecisionController.php)
- Create: [app/Modules/Academic/Queries/ListStudentDecisionsQuery.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Queries/ListStudentDecisionsQuery.php)
- Create: [app/Modules/Academic/Queries/GetStudentDecisionDetailQuery.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Queries/GetStudentDecisionDetailQuery.php)
- Update: [app/Modules/Academic/Http/Requests/StoreStudentActionRequest.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Requests/StoreStudentActionRequest.php)
- Update: [app/Modules/Academic/Http/Requests/UpdateStudentActionRequest.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Requests/UpdateStudentActionRequest.php)
- Update: [app/Modules/Academic/Actions/RecordStudentActionAction.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Actions/RecordStudentActionAction.php)
- Update: [app/Modules/Academic/Actions/UpdateStudentActionAction.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Actions/UpdateStudentActionAction.php)

## Implementation Steps
1. Add registry routes under `reports/student-decisions` with `view_student_action`/`change_student_status` middleware.
2. Add request classes for create/update decision registry.
3. Build query to return decision rows + aggregated counts:
   - `linked_actions_count`
   - `linked_students_count` (distinct student)
4. Build decision detail payload containing linked action rows (student + action type + created_at).
5. Add `decision_id` into action store/update validation and write path.
6. Ensure create/update action flow persists nullable `decision_id` without required-by-type validation.

## Todo list
- [x] Registry routes/controllers complete.
- [x] Decision list/detail query complete.
- [x] Action write flow supports `decision_id`.
- [x] Nullable decision linking behavior verified.

## Success Criteria
- Decision registry API/web payload includes linked counts and linked rows.
- Action detail links can be generated from linked row IDs.
- All action types can be saved with `decision_id = null`.

## Risk Assessment
- Risk: manual linking may be skipped in operations.
- Mitigation: show clear UI indicator when action chưa link quyết định.

## Security Considerations
- Validate `decision_id` exists and accessible.
- Preserve campus scoping where applicable on linked student/action data.

## Next steps
- Build frontend screens and menu placement.

## Unresolved questions
None.
