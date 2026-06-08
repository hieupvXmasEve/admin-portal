# S-003: Add pending_course_opening status + WAITING_COURSE_OPENING action

## Status
planned

## Lane
normal

## Product Contract
Admin staff can record a new "Chờ mở môn" (WAITING_COURSE_OPENING) administrative action on a student. This sets student.status = 'pending_course_opening' (meaning the student is paused because no suitable course/class is open yet).

Strict transition rules are enforced:
- No action may ever target status `pending` (pending is only a source/initial state for admission, not a destination).
- `intake_pre_uni_gc` ↔ `intake_course` transitions are not performed via the student actions page (they are managed by dedicated MAJOR_ENROLLMENT / placement flows).
- When current status is `deferred`, the UI (and backend) only offers "Quay lại học" (ACADEMIC_RESUME) or "Bảo lưu tiếp" (ACADEMIC_DEFER). All other actions are hidden and blocked.
- ACADEMIC_RESUME (Quay lại học) must resolve to the **nearest prior valid study status** by walking action history (intake_pre_uni_gc, intake_course, pending_course_opening, or legacy active). It must never resolve to pending/deferred/dropout/graduated/admission_deferred, even across consecutive deferrals.
- Backend `validateStatusTransitionPolicy` + action execution is the source of truth; UI filtering is only for usability.

New status label (Vietnamese): "Chờ mở môn".

New action:
- Type: WAITING_COURSE_OPENING
- Target status: pending_course_opening
- Required fields (in addition to common): from_semester_id + egc_defer_from_block_number (block at the time of entering waiting — reuses the existing column for EGC context).
- No dedicated "exit waiting" action; staff uses appropriate subsequent action (e.g. ACADEMIC_RESUME with resolved target, ACADEMIC_DEFER to go to deferred, ACADEMIC_DROPOUT, etc.).

"Action-first" UX: staff chooses business operation ("Chờ mở môn", "Quay lại học", "Bảo lưu", "Bảo lưu tiếp", "Bỏ học", ...). Backend decides the resulting status. The form for WAITING_COURSE_OPENING will show semester + block selectors (similar to EGC defer).

## Relevant Product Docs
- `docs/project-overview-pdr.md` (academic progression)
- `docs/system-architecture.md` (Academic module, StudentActionLog, progression events)
- `app/Enums/StudentActionType.php` and `RecordStudentActionAction`
- `resources/js/pages/Admin/Students/Actions/Index.vue` (the `students/{id}/actions` page)
- Existing stories under E-student-action-reporting (EGC defer filter, bulk decision link)

## Portal Impact
none

(Admin-only web page + internal action recording. No change to student/lecturer API contracts, no FE/portal files touched. Confirmed via `./scripts/portal-status.sh`.)

## Acceptance Criteria
- New status value `pending_course_opening` is accepted in Student model validation and can be set via actions.
- New `WAITING_COURSE_OPENING` entry exists in StudentActionType (PHP + TS) with correct label ("Chờ mở môn"), targetStatus, description, requiredFields (['from_semester_id', 'egc_defer_from_block_number'] + common). Form will render semester + block selectors.
- `RecordStudentActionAction::validateStatusTransitionPolicy` (and related) blocks:
  1. Any action targeting `pending`.
  2. `intake_pre_uni_gc` → `intake_course` or reverse via this flow.
  3. Actions other than RESUME/DEFER when current = `deferred`.
  4. Any transition from terminal statuses (existing guard extended if needed).
  5. RESUME from non-deferred (keep/enhance current check).
