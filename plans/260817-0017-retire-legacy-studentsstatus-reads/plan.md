---
title: "Retire legacy students.status reads"
description: "Route reads of the write-dead students.status column through the program_enrollments lifecycle projection, keeping the column as fallback and as the deliberate historical source for three EGC rules."
status: done
priority: P1
effort: 20h
branch: dev
tags: [academic, finance, lifecycle, tech-debt, student-registry]
created: 2026-08-17
---

# Retire legacy students.status reads

## Overview

`students.status` is **write-dead**: only written at student creation, never
synced back by any lifecycle transition. Canonical source is the
`program_enrollments` primary row (`is_primary = 1`, `enrollment_status` +
`study_stage`).

This plan routes reads through the lifecycle projection, keeping the column as
(a) the **fallback** for students with no primary enrollment and (b) the
deliberate **historical** source for three EGC rules that genuinely want
stage-at-admission.

### The fact that shapes every phase

Dev DB: **235 students, 235 with a primary enrollment. Zero on the fallback branch.**

The ~288 untouched test files create students with **no** enrollment, so they
exercise **only the fallback**. Prod-like data exercises **only the
projection**. The two sets never overlap.

> **"We left the tests alone and they still pass" is not evidence of safety.**
> It is evidence that the existing suite does not cover the code path this plan
> changes. Every phase must add at least one fixture with a materialized
> enrollment, or it verifies the branch it did not touch.

### Scope boundaries (accepted, not re-litigated)

- NO schema change, NO backfill, NO column drop, NO reverse-sync.
- Do NOT bulk-edit the ~288 existing test files, `StudentFactory`, or seeders — but DO add enrollment-backed fixtures per phase (see above).
- No second field on `StudentReference`; the `status` field changes source, not shape.
- `Student::FINANCIAL_STATUSES` / `BLOCKED_STATUSES` / `CLASS_ROSTER_INACTIVE_STATUSES` unchanged — only the source of the compared value changes.

## Measured baseline

`students.status` value distribution (235 students):

| Value | Count |
|---|---|
| `intake_course` | 151 |
| `deferred` | 49 |
| `intake_pre_uni_gc` | 24 |
| `pending_course_opening` | 6 |
| `dropout` | 4 |
| `active` | 1 |

**No student has `suspended`, `inactive`, `graduated`, or `pending`.**

### Measured drift — 15 students, 5 transition classes

Supersedes the earlier "11 students, `deferred`→`active`" narrative, which was
wrong in both count and shape. The projection **never emits `active`**.

| Column → projection | Count |
|---|---|
| `deferred` → `intake_course` | 8 |
| `intake_pre_uni_gc` → `intake_course` | 3 |
| `intake_pre_uni_gc` → `deferred` | 2 |
| `intake_pre_uni_gc` → `dropout` | 1 |
| `pending_course_opening` → `dropout` | 1 |

### Named accepted behavior changes — user-approved individually

The 8 `deferred → intake_course` students regain registration, which is the
plan's intent. The other **7** change in ways the original plan did not model.
**All 7 approved by the user by student code:**

| Student | Transition | Effect |
|---|---|---|
| AUH18676 | `pending_course_opening` → `dropout` | loses portal access + roster |
| AUH15060 | `intake_pre_uni_gc` → `dropout` | loses portal + roster + EGC |
| AUH15041 | `intake_pre_uni_gc` → `deferred` | leaves roster, keeps login |
| AUS118115 | `intake_pre_uni_gc` → `deferred` | leaves roster, keeps login |
| AUH16175 | `intake_pre_uni_gc` → `intake_course` | leaves EGC |
| AUS15106 | `intake_pre_uni_gc` → `intake_course` | leaves EGC |
| AUS121787 | `intake_pre_uni_gc` → `intake_course` | leaves EGC |

