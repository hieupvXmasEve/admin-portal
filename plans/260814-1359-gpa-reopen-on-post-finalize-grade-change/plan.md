---
title: "Correctable GPA after post-finalize grade change"
description: "Make re-finalize possible in place, surface stored-vs-recomputed divergence on the finalize page, and close the campus authorization hole on GPA endpoints"
status: pending
priority: P1
effort: "1.5d"
tags: [academic, gpa, data-integrity, security]
created: 2026-08-14
---

# Correctable GPA after post-finalize grade change

> **Design revised 2026-08-14 after red team.** The original hook-based
> "mark-dirty" design (brainstorm option C) was withdrawn: it overloaded
> `is_finalized` as a staleness marker, which four read paths treat as
> "does not exist", and its recovery step collided with a live unique index.
> Staleness is now **derived** rather than stored. See `## Red Team Review`.

## Overview

Grades can change AFTER semester GPA is finalized (regrade/phúc khảo, manual correction, resit, Canvas re-pull + Recalculate). The finalized `gpa_calculations` snapshot is never refreshed and there is no signal it went stale.

Proven case, AUS19927 (student id=506), SPRING2026 — stored 80.328/81.064 vs recomputed 80.737/81.293. Exact cause established from `activity_log` on `AcademicRecord` 4336 (offering 122, 1.00 credit):

| When | final_percentage | Actor |
|---|---|---|
| 2026-04-27 22:19 | 0.00 → 69.50 | user 220 |
| **2026-05-12 00:14** | **GPA finalized** (qp 1124.590 → 80.328) | registrar |
| 2026-05-13 03:05 | 69.50 → 71.37 | **null (automated)** |
| 2026-05-21 14:56 | 71.37 → 75.23 | user 220 |

Current value 75.23 yields qp 1130.320 → 80.737. Arithmetic closes exactly at both ends, so the premise is confirmed: grades changed twice after finalize, once by an unattributed automated process.

**Corrected evidence (superseded claim):** an earlier draft of this plan blamed a 2026-07-20 mass `transcript_entries` update. That date is the `transcript_entries` backfill (table created 2026-07-18 by `2026_07_18_233921_create_transcript_entries_table.php`; GPA source switched from `academic_records` to `TranscriptEntryGpaReader` in commit `bc52a9a16`, 2026-07-19), so `transcript_entries.updated_at > gpa_calculations.finalized_at` is true for every pre-cutover row by construction and proves nothing. Both sources agree today (`academic_records` and `transcript_entries` each give qp 1130.320 / 80.737 for this student+semester), so the projection is faithful here. Consequence: **the "138 rows at risk" figure was derived from that bogus proxy and is void.** Real scale: 262 rows total, all `is_finalized=true`, 139 `is_current=true`. Actual damage must be measured by recompute-and-compare, never by timestamp.

### Measured scale (dev, `academic:audit-progression-reconciliation --all`, 2026-08-14)

AUS19927 is 1 of 66. Of 139 current `gpa_calculations` rows, **73 (student, semester) pairs diverge** from their transcript-derived values — 68 of them on `semester_gpa` and `cumulative_gpa` directly:

| Measure | Value |
|---|---|
| Distinct students with wrong GPA | 66 |
| Diverged (student, semester) pairs | 73 |
| Stored **too high** (student favoured) | 62 of 68 |
| Stored **too low** (student penalised, incl. AUS19927) | 6 of 68 |
| Max divergence | 4.848 GPA points (student 570, sem 1: shown 70.013, correct 65.165) |
| Median divergence | 0.850 |
| Cases ≥ 1.0 point | 31 |
| `academic_standing` divergences | **0** — no student crosses the 50.0 threshold either way |
| Concentration | 61 of 68 in semester 2 |

Two consequences. First, the direction is the opposite of the case that started this investigation: most students are currently shown a *better* GPA than their grades justify, so correcting them lowers 62 students' published numbers. Second, `academic_standing` is unaffected in every case, so no student's standing classification changes.

Separately the same audit reports **275 `missing_persisted_calculation`** rows (semesters with transcript data but no `gpa_calculations` row at all — never finalized: 106 in sem 1, 68 in sem 2, 101 in sem 3) and 213 `student_hub_consumer_evidence_differs_from_transcript`. Both are out of scope here and unquantified as to cause.

### Solution: derived staleness, correctable snapshot

Four defects and one data backlog, five phases, no new state and no migration:

1. **Re-finalize is currently impossible.** `FinalizeSemesterGpaAction:71-77` skips any student with an `is_finalized=true` row, and its write path is `create()` against a live `UNIQUE(student_id, semester_id)` — so bypassing the guard would throw a duplicate-key error and abort the whole campus batch. Fix: compare-and-update in place, guard on *values already matching* instead of *row already exists*.
2. **Divergence is invisible.** `PreviewSemesterGpaAction:59` already recomputes live semester and cumulative GPA for every student on the finalize page. Show the stored snapshot beside it and flag the difference. No hook, no marker column, no migration — and it covers every change shape (edited grade, new retake attempt, late-graded offering, removed record) because it compares outcomes rather than watching writes.
3. **The GPA endpoints have no campus authorization.** Pre-existing, unrelated to staleness, merged here by user decision because phase 2 touches the same surfaces and would otherwise widen the exposed payload.
4. **Changes to finalized semesters are unattributable.** `academic-records:sync` runs at 03:00 and writes into already-finalized semesters with `causer_id = NULL` (`SyncAcademicRecordsCommand.php:50-63`, `routes/console.php:49-53`) — this is the process that changed AUS19927's grade at 2026-05-13 03:05, one day after finalize, with no recorded actor. Fix: make it attributable and reportable, **not** blocked — see "Root operational cause" below.

### Root operational cause: premature finalize, not sneaky grades

Traced the five worst divergences through `activity_log`. All share one shape: at finalize (2026-05-12) the records held placeholder values — frequently exactly `100.00` — and the nightly sync then delivered the real, lower grades.

| Student / sem | At finalize | Nightly sync 05-13 03:0x (causer NULL) | Human 05-21 14:56 (causer 220) |
|---|---|---|---|
| 570 / 1 | 86.18 | → 47.40 | — |
| 519 / 2 | 100.00 | → 79.75 | → 47.85 |
| 622 / 2 | 100.00 | → 74.12 | → 53.68 |
| 515 / 2 | 81.25 | → 79.00 | → 47.40 |
| 561 / 2 | 100.00 | → 76.00 | → 66.40 |

A further unattributed batch at 2026-06-02 15:20:18 (12 rows) flipped `is_passed` true→false on FALL2025 records, explaining the 11-12 `*_credit_points_earned` divergences.

So GPA was finalized **before grading was complete**, and the system had no way to correct it afterwards. This is why the freeze option was withdrawn: freezing finalized semesters would have preserved the placeholder `100.00` grades as the permanent official record. The sync is delivering correct data; the defects are premature finalize (operational, still open) and no re-finalize path (Phase 1).

Proactive/cross-semester detection needs no new code: `academic:audit-progression-reconciliation` already compares stored `gpa_calculations` against transcript-derived values across 10 fields and reports divergence as invariant `GPA-001`. Verified on this case — for student 506 it reports `semester_quality_points` expected 1130.32 vs actual 1124.590 and `cumulative_quality_points` expected 2032.33 vs actual 2026.600.

### Key evidence

- `UNIQUE(student_id, semester_id)` is live: `database/migrations/2025_12_30_121141_refactor_gpa_calculations_table.php:58`, confirmed against the running schema.
- Skip guard: `app/Actions/Academic/FinalizeSemesterGpaAction.php:71-77`; insert path `:101`; student-wide `is_current` clear with no semester predicate `:97-99`.
- Live recompute on the finalize page: `app/Actions/Academic/PreviewSemesterGpaAction.php:59` → `CalculateCumulativeGpaAction`. The page never references `is_finalized`.
- Existing audit: `app/Console/Commands/Academic/AuditAcademicProgressionReconciliationCommand.php` → `GetAcademicProgressionReconciliationQuery::reconcilePersistedGpa:187-250`.
- Campus hole: `FinalizeGpaRequest.php:11-13` / `PreviewGpaFinalizationRequest.php:11-13` (`authorize(): true`), `GpaManagementController.php:31,64` (request input overrides session campus). Correct gate exists at `SelectCampusRequest.php:19-23`.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | A finalized semester GPA can be corrected in place, with an audit entry | P1 |
| 2 | Operators can see which finalized rows diverge from current grades | P1 |
| 3 | GPA endpoints reject cross-campus `campus_id` | P1 |
| 4 | Changes to finalized semesters become attributable and reported | P1 |
| 5 | The measured backlog (73 diverged pairs + 5 never-finalized) is corrected | P1 |
| 6 | Pass/fail is read from `is_passed` only, and no failed unit carries earned credits | P1 |

## Non-goals

- No automatic rewriting of official GPA — a registrar clicking Finalize remains the approving act.
- No write-time hook on `transcript_entries`; no staleness marker column; no migration.
- No new audit command — the existing reconciliation command already covers it.
- No change to the GPA formula, the grading engine, or `GetStudentHubGpaSummaryQuery`.
- No cascade re-finalize of later semesters; they surface as divergent on their own page and are corrected the same way.

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Enable safe re-finalize of a diverged semester](./phase-01-enable-safe-refinalize.md) | Done |
| 2 | [Phase 2: Divergence visibility on the GPA finalize page](./phase-02-divergence-visibility-on-finalize-page.md) | Done |
| 3 | [Phase 3: Campus authorization for GPA endpoints](./phase-03-campus-authorization-for-gpa-endpoints.md) | Done |
| 4 | [Phase 4: Alert when automated sync changes a finalized semester](./phase-04-alert-on-sync-changing-finalized-semester.md) | Done |
| 5 | [Phase 5: Correction campaign for the diverged GPA rows](./phase-05-correction-campaign-for-73-diverged-rows.md) | Pending |
| 6 | [Phase 6: Sibling-field drift from the is_passed batch](./phase-06-sibling-field-drift-from-the-is-passed-batch.md) | Done |