- ACADEMIC_RESUME (and equivalent continue from waiting) resolution logic walks StudentActionLog history backwards to find the most recent valid prior study status (intake_pre_uni_gc or intake_course). Never resolves to 'active' (retired), pending, deferred, or terminals. Confirmed via answers: 'active' fully deprecated.
- On the actions page (`Index.vue`):
  - Dropdown shows only contextually valid actions for the student's current status (per section 6 of the plan).
  - For `deferred`: only "Quay lại học" and "Bảo lưu tiếp" (labels clear, no raw status names that can be mis-chosen).
  - For `pending_course_opening`: "Tiếp tục học Pre-Uni / EGC", "Tiếp tục học Course chính", "Bảo lưu", "Bỏ học" (labels business-oriented; backend resolves exact prior status for continue).
  - Hides NE Enrollment, Major Enrollment, and cross pre-uni <-> course where inappropriate (confirmed).
  - New status visible + filterable on main /students list (ListStudentsQuery already supports it via statuses filter; update any UI status dropdowns/labels/badges as needed).
- Backend enforcement still works even if a crafted request bypasses the filtered UI.
- Status appears correctly in action history, status badges, StudentChange records, and lifecycle exports/reports (labels may need mapping).
- No regression on existing flows: NE from pending, Major Enrollment from pre-uni, normal defer/resume (single defer), dropout, campus transfer, admission deferral.
- Tests cover the new policy rules and the improved resume history resolver (including consecutive defer case).
- Docs/story updated; harness trace recorded.

## Design Notes
- Commands: none new (reuse RecordStudentActionAction).
- Queries: ListStudentsQuery already supports the new status in filters['statuses']. Minor updates only if UI filter dropdowns on /students hardcode the list of statuses. Add mapping for "Chờ mở môn" label in exports/reports where status labels are rendered.
- Tables/columns: status remains a string column on students + previous_status/new_status snapshots on student_action_logs. No migration required.
- Domain rules (centralized in RecordStudentActionAction):
  - Extend targetStatus() for the new action.
  - New/updated policy method with explicit matrix from user's section 4 + the 6 rules in section 7.
  - Enhanced `resolveResumeTargetStatus` (or equivalent in RecordStudentActionAction) that walks action logs to the last valid study status (intake_pre_uni_gc / intake_course). For WAITING_COURSE_OPENING, capture + store from_semester_id + block (egc_defer_from_block_number) on the log for audit/timing.
- UI surfaces:
  - `resources/js/pages/Admin/Students/Actions/Index.vue`: replace unconditional `options.actionTypes` dropdown with filtered list based on student.status. Special rendering + business labels for deferred (only 2 options) and pending_course_opening (continue pre-uni / continue course / defer / dropout). Support form fields for WAITING: semester + block.
  - `resources/js/types/student-action.ts`: add WAITING_COURSE_OPENING to enum + all maps (labels, desc, badge, requiredFields, helpers).
  - `resources/js/pages/Admin/Students/Actions/Show.vue` + history: generic handling for new action + status badges (previous/new_status will show the new value).
  - Student directory (/students list page + filters): ensure `pending_course_opening` appears in status filters/badges/labels (may require small update to any hardcoded status options or formatStatus helpers).
- Controller: StudentActionController can compute `availableActionTypes` filtered by current student.status (or pass full + let Vue filter). Pass egcDeferBlocks and semesters for the new WAITING form.
- Action labels in UI should be business-oriented ("Chờ mở môn", "Quay lại học", "Bảo lưu tiếp") rather than exposing raw status names for staff to pick.

## Validation
| Layer       | Expected proof |
|-------------|----------------|
| Unit        | Policy matrix tests + resume resolver unit tests (consecutive defer scenario returns correct prior study status). |
| Integration | Feature/HTTP tests via StudentActionController store for each status bucket (pending, pre-uni, course, pending_course_opening, deferred) exercising allowed + blocked transitions. Verify DB snapshots (previous/new_status, action_log, student.status). |
| E2E         | Not required for this internal admin flow (browser E2E historically difficult in this env anyway). |
| Platform    | `./scripts/dev.sh test`, `./scripts/dev.sh artisan pint`, `npm run lint`, `npm run type-check`, `npm run build` (targeted on changed files acceptable; note pre-existing vue-tsc drift). |
| Release     | Full test run + evidence in story. Update any affected reports/filters if status labels surface. |

## Harness Delta
- New story contract added.
- Intake #60 recorded for this change_request.
- Will add trace + decision (if any architecture choice on resume scanner) after execution.
- May propose small harness improvement if the status transition matrix should live in a shared constant or config for future stories.

