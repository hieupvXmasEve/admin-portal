---
title: "Defer Return Watchlist"
description: "Report surfacing deferred / waiting-for-course students whose return semester has arrived or is approaching, so đào tạo never loses track of them."
status: done
priority: P1
effort: "1d"
tags: [academic, progression, report]
created: 2026-08-11
---

# Defer Return Watchlist

## Overview

`/reports/student-actions` is an audit log — it answers "what actions were recorded",
not "which students are overdue to come back". Today nothing surfaces a deferred
student whose return semester already started, so a student can sit in `deferred`
forever without anyone noticing.

This plan adds a read-only worklist report at
`reports/academic-progression/defer-returns` listing every student still on hold,
bucketed by whether their return semester is past due, upcoming, or absent.

No new tables, no migrations, no jobs, no notifications. One query + one controller
method + one route + one Inertia page, following the existing
`GetMissingDecisionReportQuery` / `MissingDecisions.vue` pattern.

## Context & Evidence (verified 2026-08-11, dev DB)

| Fact | Source |
|---|---|
| `ACADEMIC_DEFER` requires `from_semester_id` + `return_semester_id` | `app/Enums/StudentActionType.php:88` |
| `WAITING_COURSE_OPENING` requires `from_semester_id` + `egc_defer_from_block_number` — **no return semester by design** | `app/Enums/StudentActionType.php:90` |
| `ACADEMIC_RESUME` only legal from `deferred` / `pending_course_opening` | `app/Modules/Academic/Progression/Actions/TransitionProgramEnrollmentAction.php:139` |
| → therefore `students.status` is the live "still on hold" truth; resume/dropout/transfer flips it and the row self-clears | derived from above |
| 49 `ACADEMIC_DEFER` rows with status `deferred`; 8 with status already moved on | `student_action_logs` ⋈ `students` |
| 6 `WAITING_COURSE_OPENING` rows, `return_semester_id` NULL on 100% of them, all status `pending_course_opening` | same |
| All 8 semesters have non-null `start_date`/`end_date` (columns nullable, so still guard) | `semesters` |

Brainstorm decisions accepted by user:
- Surface = dedicated report page (not a tab on the audit log).
- Overdue boundary = return semester `start_date` passed, **no grace period**.
- Scope = `ACADEMIC_DEFER` + `WAITING_COURSE_OPENING`.
- No email/notification/scheduler.
- `WAITING_COURSE_OPENING` gets its own bucket measured by age-on-hold, since it has no return semester.

**Deviation flagged:** the brainstorm sketched a separate "Due now" bucket. The chosen
overdue rule (start_date passed) absorbs it — a student in their return semester right
now *is* overdue. "Due now" therefore becomes a severity flag inside OVERDUE
(`in_semester` while `end_date >= today`, `semester_ended` after), not a fourth bucket.

**Route placement deviation:** brainstorm said `/reports/defer-returns`. Plan uses
`reports/academic-progression/defer-returns` so it sits beside its siblings
`missing-documents` / `missing-decisions` in the same prefix, controller, and menu group.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Overdue deferred students are visible and countable, campus-scoped | P1 |
| 2 | Upcoming returns are visible early enough to prepare | P1 |
| 3 | `WAITING_COURSE_OPENING` students on hold are not silently invisible | P2 |
| 4 | Rows self-clear when the student's status changes — zero manual upkeep | P1 |

## Buckets

| Bucket | Rule | Sort |
|---|---|---|
| `overdue` | `ACADEMIC_DEFER`, status `deferred`, `return_semester.start_date <= today` | longest overdue first |
| `upcoming` | `ACADEMIC_DEFER`, status `deferred`, `return_semester.start_date > today` | soonest first |
| `waiting` | `WAITING_COURSE_OPENING`, status `pending_course_opening` | longest on hold first |

Severity inside `overdue`: `in_semester` when `return_semester.end_date >= today`, else
`semester_ended`. Null semester dates (column is nullable) sort last and render `—`
rather than being dropped from the list.

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Watchlist Query And Route](./phase-01-watchlist-query-and-route.md) | Done |
| 2 | [Phase 2: Report Page And Nav](./phase-02-report-page-and-nav.md) | Done |

## Success Criteria

- [x] Defer whose return semester already started + status still `deferred` → appears in `overdue`
- [x] Same student after `ACADEMIC_RESUME` (status flips) → disappears from the report
- [x] Defer with a future return semester → `upcoming`, never `overdue`
- [x] `WAITING_COURSE_OPENING` on hold → `waiting` bucket with age-on-hold, not dropped
- [x] Campus scoped by `session('current_campus_id')`, gated by `can:view_student_action`
- [x] Bucket counts shown on the page; each row carries one explicit action button to the student's lifecycle tab
- [x] Excel export respects the active filters and campus scope
- [x] `./scripts/dev.sh artisan test tests/Feature/Academic/DeferReturnWatchlistQueryTest.php` green