**Execution order (decided): 3 → 1 → 2 → 4 → 6 → 5.** Phase numbering follows when each phase was added, not when it runs. Phase 3 ships first because the campus hole is independent of the GPA work, is actively exposing student PII and cross-campus finalize, and Phase 2 would otherwise widen that payload before it is closed. Phase 6 runs immediately before Phase 5 because Phase 5 recomputes `credit_points_earned` from the transcript and Phase 6 is what makes those nine values correct first.

Dependencies: Phase 2 depends on Phase 1 (it calls the `GpaValueComparator` Phase 1 extracts; the badge must predict exactly what Finalize will do). Phases 3, 4 and 6 are independent of 1-2 and of each other. Phase 5 is operational and depends on 1, 2, 4 and 6 — running it before Phase 4 means corrections can silently re-diverge on the next nightly sync, and running it before Phase 6 bakes nine wrong earned-credit values into `gpa_calculations`.

Layer placement (per development-rules — every layer pinned):
- Shared support (new, only new file in the plan): `app/Shared/Support/Academic/GpaValueComparator.php` — matches the `CourseGradeScale.php` precedent in the same directory: pure Academic policy, no record access, consumed by both global Actions and module Queries. No interface, no provider binding.
- Actions: `app/Actions/Academic/FinalizeSemesterGpaAction.php`, `app/Actions/Academic/PreviewSemesterGpaAction.php` (modify existing)
- Query: `app/Modules/Academic/Progression/Queries/GetAcademicProgressionReconciliationQuery.php` (modify: delegate its private `normalize()` to the shared comparator, behaviour-preserving)
- FormRequests: `app/Modules/Academic/Http/Requests/Gpa/FinalizeGpaRequest.php`, `.../PreviewGpaFinalizationRequest.php` (modify existing)
- Controller: `app/Modules/Academic/Delivery/Http/Web/RecalculateApplyController.php` (modify: stop echoing exception text)
- Command: `app/Console/Commands/SyncAcademicRecordsCommand.php` (modify: origin properties on scheduled runs + finalized-semester change counter)
- Support: `app/Modules/Academic/Delivery/Support/CanvasGradeSyncService.php` (modify: emit the finalized-semester change event on the scheduled path only)
- Frontend: `resources/js/pages/Admin/Academic/Gpa/Index.vue` (modify existing)
- Models: `app/Models/GpaCalculation.php`, `app/Models/Semester.php` (pre-existing global, stay put, read-only)
- Phase 6 (`completion_status` semantics + the 9 earned-credit rows): modify `app/Models/AcademicRecord.php`, `app/Modules/Academic/Delivery/Actions/CompleteExamResitAttemptAction.php`, `app/Modules/Academic/Progression/Models/TranscriptEntry.php`; create `app/Console/Commands/Academic/FixFailedRecordEarnedCreditsCommand.php`
- Migration: none. New route: none. New module boundary: none. New source files: two — `GpaValueComparator` and the Phase 6 correction command — plus tests.
- Tests: `tests/Feature/Academic/Gpa/` — `RefinalizeDivergedSemesterTest.php`, `PreviewDivergenceVisibilityTest.php`, `GpaEndpointCampusAuthorizationTest.php`, `FinalizedSemesterSyncAlertTest.php` (+ existing `FinalizeSemesterGpaActionTest.php` extended). Phase 5 writes no code — its artifact goes to `plans/reports/`.

## Success Criteria

- [x] The finalize guard, the finalize-page badge and `academic:audit-progression-reconciliation` all decide "diverged" through the same `GpaValueComparator`, and extracting it leaves the audit's output unchanged.
- [x] Re-running Finalize on a diverged semester updates the existing row in place to the recomputed values, emits one activity entry carrying the prior GPA, and never attempts a duplicate `(student_id, semester_id)` insert.
- [x] Re-running Finalize when nothing changed writes nothing and reports students as skipped (unchanged behavior).
- [x] Re-finalizing an earlier semester does not pull `is_current` back from a later finalized semester; finalizing a later semester still moves it forward.
- [x] The finalize page flags divergent students, and that set equals the `GPA-001` exceptions reported by `academic:audit-progression-reconciliation` for the same scope.
- [x] `Index.vue` no longer claims finalized GPA is unaffected by later grade changes.
- [x] A campus-1-only operator is rejected when passing `campus_id=2` to GPA preview and GPA finalize.
- [ ] AUS19927 SPRING2026 reads 80.737 / 81.293 after re-finalize, and its `GPA-001` exceptions are gone.
- [ ] No production code infers pass/fail from `completion_status`; `HUB-001` drops from 213 to 0; no row has `is_passed = false` with `credit_points_earned > 0`.
- [ ] Focused tests green: `tests/Feature/Academic/Gpa/` (5 new + existing 5 files).