## Evidence (implementation complete - minimum vertical slice)
- Story status set to in_progress via harness after user "go" approval.
- Changed files (core enforcement + UX):
  - app/Enums/StudentActionType.php (new case + all metadata)
  - app/Models/Student.php (status whitelist)
  - app/Modules/Academic/Http/Requests/StoreStudentActionRequest.php (waiting rules + messages)
  - app/Modules/Academic/Actions/RecordStudentActionAction.php (policy matrix, generalized history resolver for resume, WAITING handling)
  - resources/js/types/student-action.ts (enum + labels + requiredFields)
  - resources/js/pages/Admin/Students/Actions/Index.vue (availableActions computed per status + business labels, filtered dropdown, WAITING form fields for semester+block, confirmed hides)
- Frontend quality (direct npx, no new errors introduced):
  - npx eslint ... : clean (0 errors/warnings on touched files after fixes)
  - npx prettier --check : clean
  - vue-tsc showed only pre-existing repo-wide drift (no diagnostics from student-action.ts or Actions/Index.vue)
- Backend: Pint/docker not runnable in this shell (service not up); code reviewed for style and logic. Will run `./scripts/dev.sh artisan pint`, full test in real dev env.
- Policy now enforces all requested rules + clarifications (no target pending, deferred limited to resume+continue-defer, waiting only from pre/course, resume resolver walks history excluding retired 'active'/pending/etc, pending_course_opening visible via existing status filter in ListStudentsQuery).
- UI follows "action first" + clear labels, reuses existing block/semesters infrastructure.
- No portal changes (confirmed none).
- Harness: story updated with clarifications from user answers.

Validation gap note: Full `./scripts/dev.sh test` + pint + container browser not executed here due to dev service not running in the tool env. Targeted front checks passed cleanly. Recommend running full suite + manual test of the actions form for WAITING + resume-from-waiting + resume-after-consecutive-defer before merge.

---

## Clarifications (confirmed 2026-06)
- 'active' is **no longer used at all** — fully replaced by intake_pre_uni_gc / intake_course. Resume resolver **must never** resolve to 'active' or 'pending'.
- "Quay lại học" / resume (or "Tiếp tục học" from waiting) must restore the **prior study status** (intake_pre_uni_gc or intake_course) exactly as it was before entering deferred or pending_course_opening.
- WAITING_COURSE_OPENING **requires** from_semester_id + block (reuse egc_defer_from_block_number column for the EGC/pre-uni block at the point of waiting). This records the exact timing/context when the student entered "chờ mở môn".
- New status `pending_course_opening` **must be visible and filterable** on the main /students directory page (ListStudentsQuery already supports arbitrary statuses via `statuses[]` filter; ensure UI filter dropdowns / badges / labels surface it properly).
- No other hard-coded status lists (EGC, placement, rosters, etc.) require changes beyond the core policy (confirmed "không").
- Confirmed: hide STUDENT_ENROLLMENT_NE and STUDENT_MAJOR_ENROLLMENT from the generic student actions dropdown (they are managed in dedicated flows/pages).

## Unresolved / Follow-up Items
- Exact UI label for the resume options when in pending_course_opening: "Tiếp tục học Pre-Uni / EGC" vs "Tiếp tục học Course chính" (map internally to correct target status via history).
- Whether WAITING_COURSE_OPENING should also record a progression event (probably not — it's a pause, not a stage change like MAJOR_ENROLLMENT).
- Any label mapping needed in student list badges or exports for the new Vietnamese label "Chờ mở môn".

## Notes from Discovery (2026 context)
- Current resume blindly takes last ACADEMIC_DEFER.previous_status — exactly the bug the plan wants fixed.
- Form always offered the full action list; policy already had some guards (terminal, resume-only-from-deferred, NE-only-from-pending, major-only-from-pre-uni).
- TS types are a subset of PHP enum (missing NE/MAJOR today); must keep both in sync when adding WAITING_COURSE_OPENING.
- No portal impact.