No per-student sign-off gate is required at implementation time.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | One complete, classified inventory before any code moves | P1 |
| 2 | Flip the single source (`EloquentStudentRegistryStore`) — ~20 consumers follow | P1 |
| 3 | Migrate remaining SQL filters and PHP readers that bypass the store | P1 |
| 4 | Keep 3 EGC rules on the historical column, deliberately and documented | P1 |
| 5 | Prevent regression with a receiver-anchored guard test (no CI in this repo) | P1 |
| 6 | Introduce no N+1: every list/stream/bulk path resolves in batch | P1 |

## Whitelist-historical — the 3 EGC sites (do NOT migrate)

User decision, binding. The frozen column encodes **stage at admission / at the
time the block or action was created**, which is exactly what these three rules
ask for. Migrating them would be a regression, not a fix.

| Site | If migrated (wrong) |
|---|---|
| `app/Modules/Finance/Queries/Egc/ListEgcRetakeAdjustmentsQuery.php:72` + its feeder `app/Modules/Academic/Support/AcademicFinanceChargeSourceGateway.php:1016` (`EgcBlockData.student_status`) | strips EGC retake discounts from 3 students |
| `app/Modules/Academic/Progression/Support/StudentActionExcelRowMapper.php:132` | rejects previously-valid import files |
| `app/Services/CourseCompletionService.php:616` | mutates `egc_students_count` on already-closed offerings |

Phase 4's tests asserting a "drifted-in EGC gain" are **deleted** — they
asserted the wrong outcome.

## A1 Classification Table (Phase 0)

44 files from the corrected grep (`\$student(\?)?->status\b`, `students\.status`,
`->student(\?)?->status\b`, `where(In|NotIn)?\('status'`,
`whereHas\('student', ...where\('status'`), receiver-resolved by hand. Zero
unassigned. Comment-only mentions listed separately (excluded from the guard,
not a bucket).

**Legend:** M = migrate, F = whitelist-fallback, H = whitelist-historical,
W = whitelist-writer, N = whitelist-no-edit (audit/seed). `done` = already
correctly migrated in the working tree at plan-start (verified by diff read,
not re-touched).