## Open Questions

None. All four remaining questions were resolved by the user on 2026-08-14:

1. **Premature finalize is permitted.** Finalizing early is a legitimate user action and will not be blocked or guarded against — the correct response is that it must be *correctable*, which is exactly what Phase 1 delivers. No completeness check will be added.
2. **No student notification.** Correcting the numbers is sufficient; Phase 5 does not include a notification step or a registrar sign-off gate.
3. **No production check in scope.** Measurements stay dev-only by decision.
4. **213 `student_hub_consumer_evidence_differs_from_transcript` — investigated, see below.** Separate defect from GPA staleness; tracked as its own follow-up, not absorbed here.

### HUB-001 reassessed: not stale data, a field with two meanings → Phase 6

An earlier draft of this section claimed the 213 `HUB-001` exceptions were 213 corrupted `academic_records.completion_status` rows and recommended backfilling them. **That was wrong** and is retracted. Re-investigated 2026-08-15 with a full cross-tab.

`completion_status` values across `academic_records`: `completed` + passed 1912, `completed` + failed **212**, `in_progress` 816, and **`failed` = 0 rows** — the enum permits `failed` but nothing has ever written it. The reason is `CourseCompletionService.php:315`, the primary completion path, which sets `'completion_status' => 'completed'` unconditionally on the same `fill()` as `'is_passed' => $finalPassed`. Under that writer the field means **"finished"**, and pass/fail is carried by `is_passed`. So the 212 rows are internally consistent, not corrupt.

The genuine defect is that three places disagree about what the field means:

| Location | Meaning applied |
|---|---|
| `CourseCompletionService.php:315` | "finished" — always `completed` |
| `CompleteExamResitAttemptAction.php:308` | "passed" — `$isPassed ? 'completed' : 'failed'` |
| `TranscriptEntry.php:54-57` (derived accessor) | "passed" — `is_passed ? 'completed' : 'failed'` |

The audit reports 213 exceptions precisely because the transcript accessor uses one meaning and the records use the other. **User decision (2026-08-15): the field means "finished."** Phase 6 aligns the two dissenting places to that meaning, which removes all 213 exceptions structurally without touching data.

Severity of the earlier claim was also overstated. Re-checked every reader:

| Reader | Real status |
|---|---|
| `AcademicRecord::scopeCompleted()` (`:253`) | **0 callers** — dead code |
| `AcademicRecord::isCompleted()` (`:280`) | 14 callers, **all on Event / EventParticipant**, none on `AcademicRecord` |
| `AcademicRecord::isFailed()` (`:291`) | **0 callers**, and always false since no row carries `'failed'` |
| `AcademicRecord::isPassed()` (`:286`) | **2 real callers** — `ModuleProgressService.php:74`, `ModuleGradeCalculator.php:47`. Returns true for **162** failed records (those with `grade_points > 0`). This is the actual bug. |
| `AiAcademicStudentProfileReader.php:112` | Counts `completed_courses` including failed units — correct under "finished" semantics, though the field name suggests otherwise |

One further sibling of the same 2026-06-02 batch, found while verifying: **9 rows** carry `credit_points_earned > 0` with `is_passed = false` — a failed unit awarded credits, present in both `academic_records` and `transcript_entries`. This one *is* a data error, and it is why Phase 6 must run before Phase 5: Phase 5 recomputes `credit_points_earned` from the transcript and would otherwise write these nine wrong values into `gpa_calculations`. (The 494 rows with `is_passed = true` and `credit_points_earned = 0` were checked and are all 0-credit units — legitimate.)

### Investigations closed (2026-08-14)

- **Divergence direction (62 of 68 stored too high) — explained.** Not a hidden grade-lowering process: GPA was finalized on placeholder values and the nightly sync brought the real, lower grades. Full trace in "Root operational cause" above. `attendance:sync-to-academic-records` ruled out as a source (writes attendance fields only, `SyncAttendanceToAcademicRecords.php:187-193`). The `is_passed` flips came from an unattributed 12-row batch at 2026-06-02 15:20:18, which explains the `*_credit_points_earned` divergences.
- **275 `missing_persisted_calculation` — not serious.** 72 belong to SUMMER2026, the semester currently in progress (`is_active = Y`, 2026-05-04 → 2026-08-31), where absent GPA rows are correct. Closed semesters are missing only 3 (FALL2025) and 2 (SPRING2026) pairs, all four students `status = deferred`, all with complete final records. Cause: the finalize action filtered by current student status until commit `5279ab348` (2026-08-13); the 2026-05-12 run predates the fix. No code needed — a normal Finalize run now picks them up. Folded into Phase 5.

