---
phase: 2
title: "Audit read paths: raw queries, inbound relations, status interaction"
status: pending
priority: P1
effort: "6h"
dependencies: [1]
---

<!-- Updated: Red Team Session 1 - Findings 4, 7, 8 -->

# Phase 2: Audit read paths: raw queries, inbound relations, status interaction

## Overview

Three distinct read-path problems, all surfaced by red-team review, none of which Phase 1's trait alone solves correctly:
1. Raw/non-Eloquent queries bypass the global scope entirely (original phase scope, greps corrected below).
2. The global scope also nulls **inbound** relations (`$payment->student`), which is wrong for historical views — needs `->withTrashed()` added to those relation definitions, not left as an accidental side effect.
3. `deleted_at` and `students.status` are two independent "student is gone" signals with no documented relationship — this phase pins that relationship instead of leaving it implicit.

## Requirements

- Functional: every read surface that lists/searches/exports/reports students excludes soft-deleted rows (except audit/integrity commands, which intentionally keep seeing trashed students — e.g. `AuditFinanceInvariants`).
- Functional: historical child-record views (payment lists, attendance sheets, grade reports, roster screens) keep rendering a deleted student's name/data instead of null-erroring.
- Non-functional: don't touch reads that intentionally need trashed students (e.g. an eventual restore/audit view — none planned per validation session 1).

## Architecture

Three categories:
1. **Eloquent forward queries** (`Student::query()`, list/search/export) — auto-fixed by Phase 1's global scope, no action needed.
2. **Raw/query-builder** (`DB::table('students')`, `join`/`leftJoin` keyed off `students`, raw SQL) — need explicit `deleted_at IS NULL` filter, qualified with table alias where joins exist. Audit/integrity commands are the deliberate exception — leave them unfiltered so they keep detecting orphaned/trashed data.
3. **Eloquent inbound relations** (`belongsTo(Student::class)` on 44 other models) — add `->withTrashed()` to each relation definition so a deleted student's historical child records keep resolving `->student` instead of null. This is a mechanical, per-relation-method change — much smaller than defensively null-checking the 117 call sites that dereference `->student`.

**Status interaction (Finding 8):** `students.status` (`active`/`inactive`/`suspended`/`graduated`/...) is left untouched by this feature — `deleted_at` and `status` are orthogonal. This plan does not invent new status-transition business logic. The practical interaction that matters: any Eloquent-based consumer that reasons about lifecycle via `status` (fee generation, roster gating, `StudentLifecycleStatusReader`) is *already* excluded once a student is soft-deleted, because those consumers query through `Student::query()`/relations and inherit Phase 1's scope automatically — **provided** they don't use a raw query (covered by category 2 above). The one thing this phase must explicitly verify: does semester fee generation query `Student` via Eloquent or raw SQL? If raw, add it to the category-2 filter list below.

## Related Code Files

- Audit (broadened pattern, red-team Finding 7 — the original two literal patterns missed most real hits): `grep -rnE "(DB::table\(|join\(|leftJoin\(|from\()\s*['\"]?students\b" app/ database/`
- Confirmed raw-query hits to fix (from red-team evidence, verify each still applies before editing):
  - `app/Modules/StudentRegistry/Support/EloquentCurriculumStudentSummaryReader.php:17,38,50` (already filters — qualify as `students.deleted_at` if joins are present)
  - `app/Modules/Academic/Delivery/Queries/ExportStudentAttendanceReportQuery.php:110`
  - `app/Modules/Academic/Progression/Queries/GetDeferReturnWatchlistQuery.php:133,161`
  - `app/Modules/Academic/Progression/Queries/GetMissingDecisionReportQuery.php:86,102`
  - `app/Modules/Academic/Progression/Queries/GetAcademicProgressionReconciliationQuery.php:517,652`
  - `app/Modules/Academic/Progression/Queries/Reporting/GetAcademicReportQuery.php:84`
  - `app/Modules/Finance/Support/BillingExceptionTuitionWaivedMatcher.php:19`
  - `app/Modules/Finance/Support/LifecycleDueItemPredicate.php:53`
  - `app/Modules/Finance/Queries/Reporting/ExportStudentScholarshipApplicationQuery.php:82`
  - `app/Services/FailedStudentsService.php:83` (`leftJoin`)
  - `app/Modules/Engagement/Queries/Surveys/GetSurveyProgramStatsQuery.php:49` (raw `JOIN students s`)
  - `app/Console/Commands/CheckMissingEgcInvoices.php:52`
  - **Leave unfiltered (deliberate audit surfaces):** `app/Console/Commands/AuditFinanceInvariants.php:140,184`
- Modify (44 sites, red-team Finding 4): every `belongsTo(Student::class)` relation method — enumerate via `grep -rln "belongsTo(Student::class" app/` (confirmed count: 44; known examples: `app/Models/StudentActionLog.php:64`, `app/Models/Attendance.php:74`, `app/Models/CourseRegistration.php:102`, `app/Models/EgcBlock.php:47`) — add `->withTrashed()` to each relation return statement.
- Verify: does semester/fee-generation code query `Student` via Eloquent (auto-scoped) or raw SQL (needs the category-2 filter)? Locate via `grep -rln "generateTuition\|generateFee\|semester.*fee" app/Modules/Finance app/Console/Commands`.
- Audit: `app/Modules/StudentRegistry/Queries/ListStudentsQuery.php`, `ExportStudentsQuery.php` — confirm they go through Eloquent (`Student::query()`) and inherit the scope automatically.

## Implementation Steps

1. Run the broadened grep, reconcile against the confirmed-hit list above (files may have moved since red-team review — re-verify each), and add explicit `deleted_at IS NULL` filters to every hit except the deliberate audit-command exceptions.
2. Enumerate the 44 `belongsTo(Student::class)` relations and add `->withTrashed()` to each.
3. Verify fee-generation's query path; add it to step 1's filter list if raw.
4. Manually confirm: soft-delete a test student who has payment/attendance/grade history, then load the payment list, attendance sheet, and grade report for a class they were in — history renders (not null-error), but they're absent from `/students` list, search dropdown, export, and student-facing reports.
5. Document the final audited file list in this phase's PR/commit description (no new recurring-check abstraction needed — YAGNI, this is a one-time audit, not a lint rule).

## Success Criteria

- [ ] Every raw/query-builder read path filters out soft-deleted rows, except deliberate audit commands (documented as such)
- [ ] All 44 `belongsTo(Student::class)` relations resolve `withTrashed()`
- [ ] Manual check: soft-delete a test student with history, confirm list/search/export exclude them AND their historical child records (payments, attendance, grades) still render correctly for staff who already have those records open
- [ ] Fee-generation's query path verified and covered (Eloquent-scoped, or explicitly filtered if raw)

## Risk Assessment

Missing a raw query path means a "deleted" student keeps appearing somewhere — data-hygiene bug, not a security issue (permission gate in Phase 3 still protects the delete action itself). Missing a `withTrashed()` relation means a 500 error on a legitimate historical view — user-facing breakage, higher severity than the original phase estimated. Mitigate by working from the enumerated lists above, not re-deriving them from scratch with the narrower original grep patterns.