| # | File:line | Bucket | Phase | Note |
|---|---|---|---|---|
| 1 | `EloquentStudentRegistryStore.php:228,286,313` | M | 1 | source flip — the ~20-consumer leverage point |
| 2 | `EloquentSemesterEnrollmentEligibilityReader.php:49` | M | 1 | live eligibility gate, C3 |
| 3 | `LifecycleDueItemPredicate.php:15` | M | 2 | via `isLifecycleExceptionStatus` seam |
| 4 | `LifecycleDueExceptionReasonResolver.php:14` | M | 2 | H1, same commit as #3 |
| 5 | `GetLifecycleDueExceptionSummaryQuery.php:37-39,44` | M | 2 | |
| 6 | `ListLifecycleDueExceptionsQuery.php:152,156` | M | 2 | |
| 7 | `Student.php:536` (`scopeActive`) | M | 3 | |
| 8 | `Student.php:545` (`scopeClassRosterActive`) | M | 3 | |
| 9 | `CourseRegistration.php:154` | M | 3 | |
| 10 | `DashboardChartsService.php:58,59,267,268` | M | 3 | |
| 11 | `DashboardStatsService.php:68-85` (`groupBy('status')`) | M | 3 | H5 — missing keys |
| 12 | `EloquentStudentImpersonationTokenIssuer.php:22,33` | M | 4 | M3; `:21` gate is #7/#8 via `isActive()` |
| 13 | `Http/Controllers/Api/StudentController.php:133` | M | 4 | |
| 14 | `Http/Controllers/Api/AuthController.php:107,217` | M | 4 | |
| 15 | `EloquentStudentPortalProfileReader.php:99` | M | 4 | |
| 16 | `AiAcademicStudentProfileReader.php:79` | M | 4 | gated on Q5 |
| 17 | `EventParticipantResource.php:47` | M | 4 | no batching seam, see phase-4 note |
| 18 | `LecturerAttendanceService.php:110` | M | 4 | batch |
| 19 | `PreviewStudentDecisionBulkLinkQuery.php:58` | M | 4 | batch |
| 20 | `AiAcademicEntitySearchReader.php:55` | M | 4 | batch, gated on Q5 |
| 21 | `EloquentStudentDeferLifecycleReader.php:56` | M | 4 | batch |
| 22 | `StudentAcademicSummaryService.php:1133` | M | 4 | display payload, not in earlier revisions |
| 23 | `CreateEgcStudentActionLogsCommand.php:166` | M | 4 | current-status validation gate, not historical |
| 24 | `StudentService.php:546-556` (`updateStudentStatus`) | — | 4 | **delete** (bucket D, M4), 0 callers |
| 25 | `ListDueItemsQuery.php` | M | — | **done** — batched via `StudentLifecycleStatusReader` |
| 26 | `ListCollectionProgressQuery.php:287,493,539` | M | — | **done** — `?? $student->status` fallback tail |
| 27 | `ListFeeMonitorQuery.php:688,919` | M | — | **done** |
| 28 | `ListDngLifecycleQuery.php:330` | M | — | **done** |
| 29 | `AcademicFinanceChargeSourceGateway.php:961` (`examResitDueData`) | M | — | **done** |
| 30 | `LifecycleDueExceptionReviewEventMapper.php` | M | — | **done** — status was live; this session fixed label/color to match |
| 31 | `LifecycleDueExceptionRowMapper.php` | M | — | **done** |
| 32 | `ListStudentsQuery.php:67` | M | — | **done** — overwrites DTO field with live value |
| 33 | `StudentRegistry/Http/Web/StudentController.php:241,321` | M | — | **done** — fallback tail |
| 34 | `StudentExport.php:176` | M | — | **done** — fallback tail |
| 35 | `StudentLifecycleStatusReader.php:30-36` | F | — | **is** the fallback mechanism |
| 36 | `EloquentStudentCollectionEligibilityReader.php:40` | F | — | already enrollment-first (advisory Q1, resolved) |
| 37 | `ListEgcRetakeAdjustmentsQuery.php:72` | H | — | user decision 2, binding |
| 38 | `AcademicFinanceChargeSourceGateway.php:1016` (`egcBlockData`) | H | — | feeder for #37 |
| 39 | `StudentActionExcelRowMapper.php:132` | H | — | user decision 2 |
| 40 | `CourseCompletionService.php:616` | H | — | user decision 2 |
| 41 | `GetStudentStatusBySemesterQuery.php:216,217,236` | H | — | per-semester timeline reconstruction |
| 42 | `MaterializeProgramEnrollmentAction.php:88-100,123` | H | — | intake seed — bootstraps enrollment *from* the column |
| 43 | `EloquentProgramEnrollmentReader.php:148,178,179` | H | — | same intake-seed reasoning |
| 44 | `ProcessEgcCourseResultsAction.php:640` | H | — | intake seed, dry-run mirror of #42 |
| 45 | `MigrateStudentProgressionEventsCommand.php:115,142,153` | H | — | one-shot migration command |
| 46 | `UpdateDeferredStudentsCourseRegistrations.php:45` | H | — | **descoped** (H6), not migrated — see phase 3 |
| 47 | `StudentAuditableModel.php:206` | N | — | audit trail logs the column's **own** old/new value |
| 48 | `Api/AuthController.php` (creation path), `RegisterAdmittedStudentAction.php` | W | — | write-time only, unchanged |

