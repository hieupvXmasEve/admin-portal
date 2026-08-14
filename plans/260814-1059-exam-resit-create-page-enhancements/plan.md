# Plan: /exam-resit/create page enhancements

## Status
- Phase: single-phase, DONE
- Branch: dev
- Revision: v2 — business rule relaxed per user decision (see below)

## Post-implementation review resolution (2026-08-14)
Code review found 1 CRITICAL bug (fixed) + 2 discovered risks (resolved by user decision), beyond the original plan:
- **CRITICAL, fixed**: `CreateExamResitAttemptAction` still rejected `failure_reason === null` after the query stopped filtering on it — list/submit-gate parity broken for legacy un-backfilled records (~75% of failed records on dev DB). Removed the null check; added parity tests (list ↔ action) for `manual_failed` and `null` failure_reason.
- **Cross-lane double-charge risk** (a non-grade-only failed record is now eligible in both exam-resit and course-retake lanes, no guard against registering both): user decision = leave as-is, staff coordinates manually. No code change.
- **Attendance bypass at resit completion** (`CompleteExamResitAttemptAction` decides pass/fail on grade score alone, no attendance re-check — pre-existing code, newly reachable for attendance-failed records): user decision = intentional, confirmed. "Đã cho phép thi lại thì không quan tâm attendance nữa, attendance chỉ để tham khảo." No code change; docblock updated to state this as confirmed product decision, not a caveat.
- Stale docblocks in `ListRetakeCourseEligibleStudentsQuery`, `CreateRetakeCourseRegistrationAction`, `FailureReasonClassifier` corrected to stop asserting the old grade-only/attendance-recorded routing rule.
- Deferred, not fixed (noted only): `ListExamResitBlockedStudentsQuery` duplicates `scopeEligibleRecords`'s base-pool where-clauses (~5 lines) — matches this codebase's existing "mirrors the gate" duplication pattern elsewhere; `semester_id`/`search`/`unit_id` filters accepted but unused by both queries (pre-existing, not introduced here).

Final: 135/135 targeted tests passing (`./scripts/dev.sh test --filter=ExamResit`).

## Outcome
Staff on `/exam-resit/create` can:
1. See row numbers (STT) in the failed-students list.
2. See attendance % and failure-reason label per record.
3. See a small table of duplicate/blocked records (already passed elsewhere, or already has a pending resit) — these stay hidden since they're true duplicates, not a grade/attendance judgment call.
4. Read an inline notice explaining current logic.

## Business rule change (user-confirmed 2026-08-14)
Original `ListExamResitEligibleStudentsQuery` restricted the list to `failure_reason = grade_failed` only, with attendance-recorded as a hard requirement (attendance/both/manual failures were routed to a separate "học lại" retake flow). **User explicitly requested this restriction removed**: ALL students with a finalized failed record should be visible; staff decides who to register, including attendance-failed/both-failed/manual-failed/attendance-not-recorded cases.

Confirmed scope (both questions answered):
- Relax applies to **both list AND submit gate** — `CreateExamResitAttemptAction::assertRecordCanEnterExamResit` must also stop rejecting `attendance_failed`/`both_failed` failure_reason and the attendance-not-recorded check, so a record shown in the list can actually be registered.
- Dedupe exclusions stay: `unit_already_passed` (passed via another record for same unit) and `resit_already_in_flight` (already has a pending/consumed `ExamResitAttempt` for this exact record) are true duplicates, not a grade/attendance judgment call — keep excluded from the main list, shown separately in the small blocked-table.

Known side effect to flag in report (not blocking, user already decided): this creates overlap with the separate course-retake ("học lại") flow (`ListRetakeCourseEligibleStudentsQuery`) — an attendance-failed record can now appear in both flows simultaneously. Not in scope to reconcile; noted for awareness only.

## Root definition of "blocked" (small secondary table)
Base pool = finalized failed record for a student in scope: `is_passed=false AND completion_status != 'in_progress' AND grade_status='final' AND (override_pass=false OR null)`. A record from this pool is blocked (excluded from the main list) for exactly one of:
1. unit already passed elsewhere for this student (`is_passed=true` or `override_pass=true` on another record, same unit) → reason `unit_already_passed`
2. student already has an in-flight/consumed `ExamResitAttempt` for this exact academic_record → reason `resit_already_in_flight`

Everything else in the base pool (regardless of `failure_reason` or attendance state) goes into the main list.

## Files

### Backend
- **Edit**: `app/Modules/Academic/Delivery/Queries/ListExamResitEligibleStudentsQuery.php`
  - `scopeEligibleRecords()`: remove `->where('failure_reason', AcademicRecord::FAILURE_GRADE_FAILED)` and the `total_not_recorded` null/0 condition.
  - Remove the `attendanceIsRecorded()` post-filter call in `handle()` (delete the method, no longer used).
  - Keep: `is_passed=false`, `completion_status != in_progress`, `grade_status=final`, `override_pass` false/null, unit-passed-elsewhere dedupe, in-flight-attempt dedupe.
  - Add `failure_reason` and `failure_reason_snapshot` to what's implicitly available on `failed_record` (already full AcademicRecord model — no query change needed, just consumed differently in Vue).
  - Update class docblock: no longer "grade-only failures"; now "all finalized failed records except duplicates."
- **New**: `app/Modules/Academic/Delivery/Queries/ListExamResitBlockedStudentsQuery.php`
  - Same shape as the eligible query but returns only the two dedupe cases, each tagged with `reason_code` (`unit_already_passed` | `resit_already_in_flight`) and a Vietnamese `reason_label` (inline `match()`, no new shared class — YAGNI, single consumer).
  - `handle(array $filters): Collection<array{student, failed_record, unit, reason_code, reason_label}>`
