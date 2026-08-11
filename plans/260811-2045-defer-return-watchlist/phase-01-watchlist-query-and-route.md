---
phase: 1
title: "Watchlist Query And Route"
status: done
priority: P1
effort: "5h"
dependencies: []
---

# Phase 1: Watchlist Query And Route

## Overview

Backend only: one query class producing the bucketed, campus-scoped, paginated
watchlist rows plus bucket counts, wired to a controller method and a route behind
`can:view_student_action`. Tests first.

## Requirements

**Functional**
- Return one paginated list of students still on hold, each row carrying its bucket.
- Buckets per `plan.md` → `overdue`, `upcoming`, `waiting`.
- Optional `bucket` filter; default = all buckets, ordered `overdue` → `upcoming` → `waiting`.
- Optional `search` on student name / student code (same shape as missing-decisions).
- Return bucket counts independent of the current page and of the `bucket` filter.
- Latest defer log per student only — no duplicate rows for repeat deferrals.
- `upcoming` has **no horizon cutoff** — every future return semester is listed.
  A window would hide long-dated deferrals, the exact miss this report prevents.
- Excel export of the current filter + campus scope (validation Session 1 decision).

**Non-functional**
- Single joined query builder (no per-row Eloquent hydration), mirroring
  `GetMissingDecisionReportQuery`.
- Campus scoping applied server-side from `session('current_campus_id')`.
- Nullable `semesters.start_date` / `end_date` must not drop a row.

## Architecture

Data sources: `student_action_logs` ⋈ `students` ⋈ `semesters` (return semester,
left join) ⋈ `campuses` (left join).

Two normalised legs unioned, same as the missing-decisions precedent:

1. **defer leg** — `action_type = 'ACADEMIC_DEFER'`, latest defer log for that student,
   join `semesters` on `return_semester_id`.
   Bucket = `start_date <= today ? 'overdue' : 'upcoming'`.
2. **waiting leg** — `action_type = 'WAITING_COURSE_OPENING'`, latest log per student,
   join `semesters` on `from_semester_id` for the on-hold anchor date. Bucket = `waiting`.

Each leg filters `students.status` to that action's own
`StudentActionType::targetStatus()` — `ACADEMIC_DEFER → 'deferred'`,
`WAITING_COURSE_OPENING → 'pending_course_opening'`
(`app/Enums/StudentActionType.php:69,71`). Do **not** retype the literals: the enum is
the single place the action↔status coupling lives, and it is what makes a row
self-clear when resume/dropout/transfer moves the student off that status.
(`TransitionProgramEnrollmentAction` hardcodes the same strings at `:124`/`:129`/`:140`
— that is not the source to copy.)

Common projected columns:

```
bucket            overdue | upcoming | waiting
severity          in_semester | semester_ended | null      (overdue only)
action_id         student_action_logs.id
action_type
student_pk, student_code, student_name, student_status
campus_id, campus_name, campus_code
anchor_semester_id, anchor_semester_code, anchor_semester_name
anchor_start_date, anchor_end_date
days_elapsed      today - anchor_start_date  (negative = days until return)
```

`days_elapsed` is computed in SQL (`DATEDIFF`) so ordering works across pagination;
the page renders it, it is not recomputed client-side.

"Latest log per student" — filter to the max `id` per `(student_id, action_type)`
via a correlated subquery or a join against a grouped derived table. Prefer whichever
reads cleaner; assert the behavior in a test rather than in a comment.

Bucket counts: reuse the union subquery, `groupBy('bucket')->selectRaw('count(*)')`,
executed once — do not paginate it and do not re-derive it in the frontend.

## Related Code Files

- Create: `app/Modules/Academic/Progression/Queries/GetDeferReturnWatchlistQuery.php`
- Create: `app/Modules/Academic/Progression/Exports/DeferReturnWatchlistExport.php`
- Create: `tests/Feature/Academic/DeferReturnWatchlistQueryTest.php`
- Modify: `app/Modules/Academic/Progression/Http/Web/AcademicProgressionAuditController.php` — add `deferReturns()` + `exportDeferReturns()`
- Modify: `app/Modules/Academic/routes/web.php` — add both routes inside the existing `reports/academic-progression` group (beside `missing-decisions`, ~line 441)
- Modify: `tests/Feature/Academic/Progression/ProgressionReportingWebRoutesTest.php` — route + permission coverage

