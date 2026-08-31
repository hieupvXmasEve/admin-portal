# Correctable GPA After Post-Finalize Grade Change

**Date**: 2026-08-24 08:58
**Severity**: High
**Component**: Academic / GPA finalization
**Status**: Resolved (prod campaign pending)

## What Happened

Executed plan `plans/260814-1359-gpa-reopen-on-post-finalize-grade-change/` end-to-end via /ak:cook, phases in order 3→1→2→4→6→5. 5 commits shipped to `dev`, unpushed. Phase 5 (prod correction campaign) is operational-only — runbook written, not executed.

Proven case: AUS19927 (student 506) SPRING2026 — stored 80.328/81.064 vs correct 80.737/81.293. Finalized on placeholder 100.00 values before grading complete; nightly Canvas sync then delivered correct lower grades with `causer_id=NULL`, leaving snapshot permanently wrong with no re-finalize path.

## The Brutal Truth

The root bug is that `FinalizeSemesterGpaAction` used `create()` — a UNIQUE constraint on `(student_id, semester_id)` made re-finalize structurally impossible. We shipped a finalization workflow that assumed grades were stable at finalization time and then gave Canvas nightly sync write access to those same grade fields. That's a broken contract that's been silently producing wrong transcripts. AUS19927 is the one we caught; the divergence audit (GPA-001) will surface more.

The `completion_status`-vs-`is_passed` bug (phase 6) was a whole separate landmine: transcript pass/fail, resit eligibility, and credit progress were all reading the wrong field. That fix surfaced 213 HUB-001 structural errors and affected `CourseStatisticsService`, `CreditProgressService`, `CurriculumService` beyond the 4 sites the plan enumerated — grep kept finding more. User approved fixing all.

## Technical Details

Commits (all on `dev`, unpushed as of session end):

- `8801ea8b1` — `FinalizeSemesterGpaAction` now `updateOrCreate`; new `app/Shared/Support/Academic/GpaValueComparator.php` (3-dp normalization shared by finalize guard, page badge, audit — agree by construction); `is_current` claimed only for latest finalized semester; `gpa_refinalized` activity log; `PreviewSemesterGpaAction` adds stored-vs-recompute divergence; `Index.vue` badge + banner
- `1596571cd` — `FinalizeGpaRequest`/`PreviewGpaFinalizationRequest` `authorize()` reject cross-campus `campus_id`; `RecalculateApplyController` stops echoing raw exception
- `7e1b466fa` — `CanvasGradeSyncService` emits `finalized_semester_grade_changed` activity with origin in properties; command reports semesters needing re-finalize; freeze rejected (sync delivers correct data)
- `00e9ff0a1` — `AcademicRecord::isPassed/isFailed`, resit writer, `TranscriptEntry` accessor read `is_passed`; `FixFailedRecordEarnedCreditsCommand` zeroes `credit_points_earned`+`credit_hours_earned` on 9 failed rows (dry-run gated); dev dry-run matched the 9 plan-named rows exactly
- `714689e37` — plan sync + `plans/reports/gpa-correction-campaign-runbook.md`

Test run: `tests/Feature/Academic/Gpa` — 50 files green; phase-6 sweep 98 passed.

Also resolved a pre-existing `git stash pop` conflict (4 files: `.gitignore`, `Semesters/Index.vue`, `student-applications/index.vue`, `skills-lock.json`) unrelated to GPA — all resolved to HEAD, stash changes already landed via earlier commits.

## What We Tried / Rejected

- **Mark-dirty / hook approach** (original design): withdrawn — overloaded `is_finalized`, collided with the UNIQUE index. Divergence is compared at read time via `GpaValueComparator`, not watched via a dirty flag.
- **Freeze Canvas sync for finalized semesters**: rejected on evidence — sync delivers correct data; freezing would permanently lock in the wrong values.

## Root Cause Analysis

Two distinct failures compounded:

1. `create()` instead of `updateOrCreate` in finalize action — made correction structurally impossible once a row existed. No one caught this because re-finalization was never a tested path.
2. Premature finalization workflow (finalized on placeholder 100.00 before grading complete) + Canvas sync writing to the same fields post-finalize with no event/alert. System trusted its own snapshot was final while async processes were still mutating inputs.

Secondary: `completion_status` treated as pass/fail signal across 6+ sites when `is_passed` is the contract field. The field existed; it was just ignored everywhere that mattered.

## Lessons Learned

- **Shared comparator, not duplicated logic**: `GpaValueComparator` ensures finalize guard, page badge, and GPA-001 audit agree by construction. A cross-check test asserts the page divergent-set == audit set. Should have done this from day one; differing rounding in three places would have produced different "diverged" results.
- **Review found real bugs**: decimal:2 credit fields vs 3-dp compare would have caused perpetual re-finalize (round recomputed credits to 2dp). Code reviewer subagent caught this before merge.
- **grep before you scope**: plan enumerated 4 `completion_status` sites; grep found 3 more in services. Scope creep was correct — fixing 4 and leaving 3 broken would have been a silent half-fix.
- **Test fixtures encode the old contract**: resit tests and 2 module helpers had `completion_status` + grade_points encoding pass. Had to correct those — real contract change, not test weakening.
- **Runbook before deploy**: prod correction campaign (fix-command `--commit` + re-finalize diverged semesters + verify GPA-001/HUB-001=0) is written and gated. Don't execute blind.

## Next Steps

- [ ] Deploy `dev` → prod (standard pipeline) — **owner: hieupv**
- [ ] Run `FixFailedRecordEarnedCreditsCommand --commit` on prod, verify 9 rows — **owner: hieupv**
- [ ] Re-finalize all semesters flagged by GPA divergence audit (GPA-001 set) — **owner: hieupv**, follow `plans/reports/gpa-correction-campaign-runbook.md`
- [ ] Verify GPA-001 count = 0 and HUB-001 count = 0 post-campaign
- [ ] Check whether other students beyond AUS19927 had Canvas sync post-finalize (GPA-001 will surface them)
