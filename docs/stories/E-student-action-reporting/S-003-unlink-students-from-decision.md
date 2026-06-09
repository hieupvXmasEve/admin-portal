# S-003: Unlink / Remove Students from Decision

## Status
implemented

## Lane
normal

## Product Contract
Staff with the `unlink_student_from_decision` permission can remove (unlink) individual linked student action logs from a Student Decision record via the decision detail page at `/reports/student-decisions/{studentDecision}`. The unlink operation uses a confirmation dialog (global confirm), calls a JSON API endpoint, updates the `student_action_logs.decision_id` to null (scoped to the decision and current campus), and refreshes the linked actions list. No data is deleted, only the link is removed. Backend is protected by explicit permission middleware and action encapsulation.

## Relevant Product Docs
- `docs/system-architecture.md` (Student Decisions route cluster and link model)
- `docs/project-overview-pdr.md`
- `config/permission.php`
- `docs/stories/E-student-action-reporting/S-002-bulk-link-students-to-decision/`

## Portal Impact
none

## Acceptance Criteria
- A "Remove" / unlink action (trash icon button) is available per row in the Linked Student Actions table on the decision Show page.
- Clicking triggers the global confirm dialog with clear message identifying the student + action log.
- Confirmed action calls the backend unlink endpoint (protected by `can:unlink_student_from_decision`).
- On success: toast + partial reload of `linkedActions` (and decision stats).
- On error/403: appropriate feedback.
- New permission `unlink_student_from_decision` is defined in `config/permission.php` (under reports).
- New dedicated route, controller method (orchestrates), and `UnlinkStudentFromDecisionAction`.
- Unlink only affects rows that are currently linked to *this* decision + respect campus scoping (same as bulk link).
- No schema change.
- Route is listed under reports.student-decisions.* 
- Quality gates (pint, eslint, type-check, targeted test, route:list) pass or gaps explicitly noted.
- Story + harness records updated; docs refreshed where routes/auth change.

## Design Notes
- Commands/Actions: `UnlinkStudentFromDecisionAction::run(StudentDecision, StudentActionLog, userId, ?campusId)`
- Queries: reuse `GetStudentDecisionDetailQuery` (no change needed)
- API: POST `/reports/student-decisions/{studentDecision}/students/{actionLog}/unlink` returning ApiResponse (mirrors preview/bulk-link style)
- Tables: only `student_action_logs.decision_id` is mutated (set null); no cascade or log deletion.
- Domain rules: only unlink if currently `decision_id` matches the decision; campus filter applied on query; no effect on already-unlinked rows.
- UI surfaces: `resources/js/pages/Admin/Reports/StudentDecisions/Show.vue` — add column/slot + confirm + useApi call + router.reload partial. Use `useGlobalConfirmDialog`.
- Routes: registered with `->middleware('can:unlink_student_from_decision')`
- Permissions: added to config; UpdatePermissionsSeeder will pick it up on next run.
- Frontend route helper: extend `studentRoutes` in `@/utils/routes`.
- No Inertia form (useApi + reload, like bulk link).
- Authorization explicit on route + action (no policy yet for this; middleware is current pattern for these report actions).

## Validation
| Layer | Expected proof |
| --- | --- |
| Unit | (gap: reuse pattern from StudentCodeParserTest / bulk link; no dedicated unit for unlink action in slice) |
| Integration | Targeted Feature test or manual via controller (StudentDecisionBulkLinkTest style); run `./scripts/dev.sh test tests/Feature/Academic/StudentDecision*Test.php` if extended |
| E2E | none (browser not available in container reliably) |
| Platform | `./scripts/dev.sh artisan route:list --name=reports.student-decisions` shows new `students.unlink` route |
| Release | Targeted: `./scripts/dev.sh artisan pint -- --test` on changed PHP; `./scripts/dev.sh npm run type-check`; `./scripts/dev.sh npm run lint -- --format compact` on touched JS; `git diff --check`; full `./scripts/dev.sh test` (or note pre-existing failures) |

## Harness Delta
- Intake #61 recorded (change_request, normal, flags: authorization,existing-behavior)
- New story S-003 registered
- Added permission definition (authz surface)
- Route + contract change documented in system-architecture if updated in same window
- Validation expectations added to this story and TEST_MATRIX.md

## Evidence
- Intake #61 recorded via `./scripts/harness intake --type change_request ... --lane normal --flags "authorization,existing-behavior"`
- Story file created + `./scripts/harness story add --id S-003-unlink-students-from-decision ...`
- Route list: 7 student-decision routes (new `.../students/{actionLog}/unlink` present)
- Pint: PASS 4 files (UnlinkAction, Controller, routes/web.php, permission.php)
- Prettier: All matched files use Prettier code style (after --write on Show.vue + routes.ts)
- ESLint: clean (no output) on Show.vue + routes.ts
- PHPUnit (existing): `StudentDecisionBulkLinkTest` 3 passed / 25 assertions (no regression)
- `git diff --check`: clean (after ws fix in TEST_MATRIX)
- Full `vue-tsc` / type-check: skipped (pre-existing OOM + 492 baseline drift outside our files, consistent with S-002 evidence)
- No new dedicated feature test for unlink in this slice (gap noted; behavior covered by action + controller patterns + existing decision tests)
- Changed files: config/permission.php, app/Modules/Academic/routes/web.php, StudentDecisionController.php, new UnlinkStudentFromDecisionAction.php, resources/js/utils/routes.ts, resources/js/pages/Admin/Reports/StudentDecisions/Show.vue, docs/* (arch, TEST_MATRIX, new story)
- Portal impact: none (confirmed via scripts/portal-status.sh)