- **Edit**: `app/Modules/Academic/Delivery/Actions/CreateExamResitAttemptAction.php`
  - In `assertRecordCanEnterExamResit()`: delete the `failure_reason` attendance/both rejection block (lines ~155-162) and the attendance-not-recorded rejection block (lines ~164-169). Keep: student/campus match, is_passed/override_pass check, in_progress/grade_status check, `failure_reason === null` check (data-integrity, not the business restriction), in-flight-attempt dedupe check.
  - Update method docblock/comment to drop the "route to retake" language.
- **Edit**: `app/Modules/Academic/Delivery/Http/Web/ExamResitAttemptController.php`
  - Inject `ListExamResitBlockedStudentsQuery $blockedQuery` in constructor.
  - In `create()`: call with same filters array as the main query, add `blocked_students` and `total_blocked` to the Inertia payload.

### Frontend
- **Edit**: `resources/js/pages/Academic/ExamResit/Create.vue`
  - Extend `EligibleEntry.failed_record` type: add `failure_reason: string`, `failure_reason_snapshot: { attendance_pct: number | null; attendance_threshold?: number } | null`.
  - Add `BlockedEntry` interface: `{ student, failed_record: { id, unit_id, final_percentage, reason_code, reason_label }, unit }`.
  - Add props: `blocked_students: BlockedEntry[]`, `total_blocked: number`.
  - Main list: add STT (`v-for="(entry, index) in eligible_students"`, `{{ index + 1 }}`), attendance % (from `failure_reason_snapshot.attendance_pct`, guard null), and a small failure-reason badge (map `grade_failed`/`attendance_failed`/`both_failed`/`manual_failed` → Vietnamese label inline in a computed helper).
  - New `<Card>` below the existing grid (full width) titled "Bản ghi trùng lặp / đã xử lý" with same row style as main list (no checkbox), showing STT, student, unit, `reason_label`.
  - Add `Alert` (shadcn, existing import pattern from `Academic/Warnings/Index.vue`) above the grid: explain the list shows all sinh viên có bản ghi trượt đã chốt điểm (mọi lý do trượt), trừ các bản ghi trùng lặp (đã pass môn ở nơi khác, hoặc đã có đăng ký thi lại đang xử lý) — staff tự xem lý do trượt và quyết định đăng ký.

## Steps
1. Relax `ListExamResitEligibleStudentsQuery::scopeEligibleRecords()` + remove `attendanceIsRecorded()`.
2. Relax `CreateExamResitAttemptAction::assertRecordCanEnterExamResit()` (remove the 2 rejection blocks).
3. Write `ListExamResitBlockedStudentsQuery` (dedupe-only: unit_already_passed, resit_already_in_flight).
4. Wire `blocked_students`/`total_blocked` into `ExamResitAttemptController::create()`.
5. Update `Create.vue`: STT, attendance % + failure-reason badge, blocked-records table, notice Alert.
6. Update/extend feature tests:
   - `tests/Feature/Academic/ExamResit/*` — assert `create()` now lists attendance_failed/both_failed/manual_failed records too; assert unit_already_passed/resit_already_in_flight still excluded and appear in `blocked_students`.
   - Assert `store`/`storeBulk` now succeed for a previously-rejected attendance_failed record; still reject duplicate/in-flight.
   - Search existing tests asserting the OLD rejection messages ("phải đi luồng học lại", "chưa ghi nhận đủ") — update or remove, they now assert wrong behavior.
7. Run targeted PHP tests (`./scripts/dev.sh test --filter=ExamResit`). ✅ 135/135 passing.
8. Manual verification: confirm AUH15442 (unit_id=51, `both_failed`) now appears in the main eligible list per the new rule. ✅ Verified via tinker: `record_id=4791 reason=both_failed` returned by `ListExamResitEligibleStudentsQuery`.

## Acceptance Criteria
- [x] Main list shows 1-based STT per row.
- [x] Main list shows attendance % and failure-reason label per row.
- [x] Main list includes attendance_failed/both_failed/manual_failed/not-recorded-attendance records (previously hidden).
- [x] Small blocked-table shows only unit_already_passed / resit_already_in_flight records.
- [x] Notice/Alert visible explaining the (relaxed) current logic.
- [x] Submitting a previously-rejected attendance_failed record via `store`/`storeBulk` now succeeds (assuming syllabus/catalog pricing exists). Verified by test.
- [x] Duplicate/in-flight records still rejected at submit.
- [x] `php artisan test --filter=ExamResit` green (135/135, including updated assertions).

## Risks
- Existing tests likely assert the old rejection messages for attendance_failed/both_failed/not-recorded — these WILL need updating, not just new tests added. Search before editing to avoid missing one.
- `BulkCreateExamResitAttemptsAction` per-item try/catch still applies — no change needed there, but its "failed" reasons list will now differ (fewer entries) for previously-blocked-by-attendance batches.
- Blocked query re-runs a similar per-student N+1 loop as the main query (existing pattern) — acceptable, same filter-bounded scope.
- `failure_reason_snapshot` nullable JSON — guard `?? null` in Vue.
- Overlap with `ListRetakeCourseEligibleStudentsQuery` (course-retake flow) is a known, accepted side effect — not reconciled in this change.

## Rollback
Revert the 3 backend file changes (2 edits + 1 new file) + Create.vue edit + test changes; no migration, no schema change. Public contract change: `store`/`storeBulk` now accept previously-rejected records — flag this as a behavior change in the commit message, not silent.