Residual technical risk: the concurrency window (bulk finalize interleaving with a lecturer Recalculate) is mitigated rather than eliminated — divergence resurfaces on the next page load or audit run instead of being silently lost. Accepted by decision, see Validation Log #3 and Phase 1 → Risk Assessment.

## Validation Log

### External confirmation — 2026-08-15

Two claims that no amount of code reading could settle were confirmed by the user
against sources outside this repository. Both hold; no decision changes.

| Claim | Confirmed how | Consequence |
|---|---|---|
| `completion_status` means **"finished"**, not "passed" | User confirmed the intended business meaning | Decision #9 stands. The 212 rows stay untouched; Phase 6 fixes the readers and the two dissenting writers, not the data. |
| Canvas holds **47.4** for unit AU008, student AUS10687 (id 570) | User checked Canvas directly | The nightly sync was delivering the **correct** grade; the stored `86.18` at finalize was the placeholder. Decision #7 (alert, do not freeze) stands — freezing would have preserved the wrong value as the official record. |

This is the first time any figure in this plan has been checked against the
system of record rather than inferred from `activity_log` ordering. It closes the
largest remaining uncertainty: that the whole "premature finalize" reading might
have had the direction backwards. It did not.

Still true: every other number here is measured on dev (`asia`) and no other unit
has been compared against Canvas.

### Session 2 — 2026-08-15

Triggered by a user challenge to a claim in Session 1's write-up, not by a new
verification sweep. The challenge was correct and the claim is retracted.

#### Retraction

Session 1 recorded that the 213 `HUB-001` exceptions were 213 corrupted
`completion_status` rows, and listed five readers as harmed. Re-verification
showed:

- `completion_status` means **"finished"** under its primary writer
  (`CourseCompletionService.php:315` sets it unconditionally beside
  `is_passed`), and **no row anywhere carries `'failed'`** (cross-tab:
  `completed`+passed 1912, `completed`+failed 212, `in_progress` 816,
  `failed` 0). The 212 rows are consistent, not corrupt. **No backfill.**
- Three of the five "harmed readers" were wrong: `scopeCompleted()` and
  `isFailed()` have zero callers, and all 14 `isCompleted()` callers are on
  Event models, not `AcademicRecord`. Only `isPassed()` (2 callers) is a real
  defect, affecting 162 rows.
- Retracting the backfill recommendation also removes the "own plan, not this
  one" framing — the work is now Phase 6 of this plan by user decision.

#### New finding

Verifying the above surfaced a genuine data error the audit had reported only as
`*_credit_points_earned` divergences: **9 rows with `is_passed = false` and
`credit_points_earned > 0`**, in both `academic_records` and `transcript_entries`
(ar 3130, 3159, 3776, 3783, 4338, 4339, 4353, 4361, 4370). The 494 rows with
`is_passed = true` and `credit_points_earned = 0` were checked and are all
0-credit units — legitimate. This is what makes Phase 6 a prerequisite of
Phase 5 rather than an optional follow-up.

#### Decisions

| # | Question | Decision |
|---|---|---|
| 5 | What does `completion_status` mean? | **"Finished", not "passed".** Matches the primary writer. Fix the readers and the two dissenting writers; do not touch the 212 rows. |
| 6 | The 9 failed-but-credited rows | **Correct them before Phase 5**, in both tables, via a command with the repo's `--dry-run`/`--commit` gate. |
| 7 | Packaging | **Merge into this plan as Phase 6** rather than a separate plan. |

#### Propagation

- Added `phase-06-sibling-field-drift-from-the-is-passed-batch.md`.
- plan.md: rewrote the HUB-001 section as a retraction plus corrected analysis; added goal 6; added Phase 6 to the phases table; execution order updated to 3 → 1 → 2 → 4 → 6 → 5 with a note that numbering reflects creation order, not run order; layer placement and new-file count updated (two new source files now); success criteria extended.
- Phase 5: `dependencies` now `[1, 2, 4, 6]`, with a constraint and a todo gate explaining why.

#### Whole-Plan Consistency Sweep

Re-read `plan.md` and all six phase files.

- The retracted "213 corrupted rows / backfill / own plan" language is gone from every file; the only surviving mentions are the explicit retraction and Phase 6's corrected analysis.
- "Failed: 0" from Session 1 still holds — neither Session 1 failure was reopened.
- Execution-order prose, the phases table, and every `dependencies` field agree on 3 → 1 → 2 → 4 → 6 → 5.
- The plan's migration-free property is preserved; Phase 6 deliberately leaves the unused `'failed'` enum value in place rather than adding a migration to drop it.
- Test-count claim reconciled: 5 new test files across the plan, not 3.