Read before writing:
- `app/Modules/Academic/Progression/Queries/GetMissingDecisionReportQuery.php` (query shape, `baseQuery`/`mapRow` split, campus scope, search)
- `app/Modules/Academic/Progression/Http/Web/AcademicProgressionAuditController.php:103-119` (`missingDecisions` method shape)
- `app/Modules/Academic/Progression/Exports/StudentActionLogsExport.php` (`FromQuery` + `WithHeadings` + `WithMapping` shape)
- `app/Modules/Academic/Progression/Http/Web/StudentActionAuditController.php:183-199` (`ExcelExportService::download` usage, filename convention)
- `tests/Feature/Academic/MissingDecisionReportQueryTest.php` (Pest fixtures, `RefreshDatabase`, factory helpers)

## Implementation Steps

1. Write `tests/Feature/Academic/DeferReturnWatchlistQueryTest.php` first, RED. Cases:
   - defer + return semester started + status `deferred` → `overdue`, `severity` correct
   - defer + return semester ended → `overdue` with `severity = semester_ended`
   - defer + future return semester → `upcoming`
   - defer whose student status moved to `intake_course` → absent
   - `WAITING_COURSE_OPENING` + `pending_course_opening` → `waiting`, `days_elapsed` from `from_semester.start_date`
   - two defer logs for one student → exactly one row (the latest)
   - other-campus student excluded when `campusId` passed
   - `search` matches on name and on student code
   - bucket counts unaffected by the `bucket` filter and by pagination
2. Implement `GetDeferReturnWatchlistQuery` with `handle(array $filters, ?int $campusId)`,
   public `baseQuery()` for reuse/tests, and `counts(?int $campusId)`.
3. Add `deferReturns()` to `AcademicProgressionAuditController`: validate
   `search` (nullable string max 255), `per_page` (nullable int 1-100),
   `bucket` (nullable, in overdue/upcoming/waiting); render
   `Admin/Reports/AcademicProgressionAudit/DeferReturns` with `rows`, `counts`, `filters`.
4. Register route `GET reports/academic-progression/defer-returns`,
   name `reports.academic-progression.defer-returns`, middleware
   `can:view_student_action`.
5. Add `DeferReturnWatchlistExport` over `baseQuery()` (same filters + campus scope as
   the page) and `exportDeferReturns()` returning
   `ExcelExportService::download($export, 'defer_return_watchlist_'.date('Y-m-d_H-i'))`.
   Register `GET .../defer-returns/export`, name
   `reports.academic-progression.defer-returns.export`, same `can:view_student_action`.
   Columns: bucket, severity, student code, student name, campus, action type,
   anchor semester code, anchor start date, days elapsed, student status.
   `baseQuery()` returns a `Illuminate\Database\Query\Builder`, not Eloquent — if
   maatwebsite's `FromQuery` rejects it, fall back to `FromCollection` over
   `->get()`; the result set is small (~55 rows today) so either is safe.
6. Extend the web-routes test: 200 for a permitted user on both routes, 403 without
   the permission; assert the export responds with a spreadsheet content type.
7. Run `./scripts/dev.sh artisan test tests/Feature/Academic/DeferReturnWatchlistQueryTest.php tests/Feature/Academic/Progression/ProgressionReportingWebRoutesTest.php`.

## Success Criteria

- [x] All Phase 1 test cases green
- [x] Both routes respond 200 with permission (403-without-permission not covered — this test file globally disables the `Authorize` middleware and always-allows via `Gate::before`, matching every sibling test in it; no route in the file asserts 403)
- [x] Export honours the active `bucket`/`search` filter and the campus scope
- [x] Query issues a bounded number of statements (list + counts), no per-row lookups
- [x] Repeat-deferral student yields exactly one row
- [x] Row with a null anchor date still returned, sorted last

## Risk Assessment

- **MariaDB `DATEDIFF` / UNION ordering quirks** — the union must be wrapped in a
  `fromSub` before ordering (the missing-decisions query already does this); verify
  ordering with a test that spans both legs, not just one.
- **`students.status` string drift** — statuses are validated strings, not a DB enum
  (repo convention), so a typo fails silently as an empty bucket. Derive both from
  `StudentActionType::targetStatus()`; a test asserting a `deferred` student appears
  will catch drift if the enum mapping ever changes.
- **Export diverging from the page** — build the export off the same `baseQuery()`
  with the same filters, never a second hand-written query.
- **Whole-suite noise** — `tests/Feature/Academic` has a known unrelated red
  (`GetStudentAttendanceQueryTest`, class_sessions CHECK constraint) that aborts a
  full-directory run. Run the two files explicitly.