**Comment-only (excluded from the guard, not a bucket):**
`SendDueItemRemindersAction.php:57`, `SendDueItemParentRemindersAction.php:61`,
`BillingExceptionCollector.php:495`, `LifecycleStatusTimeline.php:16`,
`GetStudentLifecycleCohortMatrixQuery.php:22`, `LifecycleDueItemPredicate.php:28,64`,
`Student.php:28,618`, `ListStudentsQuery.php:49`,
`EloquentStudentCollectionEligibilityReader.php:15`, `ListDueItemsQuery.php:101`,
`AcademicFinanceChargeSourceGateway.php:933`, `LifecycleDueExceptionRowMapper.php:79`,
`ListFeeMonitorQuery.php:681`, `ListDngLifecycleQuery.php:342`,
`ListCollectionProgressQuery.php:285`.

**Downstream-of-phase-1, no direct edit** (C4): the 5 Delivery gates
(`BulkRegisterCourseOfferingStudentsAction.php:35`,
`CreateRetakeCourseRegistrationAction.php:63`,
`RegisterStudentForActiveSemesterUnitsAction.php:41`,
`SearchCourseOfferingStudentsAction.php:62,63`,
`CheckCourseRegistrationEligibilityQuery.php:32`) read `$student->status` on a
`StudentReference` **DTO**, populated by `EloquentStudentRegistryStore` (#1).
Once #1 flips, these read live data with zero code change. Phase 5's guard must
not whitelist them by path — receiver-anchoring (by `Student` model import)
keeps them off the guard's candidate set entirely.

**Consumers, not read sites** (call the migrated predicate/resolver, do not
themselves read `$student->status`): `ResolveLifecycleDueExceptionAction.php`,
`AcknowledgeLifecycleDueExceptionAction.php`, `LifecycleDueExceptionController.php`.

## A4 Baseline (Phase 0)

Recorded before phase 1 starts, read-only against the dev `asia` database
(`./scripts/dev.sh artisan tinker`, **not** `--env=testing` — no `.env.testing`
exists, that flag targets the wrong database). Full JSON:
[`a4-baseline.json`](./a4-baseline.json). 235 total, 15 drift — matches the
measured-baseline table above exactly.

## Phases

| # | Phase | Effort | Depends on | Status |
|---|-------|--------|-----------|--------|
| 0 | [Inventory and contracts](./phase-00-inventory-and-contracts.md) | 3h | — | Done |
| 1 | [Flip at source: StudentRegistry store](./phase-01-start.md) | 4h | 0 | Done |
| 2 | [Finance SQL filters](./phase-02-finance-sql-filters.md) | 3h | 0 | Done |
| 3 | [Roster, auth and dashboards](./phase-03-roster-and-dashboards.md) | 5h | 0, 1 | Done |
| 4 | [Display, API and AI readers](./phase-04-display-api-and-ai-readers.md) | 3h | 0 | Done |
| 5 | [Guard test and drift probe](./phase-05-guard-test-and-drift-probe.md) | 2h | 0,1,2,3,4 | Done |

### Dependencies and parallelism — corrected

**The earlier "phases 1/2/4 run in parallel" claim was false.** Phases 1 and 3
were told to reuse a helper phase 2 placed in `app/Modules/Finance/Support/`, a
cross-module import that fails
`tests/Feature/Architecture/DomainBoundaryArchitectureTest.php:7-35` — which is
also phase 5's green gate.

Corrected: **phase 0 first and alone.** It delivers the shared scope under
`app/Shared/`. Then phases 1, 2, 4 may run in parallel; phase 3 waits on 1;
phase 5 waits on all.

```
0 ──┬── 1 ──┬── 3 ──┐
    ├── 2 ──┤       ├── 5
    └── 4 ──┴───────┘
```

### File ownership

| Phase | Owns (exclusive) |
|---|---|
| 0 | the new `app/Shared/…` projection scope class |
| 1 | `app/Modules/StudentRegistry/Support/EloquentStudentRegistryStore.php` |
| 2 | `LifecycleDueItemPredicate.php`, `LifecycleDueExceptionReasonResolver.php`, `GetLifecycleDueExceptionSummaryQuery.php`, `ListLifecycleDueExceptionsQuery.php`, `ResolveLifecycleDueExceptionAction.php`, `AcknowledgeLifecycleDueExceptionAction.php`, `LifecycleDueExceptionController.php` |
| 3 | `app/Models/Student.php`, `app/Models/CourseRegistration.php`, `app/Models/CourseOffering.php`, `DashboardChartsService.php`, `DashboardStatsService.php` |
| 4 | the display readers listed in phase 4 |
| 5 | `tests/Feature/Architecture/LegacyStudentStatusReadGuardArchTest.php` |

**Hard conflict:** `app/Models/Student.php` is phase 3 only. Phase 4 must not
touch it.

## Test paths — existence verified

| Path | State |
|---|---|
| `tests/Feature/Registry/` | exists |
| `tests/Feature/Academic/RetakeCourse/` | exists |
| `tests/Feature/Academic/Delivery/` | exists |
| `tests/Feature/Academic/Progression/` | exists |
| `tests/Feature/Finance/Operations/` | exists |
| `tests/Feature/Finance/Reporting/` | exists |
| `tests/Feature/Finance/Egc/` | exists |
| `tests/Unit/Finance/Operations/` | exists — **added to phase 2** (H1) |
| `tests/Feature/Engagement/`, `tests/Feature/Mcp/`, `tests/Feature/Architecture/` | exist |
| `tests/Feature/Reporting/` | **does not exist — created by phase 3** |

**Never run `tests/Feature/Academic/` as a whole directory** — it aborts with
exit 255 on a `class_sessions` CHECK constraint. Run subdirectories.
Finance suites carry 2 known pre-existing failures (settlement-snapshot,
EGC-only operator). Baselines record failing **names**, not counts.

## Acceptance criteria

- [x] Phase 0 classification table covers 100% of grep hits (48 files — above the 36–45 estimate, zero unassigned).
- [x] `./scripts/dev.sh artisan test tests/Feature/Architecture/` → green, including `DomainBoundaryArchitectureTest` and the new guard (only the 4 pre-existing, unrelated failures remain).
- [x] Auth-path tests green: a withdrawn-by-enrollment student is denied by `StudentApiAuthorization`, `ParentStudentAccess`, portal token refresh, and impersonation (C1).
- [x] Two-primary-row fixture proves highest-id wins in both the PHP relation and the SQL twin (C5).
- [x] Query-count assertions at **exactly 1** status query for: `findMany()` (10 students), bulk register (~20 codes), a 20-student roster. EGC block collections stay on the historical column (whitelist-historical, user decision 2) — no new query to count there.
- [x] `rg -c "CASE\s*WHEN.*enrollment_status" app/` → **1**.
- [x] Drift diff vs. the phase-0 recorded baseline JSON contains only the 15 known students (`a5-drift-probe.json`, verified against dev `asia`).
- [x] `array_sum($byStatus) === $total` asserted in the dashboard test (H5).
- [x] Response key `active_count` unchanged (frozen contract, M2).

## Open questions

| # | Question | State |
|---|---|---|
| 1 | Is `EloquentStudentCollectionEligibilityReader` enrollment-first? | **RESOLVED — yes**, already migrated (`:36-40,58-62`). No work. |
| 2 | Is `inactive` account-state or lifecycle-status? | **RESOLVED — dead value.** No carve-out. `BLOCKED_STATUSES` stays intact as a defensive allow-list (the projection still emits `dropout`, `graduated`, `pending`, `pending_course_opening`). A self-registered `inactive` student has no enrollment → fallback → still blocked. Documented as Medium risk M5, not a gate. |
| 3 | The single `active` enrollment with `study_stage = NULL` | **RESOLVED 2026-08-17 — legitimate data, no backfill.** Probed: student `AUH131310`, `students.status = 'active'`, `academic_status = 'active'`, `intake_gc` and `intake_major` both NULL — the student genuinely has no intake stage, so `MaterializeProgramEnrollmentAction::studyStage()` correctly returns NULL and the projection correctly emits `active`. Treat `active` as a real projected value: add it to `$expectedStatuses` and uncomment `'active' => 'Active'` in `Student::statusLabelFor()` (already required by H5). Phase 3 unblocked. |
| 4 | Dashboard `active_count` semantics | **RESOLVED — freeze the response key**, change only its SQL. Consumer `resources/js/components/dashboard/StudentDistribution.vue:17,23,169` is typed non-optional; renaming yields `NaN`. |
| 5 | Is the AI readers' `status` an MCP contract value? | **OPEN — blocks phase 4.** Check `tests/Feature/Mcp/McpFieldAllowlistArchitectureTest.php` for value assertions. |

## Related

- Accepted advisory: [`plans/reports/advise-260816-2344-student-status-legacy-migration.md`](../reports/advise-260816-2344-student-status-legacy-migration.md)
- Thematically overlapping, `completed`, no blocker: `plans/260815-1320-close-remaining-legacy-shims-and-final-dead-cleanup`

---

## Red Team Review

3 hostile reviewers, 29 findings → 15 after dedupe. **All accepted by the user.**
User answered every product question; those answers are binding above.

### Findings and disposition

| ID | Sev | Finding | Disposition | Applied in |
|---|---|---|---|---|
| C1 | Critical | `isActive()` never flips — "compute only if relation loaded, never lazy-load" leaves every auth path on the dead column while `scopeActive()` (SQL) does flip. A withdrawn student vanishes from lists but keeps portal login and impersonation. Plan listed 1 of ~8 consumers. | Accept | phase 3 |
| C2 | Critical | Drift numbers wrong (11/1-class predicted vs 15/5-class measured); phase 5's gate fails on day one. | Accept | plan.md, phase 0 (A4), phase 5 |
| C3 | Critical | Inventory ~40% short — guard patterns match 36–45 files, plan budgeted ~14. 7 unassigned file groups incl. a live eligibility gate and a write gate. | Accept | **new phase 0 (A1)** |
| C4 | Critical | Guard test broken both ways: misses 4 SQL shapes the plan itself migrates; matches comment-only hits in 4 files; path-set equality would force whitelisting the 5 Delivery gates whose `$student->status` is a **DTO** read (`StudentReference.php:24`) — blinding the guard at the migration's success. | Accept | phase 5 |
| C5 | Critical | Primary-row tie-break diverges 3 ways (reader=highest id, planned relation=oldest, phase-2 `whereExists`=any). Multiple `is_primary=1` rows are schema-legal — unique index covers only primary+active. | Accept | **phase 0 (A2)**, phase 3 fixture |
| H1 | High | Phase 2's signature change breaks 3 unlisted callers + a test file at a path phase 2 never ran; sibling `LifecycleDueExceptionReasonResolver::resolve(?Student)` stays on the column → gate projection-based, persisted reason legacy. | Accept | phase 2 |
| H2 | High | "Phases run in parallel" false — shared helper in `app/Modules/Finance/` fails `DomainBoundaryArchitectureTest`, which is phase 5's own gate. | Accept | plan.md deps, **phase 0 (A3)** |
| H3 | High | Phase 1 rollback claim false: `activeIdsForCampus()` feeds `StudentFormAssignment::upsert()` with no delete arm; measured set delta 175→180. Reverting the file does not undo the writes. | Accept | phase 1 |
| H4 | High | `CourseOffering` counts rosters via 3 branches chosen by which relation is loaded; phase 3 eager-loads only 1 of 3 → same offering, different counts, same page load. | Accept | phase 3 |
| H5 | High | `$expectedStatuses` lacks `pending_course_opening` (6) and `active` (1) while `:74` sums all keys → `by_status` 229 vs `total` 235, silently. `statusLabelFor()` has `'active'` commented out → "Unknown". | Accept | phase 3 |
| H6 | High | `UpdateDeferredStudentsCourseRegistrations` mass-writes `registration_status='defer'` with no prior-value capture; target population shifts 8 out / 2 in. | Accept-modified — **descoped**, not migrated | phase 3 |
| H7 | High | Bulk N+1: `findByStudentCode()` inside `foreach` in bulk register (**inside `DB::transaction`**) and offering search; 200 codes → 400 queries. Plus a per-row `forStudentId()` in `LifecycleDueExceptionRowMapper:80-82`. | Accept | phase 1, phase 2 |
| H8 | High | Phase 1 omits `findSerialized()`, `findManySerialized()`, `findProfile()` — `toArray()` with `$hidden = []` emits the raw column to `CourseRegistrationPresenter:38` and `ListCourseRegistrationsQuery:59` → roster and registration list disagree. | Accept | phase 1 |
| M1 | Med | Drift probe tautological — compares reader against column, its fallback arm IS `pluck('status','id')`, so `fallback_broken == []` is true by construction and vacuous at 0/235. Never touches the flipped surfaces. | Accept | phase 5 |
| M2 | Med | `active_count`: plan.md said Q4 resolved, phase 3 still said "answer first" and suggested renaming; consumer Vue is typed non-optional → `NaN`. | Accept | plan.md Q4, phase 3 |
| M3 | Med | Phase 4 line refs wrong for `EloquentStudentImpersonationTokenIssuer` (gate `:21`, message `:22`, unmentioned payload `:33`); issuer matches email/student_id with no campus scoping (`:17`). | Accept | phase 4 |
| M4 | Med | `StudentService::updateStudentStatus()` has 0 callers — dead code, mislabeled "create-time writer" in the whitelist. | Accept | phase 0, phase 4 |
| M5 | Med | Suspended/inactive representability. | Accept-modified — **downgraded** from Critical per user decision 1; documented risk, not a gate | plan.md Q2, phase 3 |

### Product decisions applied

1. **suspended/inactive are dead values.** No legacy-column account-state carve-out. `BLOCKED_STATUSES` kept intact as a defensive allow-list. → Q2 resolved; C1's fix is a straight flip, not a carve-out.
2. **The 3 EGC sites keep the historical source.** New `whitelist-historical` bucket; phase 4's drifted-in-EGC-gain tests deleted.
3. **All 7 unmodelled drift students approved by name.** Recorded above; no per-student sign-off gate.

### Whole-Plan Consistency Sweep

Re-read `plan.md` + all 6 phase files after applying findings. Reconciled:

| Stale claim | Was | Now |
|---|---|---|
| Drift narrative | "11 students, `deferred`→`active`" | 15 students / 5 classes; `active` never emitted (plan.md, phase 1, phase 5) |
| Parallelism | "phases 1/2/4 run in parallel" | phase 0 gates all; corrected graph above |
| Whitelist size | "≤ 14 paths", "`wc -l` equals whitelist length" | both **deleted** — unmeasured; phase 0 measures (36–45) |
| Phase 3 Q4 | "answer Q4 first", "rename to `enrolled_count`" | **deleted**; `active_count` key frozen |
| Phase 4 EGC | tests asserting drifted student *gains* retake discounts | **deleted**; sites moved to whitelist-historical |
| Advisory Q1/Q2 | listed open | both resolved in the table above |
| `StudentService::updateStudentStatus` | whitelisted as "create-time writer" | reclassified dead code, deletion scheduled |
| `tests/Feature/Reporting/` | cited as if existing | marked created-by-this-plan |
| Phase 2 test scope | `tests/Feature/Finance/…` only | `tests/Unit/Finance/Operations/` added |
| Effort | 14h | 20h (phase 0 added; phases 1/2/3 grew) |

**Residual, accepted:** the guard test still cannot run automatically — the repo
has no CI. Phase 5 states this as the honest ceiling rather than implying
coverage it does not have.

<!-- slug: retire-legacy-studentsstatus-reads -->