No unresolved contradictions remain.

### Session 1 — 2026-08-14

Verification pass scoped per the workflow guard: `## Red Team Review` already carries
file:line evidence for the pre-redesign claims, so this session verified only the
**new** claims introduced when the design changed after red team. No `[UNVERIFIED]`
tags were present.

#### Verification Results
- Claims checked: 14 (new post-redesign claims only)
- Verified: 12 | **Failed: 2** | Unverified: 0
- Tier: Full (5 phases), reduced per the red-team guard
- Both failures resolved by decision in this session; no failures remain open.

| Claim | Result |
|---|---|
| `GetAcademicProgressionReconciliationQuery::normalize` exists and is reusable | **FAILED** — exists at `:899` but is `private`; also it is 3-dp normalization, not the epsilon tolerance the plan's prose implied |
| A system-actor convention exists for console-context activity logging | **FAILED** — no `config/activitylog.php`, no console command calls `causedBy`, no system/bot `User` record; only `BulkUpdateCourseOfferingSessionsAction.php:107` uses `causedBy(Auth::user())` |
| `PreviewSemesterGpaAction:59` calls `CalculateCumulativeGpaAction` (live recompute) | VERIFIED |
| `FinalizeSemesterGpaAction` `create()` at `:101`, student-wide `is_current` clear at `:97-99` | VERIFIED |
| `UNIQUE(student_id, semester_id)` live on `gpa_calculations` | VERIFIED against running schema |
| `SyncAcademicRecordsCommand:50-63` filters; `routes/console.php:49-53` 03:00 schedule | VERIFIED |
| `CanvasGradeSyncService` shared by scheduled sync and human Recalculate | VERIFIED |
| `SyncAttendanceToAcademicRecords:187-193` writes attendance fields only | VERIFIED |
| `TranscriptEntry::getCompletionStatusAttribute` derived accessor at `:54-57` | VERIFIED |
| Commit `5279ab348` (2026-08-13) removed the finalize status filter | VERIFIED |
| SUMMER2026 is the active semester (`is_active = Y`) | VERIFIED |
| `CourseGradeScale.php` precedent for a cross-boundary pure Academic helper | VERIFIED — used from `app/Models/AcademicRecord.php`, `Modules/Academic/Support/FailureReasonClassifier.php`, `.../Delivery/Actions/SaveLecturerGradebookScoresAction.php` |

#### Decisions

| # | Question | Decision |
|---|---|---|
| 1 | Where does the comparison rule live, given `normalize()` is private? | **Extract `app/Shared/Support/Academic/GpaValueComparator.php`**; the reconciliation query delegates to it; the finalize guard and the Phase 2 badge call it. Rejected making `normalize()` public (a global Action would then depend on a Progression query class) and rejected per-caller re-implementation (drift). |
| 2 | How is the scheduled sync attributed, with no system-actor convention? | **Record origin in activity `properties`** (`source`, command name, run timestamp); leave `causer_id` NULL. Rejected creating a synthetic `User` row — keeps non-people out of `users`. |
| 3 | `lockForUpdate()` on the compare-and-write? | **No lock, residual risk accepted.** The finalize transaction already spans a whole campus and the Recalculate transaction holds locks across a Canvas HTTP call; lengthening either is the worse trade. Derived divergence makes the failure mode a delayed correction, not a silent permanent error. Recorded in Phase 1 Risk Assessment so it is not silently reversed later. |
| 4 | Implementation order | **3 → 1 → 2 → 4 → 5.** Campus authorization first: independent of the GPA work, actively exposing PII and cross-campus finalize, and Phase 2 would otherwise widen that payload first. _(Superseded by Session 2: Phase 6 inserted before Phase 5 → 3 → 1 → 2 → 4 → 6 → 5. The rationale above still holds.)_ |

#### Propagation

- Phase 1: added the `GpaValueComparator` create target, the reconciliation-query delegation target, a "The comparison rule" section correcting the tolerance framing, the no-lock decision in Risk Assessment, plus an extraction step and todo.
- Phase 2: divergence specified to call `GpaValueComparator`; comparator added as a read-only dependency; tolerance prose corrected.
- Phase 4: attribution reframed from "system actor" to properties-based origin with its verification evidence; requirements, architecture, steps, success criteria and risks updated.
- plan.md: execution order pinned, comparator added to layer placement as the plan's only new source file, success criterion added for three-way agreement.

#### Whole-Plan Consistency Sweep

Re-read `plan.md` and all five phase files after propagation.