## Out of Scope

- Email / scheduled notification / sidebar badge count (explicitly deferred by user)
- `ADMISSION_DEFERRAL` (different status law, uses `intended_intake_semester_id`)
- Adding `return_semester_id` to `WAITING_COURSE_OPENING` (would change the intake contract)
- Any edit/resolve action inline; the page links out to the existing student action flow
- Clickable table rows — validation chose a single explicit action button instead

## Validation Log

### Session 1 — 2026-08-11

**Verification Results (Light tier, Fact Checker)**
- Claims checked: 10 | Verified: 9 | Failed: 0 | Unverified: 0 (1 resolved with correction)
- Correction: Phase 1 originally sourced the `deferred` / `pending_course_opening`
  literals from `TransitionProgramEnrollmentAction`, which hardcodes them as raw
  strings (`:124`, `:129`, `:140`). Authoritative source is
  `StudentActionType::targetStatus()` (`app/Enums/StudentActionType.php:69,71`),
  which already maps `ACADEMIC_DEFER → deferred` and
  `WAITING_COURSE_OPENING → pending_course_opening`. Phase 1 updated to derive
  each leg's status filter from the enum, keeping action↔status coupling in one place.
- Verified: `AcademicProgressionAuditController::missingDecisions` (`:103-119`),
  route group at `app/Modules/Academic/routes/web.php:441`,
  `tests/Feature/Academic/MissingDecisionReportQueryTest.php`,
  `TransitionProgramEnrollmentAction:139`, `MissingDecisions.vue:177`
  (`:pagination-data`), `routes.ts:249`, `menu-sidebar.ts:280`,
  `useTableFilters(baseIndexUrl, initialFilters, only)` (`useFilters.ts:239`),
  `StudentActionLogsExport` + `ExcelExportService` export precedent.

**Decisions**
1. `upcoming` horizon → **all future semesters**, no cutoff. List is small; a window
   would hide long-dated deferrals, which is the exact failure mode this report exists
   to prevent.
2. Row interaction → **one explicit action button per row**, not a clickable row.
   Diverges from `MissingDecisions.vue`, which makes the whole `<tr>` clickable.
3. Excel export → **in scope, Phase 1** (was previously out of scope).

### Whole-Plan Consistency Sweep

Re-read `plan.md`, `phase-01-*.md`, `phase-02-*.md` after propagation.
- Export moved out of "Out of Scope" into Phase 1 scope, success criteria, and risks.
- Row-click removed from Phase 2 requirements and from the `MissingDecisions.vue`
  copy instruction; "copy the sibling page" now carries an explicit exception so the
  implementer does not reintroduce `@click` on `<tr>`.
- Status-literal instruction replaced in Phase 1 architecture and risk sections.
- `Due now` and `/reports/defer-returns` survive only inside the two labelled
  "Deviation flagged" notes, which exist to record what changed from the brainstorm.
  No phase file treats either as a live instruction.
- No remaining reference to export being deferred.
- Unresolved contradictions: none.

### Implementation — Code Review Fixes (post-Phase 2)

- `severity` CASE now treats a NULL `anchor_end_date` as unknown (`null`,
  renders `—`) instead of falsely asserting `semester_ended` — a NULL-`end_date`
  overdue row was showing a red "semester ended" badge for a date that was
  simply missing.
- Bucket-count cards labelled as "not filtered by search" — `counts()`
  intentionally ignores the search filter (plan only requires it be independent
  of the `bucket` filter and pagination), so the header badge and the three
  cards can legitimately show different totals when searching; the label makes
  that explicit instead of reading as a bug.
- Left the `semesters` join unscoped to soft-deletes (a trashed anchor semester
  still buckets the row) with a comment recording that this is deliberate —
  the student stays visible rather than silently disappearing.
- Browser click-through against dev data (Phase 2 step 5) not performed: this
  dev environment only has Google OAuth login and no local dev-login bypass
  exists in the repo. Verified instead via the two backend test files (24
  tests / 150 assertions) plus `assertInertia` component/prop assertions.

## Risks

| Risk | Mitigation |
|---|---|
| Student with multiple `ACADEMIC_DEFER` logs → duplicate rows | Take the latest defer log per student (max `id`); assert in test |
| Nullable semester dates → student silently missing from all buckets | Null dates sort last and render `—`; never filtered out |
| N+1 on student/semester/campus | Single joined query builder like `GetMissingDecisionReportQuery`, not Eloquent `with()` per row |
| `DataPagination` prop mismatch (past crash on Fee Monitor) | Use `:pagination-data` exactly as `MissingDecisions.vue:177` |

<!-- slug: defer-return-watchlist -->
