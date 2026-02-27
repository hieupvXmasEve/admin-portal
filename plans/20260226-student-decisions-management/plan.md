---
title: "Student Decisions Registry + Action Linking"
description: "Plan add standalone decision registry linked to student_action_logs, with linked-student/action views and decision file preview."
status: in-progress
priority: P1
effort: 18h
branch: dev
tags: [feature, academic, backend, frontend, reports]
created: 2026-02-26
---

# Student Decisions Registry Plan

## Overview
Build one standalone decision registry table (not tied directly to student), then link decisions to `student_action_logs` via nullable `decision_id` (manual assignment). Support viewing how many students/actions are linked to a decision, open action detail via `reports/student-actions/{id}`, and open decision file in new tab.

## Scope
In scope:
- New table `student_decisions` (registry only).
- Add nullable `decision_id` FK on `student_action_logs`.
- `decision_id` trên action log là optional; user gán thủ công khi cần.
- New report page to manage decisions and inspect linked action/student list.
- Sidebar menu item under `Student Status`, below `Lifecycle Yearly Analysis`.

Out of scope:
- Full workflow engine for decision approval.
- Removing existing legacy `decision_number`, `decision_signer`, `decision_signed_at` in this phase.
- Bulk import/export for decision registry.

## Assumptions
- Keep backward compatibility with current action logs by not dropping legacy fields now.
- Decision file is represented by `upload_record_id` and URL resolved from `UploadRecord` model/accessor.
- Linked list counts by action log rows; student count uses distinct `student_id`.

## Context Links
- [Academic web routes](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/routes/web.php)
- [Student action detail route usage](/Users/hunt2412/hieupvdev/project/swinx/resources/js/utils/routes.ts)
- [StudentStatus menu group](/Users/hunt2412/hieupvdev/project/swinx/resources/js/constants/menu-sidebar.ts)
- [StudentActionLog model](/Users/hunt2412/hieupvdev/project/swinx/app/Models/StudentActionLog.php)
- [UploadRecord model](/Users/hunt2412/hieupvdev/project/swinx/app/Models/UploadRecord.php)

## Data Contract (MVP)
Table `student_decisions`:
- `id`
- `decision_name` (required)
- `decision_number` (required)
- `decision_signer` (required)
- `issued_at` (required)
- `expires_at` (nullable)
- `upload_record_id` (nullable FK -> `upload_records.id`, `nullOnDelete`)
- `changed_by_user_id` (FK -> `users.id`)
- timestamps

`student_action_logs` extension:
- add `decision_id` (nullable FK -> `student_decisions.id`, `nullOnDelete`)
- relation `StudentActionLog::decision()`

## Business Rules
- `decision_id` có thể null cho mọi action type.
- User tự chọn/gán quyết định thủ công khi cần liên kết.
- Decision registry page shows:
  - total linked actions
  - total linked students (distinct)
  - linked action list with student info
  - action detail link to `reports/student-actions/{id}`
  - decision file link open new browser tab

## Phase Status / Progress
| Phase | Focus | Status | Effort | Link |
|---|---|---|---:|---|
| 01 | Schema + relations + compatibility | completed | 5h | [phase-01](/Users/hunt2412/hieupvdev/project/swinx/plans/20260226-student-decisions-management/phase-01-schema-and-domain-model.md) |
| 02 | Backend contracts + rule enforcement | completed | 6h | [phase-02](/Users/hunt2412/hieupvdev/project/swinx/plans/20260226-student-decisions-management/phase-02-backend-web-contracts.md) |
| 03 | Frontend registry page + linked list + menu | completed | 5h | [phase-03](/Users/hunt2412/hieupvdev/project/swinx/plans/20260226-student-decisions-management/phase-03-frontend-page-and-menu.md) |
| 04 | Tests + rollout checks | pending | 2h | [phase-04](/Users/hunt2412/hieupvdev/project/swinx/plans/20260226-student-decisions-management/phase-04-tests-and-rollout.md) |

Current progress: 3/4 phases complete (75%). Remaining: Phase 04 tests + rollout checks.

## Definition of Done
- Decision registry CRUD (list/create/update) works.
- `student_action_logs` can link to decision via `decision_id`.
- `decision_id` nullable hoạt động đúng cho toàn bộ action type.
- Decision detail view shows linked students/actions with deep link to action detail route.
- Decision file can be opened in new tab from decision list/detail.
- Menu item appears at requested position.

## Unresolved Questions
None.