- Removed remaining "tolerance"/"0.001" framing that contradicted the actual 3-dp normalization rule.
- Removed "system actor" / "non-null actor" language from Phase 4 requirements, success criteria and risks so nothing contradicts the properties-based decision.
- Reconciled the "no new files" claim: the plan now states exactly one new source file plus tests.
- Reconciled phase-dependency prose with the pinned 3 → 1 → 2 → 4 → 5 order.
- Confirmed the earlier sweep's removals are still absent: transcript write hook, new audit command, arch test, `gpa_reopened_count`, shared remark-string constant, freeze predicate, `--include-finalized`, notification/sign-off gates, `FinalizedSemesterSyncFreezeTest`.

No unresolved contradictions remain.

## Red Team Review

### Session — 2026-08-14
3 hostile reviewers (Assumption Destroyer, Failure Mode Analyst, Security Adversary),
Standard verification tier. 28 raw findings → 17 after dedupe.
**Severity:** 4 Critical, 6 High, 5 Medium (+2 pre-existing defects, 1 promoted into Phase 3).
**Outcome:** original hook design withdrawn; 11 of 17 findings become structurally
impossible under derived staleness rather than being individually patched.
"Redesign" in the table below means the finding is void because the mechanism it
attacked no longer exists.

| # | Finding | Sev | Disposition | Applies to |
|---|---------|-----|-------------|-----------|
| 1 | `UNIQUE(student_id, semester_id)` makes re-finalize throw duplicate-key; whole bulk transaction aborts | Critical | Accept | Phase 1 (in-place update) |
| 2 | `is_finalized=false` deletes the row from 4+ read paths, incl. student portal (falls back to an OLDER semester's cumulative GPA) | Critical | Accept | Void — flag no longer written |
| 3 | `wasRecentlyCreated` guard excludes retake/resit/late-graded offerings — the highest-volume real change path | Critical | Accept | Void — no hook; comparison covers all shapes |
| 4 | Root-cause evidence was a backfill artifact; "138 at risk" void | Critical | Accept | plan.md Overview (re-baselined) |
| 5 | Lecturer-tier `complete_course_offering` (auto-granted per ADR 0013) can invalidate registrar-tier finalization | Critical | Accept | Void — course actions no longer mutate GPA state |
| 6 | Re-finalizing an earlier semester moves `is_current` BACKWARD student-wide (no semester predicate at FinalizeSemesterGpaAction:97-99) | High | Accept | Phase 1 |
| 7 | Reopen/re-finalize writes no activity log; `remarks` overwritten | High | Accept | Phase 1 (mandatory given in-place update) |
| 8 | `--repair` has no `--dry-run`/`--force`/pre-image/inverse | High | Accept | Void — no mutation command exists |
| 9 | `reconcilePersistedGpa` already computes stored-vs-recomputed GPA — new command duplicates it (DRY) | High | Accept | Phase deleted; verified working on this case |
| 10 | Finalize page has NO `is_finalized` filter — plan's "page picks the student up again" premise is FALSE; hook alone would ship an invisible state change | High | Accept | plan.md corrected + Phase 2 |
| 11 | No locking analysis: concurrent recalculate vs bulk finalize can lose the reopen silently, or deadlock across a Canvas HTTP call | High | Accept (mitigated, not eliminated) | Phase 1 Risk |
| 12 | `gpa_reopened_count` not derivable as specified — `commit()` returns void; remark-LIKE is a standing condition | Medium | Accept | Void — counter dropped |
| 13 | `chunk()` over the mutated column skips rows; repo uses `chunkById` | Medium | Accept | Void — no chunked mutation |
| 14 | Arch test cannot express write-vs-read intent; removal drift uncovered (no FK on `course_result_id`) | Medium | Accept | Void — arch test dropped; comparison catches removals |
| 15 | Propagation misses NULL-`start_date` later semesters | Medium | Accept | Phase 1 (`is_current` rule must be NULL-safe) |
| 16 | `Index.vue:179` claims finalized GPA is immune to later grade changes | Medium | **Reject as doctrine — accept as stale copy** | Phase 2 |
| — | "Delta is a source-semantics artifact, do not repair" | — | **Reject** — refuted by `activity_log` (real grade change, twice) and by both sources agreeing at 80.737 today | — |
| — | Campus `campus_id` bypass on GPA endpoints (pre-existing) | High | Accept — promoted to scope by user | Phase 3 |

### Why the design changed rather than being patched

Findings 1, 2, 3, 5 all traced to one decision: **reusing `is_finalized` as the
staleness marker.** In this codebase `is_finalized=false` does not mean "needs
review" — four read paths treat it as "does not exist" (student portal history and
current-GPA lookup with a fallback to an *older* semester, GPA History page and its
export, performance dashboards). And the finalize guard that "stops matching" is
also what protects the unique index from a duplicate insert, so bypassing it
converts a stale number into an aborted batch and an un-finalizable record.

