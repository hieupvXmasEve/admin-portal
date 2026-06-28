# Hub: enable Graduation tab + real Export action

Status: ready-for-human

## Parent

`.scratch/student-hub/PRD.md`

## What to build

Finish the two half-built pieces of the Hub, end-to-end.

End-to-end behavior:

- **Graduation tab:** enable the currently-commented Graduation view and show graduation requirements and progress toward the degree for the Student.
- **Export action:** make the context-bar Export quick action real — produce an academic-summary export for the Student. The dead "coming soon" toast is removed.

## Acceptance criteria

- [x] The Graduation tab is visible and shows requirements + progress toward the degree.
- [x] The Export action produces an academic-summary export (file download); the "coming soon" behavior is gone.
- [x] Feature tests cover the Graduation Query data contract and the export output, following prior art GPA/summary queries and the existing Excel exports.

## Blocked by

- `.scratch/student-hub/issues/01-hub-shell-overview-and-role-aware-rendering.md`

## Comments

### Progress (implemented via `/implement`)

All three acceptance criteria met. The page, component, controller `graduation()` method, route, and `getGraduationData()` service data already existed from prior work; this slice enabled and corrected them, and built the Export end-to-end.

- **Graduation tab enabled.** Added the `graduation` entry to the data-driven `STUDENT_HUB_TABS` registry (`hub-tabs.ts`) — the shell renders it with no shell edits (issue 01's extension point). Placed after Attendance, before the finance tabs, mirroring the PRD tab order. Aligned `Graduation.vue`'s `student` prop to `StudentHubContext` (what the controller passes) and dropped an unused param in `GraduationTab.vue`.
- **Load-bearing bug fix.** `getGraduationTracker` computed earned credits with `->where('grade_status', 'passing')`, but `'passing'` is **not** a member of the `academic_records.grade_status` enum (`in_progress, provisional, final, incomplete, withdrawn, failed, pass_no_credit, audit, transfer_credit`) — so the filter matched **zero** rows and "progress toward the degree" was permanently 0. Changed to `->where('is_passed', true)`, mirroring the service's own `getCreditPointSnapshots` earned-credit semantics. Without this the Graduation AC ("shows progress") was unachievable.
- **Export made real.** New `StudentAcademicSummaryExport` presenter (`app/Modules/Academic/Exports/`) — a pure formatter that lays out identity, cumulative GPA + standing, graduation progress, the requirements breakdown, and a transcript. New `export()` controller action + `students.academic-summary.export` route (gated `can:view_student_summary`), with data gathered by a new thin `StudentAcademicSummaryService::getAcademicSummaryExportData()` (keeps the controller a thin orchestrator, matching the per-tab read methods). The context-bar Export quick action now navigates to that route (real `.xlsx` download); the dead "coming soon" toast + its `vue-sonner` import are gone.
- **Tests** (Query/export seams): `StudentHubGraduationTest` (graduation contract by value — credits/requirements/readiness/risk/timeline + student isolation + Hub render + permission-forbidden) and `StudentAcademicSummaryExportTest` (export workbook output by value + `Excel::fake()` download with filename/content + permission-forbidden). All green (9 tests / 59 assertions); the 21-test Hub-related suite stays green (no regression).

**Caveats (pre-existing, out of scope):**
- `CurriculumUnitFactory`'s `core()`/`elective()`/`major()` states write `is_compulsory`, which is not a column on `curriculum_units` (the real column is `is_required`) — any test using those states fails. Tests here set `type` directly to avoid it. Spun off as a separate task.
- The whole-suite / whole-`tests/Feature/Academic` `--compact` runs still abort (exit 255) on the pre-existing `GetStudentAttendanceQueryTest` `class_sessions` CHECK-constraint; verification was per-file. Whole-project `vue-tsc` was not run (OOMs in the dev container) — relied on per-file ESLint (clean for the touched feature files).