Patching that design needed a migration, a marker column, an insert-aware hook, an
activity log, and a locking strategy. Deriving staleness instead — comparing the
stored snapshot against the recompute the finalize page **already performs** —
needs none of them, and covers change shapes the hook structurally could not see
(new retake attempts, late-graded offerings, removed source records). Eleven of
seventeen findings become inapplicable rather than mitigated.

What remains genuinely necessary: make re-finalize possible at all (Phase 1),
compute `is_current` from the latest finalized semester instead of last-write-wins
(Phase 1), log prior values on every correction since in-place update keeps no row
history (Phase 1), and show the divergence (Phase 2).

### Whole-Plan Consistency Sweep

Re-read `plan.md` and all three phase files after the redesign. Reconciled:
- title, description, status, effort, and the goals/non-goals tables now describe derived staleness, not the hook;
- the superseded root-cause evidence (2026-07-20 batch) is explicitly marked corrected in Overview, with the real `activity_log` trail replacing it;
- the void "138 rows at risk" figure replaced with measured counts (262 total rows, all `is_finalized=true`, 139 `is_current=true`);
- the false premise "the finalize page picks the student up again via its `already_finalized` guard" removed from Overview and corrected in Phase 2;
- phase files renamed to match their new content; all `plan.md` links updated;
- removed from scope and from the layer-placement list: the transcript write hook, the new audit command, the arch test, `gpa_reopened_count`, the remark-string constant shared across phases, and the "no migration ⇒ trivial rollback" claim;
- Phase 3 (campus authorization) promoted from "out of scope" to a phase, per user decision, and the remaining two pre-existing defects kept listed as out of scope.

No unresolved contradictions remain.

### Decisions resolved (2026-08-14)

1. **Product doctrine — RESOLVED, not a policy.** The `Index.vue:179` copy describes
   cumulative GPA as *"các kỳ đã chốt: sử dụng snapshot từ bảng GPA đã finalized …
   không bị ảnh hưởng khi điểm thay đổi sau khi chốt"* — an algorithm that has never
   existed in this codebase. `CalculateCumulativeGpaAction` always recomputes from
   transcript/academic records and has **zero** `GpaCalculation` references across all
   4 revisions back to 2025-12-30 (`d91adcd5e`, `bc03865a5`, `088414df8`, `8bab81c1a`);
   `PreviewSemesterGpaAction:59` calls that same live recompute. The copy was added
   2026-01-05 in `088414df8`, a UI feature commit for the history/dashboard pages, not
   a policy change. Verdict: stale/incorrect help text, no owner sign-off required.
   The text must be corrected as part of this work, since operators may currently
   believe finalized GPA is stable against grade corrections.
2. **Row model — in-place update** (user decision). Re-finalize updates the existing
   `(student_id, semester_id)` row; the unique index stays. Consequence: no row-level
   snapshot history, so an activity-log entry recording prior `semester_gpa` /
   `cumulative_gpa` is **mandatory**, not optional (Finding 7).
3. **Campus authorization — merged into this plan** (user decision) as Phase 3.
4. **Detection mechanism — derived, not stored** (user decision). Staleness is computed
   by comparing the stored snapshot against the live recompute the finalize page already
   performs (`PreviewSemesterGpaAction:59`), replacing brainstorm option C's write-time
   hook. This reverses a previously accepted design, so it was presented and chosen
   explicitly rather than applied unilaterally.
5. **Nightly sync — alert, do not freeze** (user decision, reversed on evidence) → Phase 4.
   Freeze was chosen first, then withdrawn once the trace showed the sync delivers
   *correct* grades over placeholders: freezing would have locked the placeholder values
   in as the official record. The sync keeps running; the change becomes attributable and
   reported instead.
8. **Correct the backlog immediately after ship** (user decision) → Phase 5, including the
   5 never-finalized pairs.
6. **Re-finalize UX — reuse the existing Finalize button** (user decision). No separate
   confirmation screen; the guard change makes Finalize correct diverged rows as well as
   create new ones. Phase 2's badge is what tells the operator in advance what will change.
7. **No cascade** (user decision). Correcting semester N leaves later semesters' cumulative
   divergent; each surfaces on its own page and is corrected the same way.

### Out of scope (pre-existing defects found during review — separate work)

- `CanvasGradeSyncService` HTTP call runs inside the DB transaction (`RecalculateApplyController.php:39-45`) — network latency holds row locks. Not introduced here, and the derived design no longer adds writes to that transaction.
- `GpaManagementController::finalize` does not catch `QueryException`, and the student loop runs in one transaction, so any per-student DB failure still aborts the whole campus batch. Phase 1 removes the duplicate-key failure mode but does not add error isolation.
- `GetStudentAcademicRecordsQuery:28-31` falls back to `$records->first()` when no current+finalized row exists, which can present an older semester's cumulative GPA as current. Latent today; would become reachable if anything ever un-finalizes a row.

<!-- slug: gpa-reopen-on-post-finalize-grade-change -->
