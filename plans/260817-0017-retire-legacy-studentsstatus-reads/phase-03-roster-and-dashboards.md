---
phase: 3
title: "Roster, auth and dashboards"
status: done
priority: P1
effort: 5h
dependencies: [0, 1]
---

> **Done 2026-08-17.** C1 fix landed centrally in `Student::lifecycleStatus()`
> (called by `isActive()`/`isClassRosterActive()`) — all 6 auth-path files
> (`StudentApiAuthorization`, `ParentStudentAccess`,
> `EloquentStudentPortalTokenRefresher`, `EloquentStudentImpersonationTokenIssuer`,
> Engagement `FormController`/`EventParticipationOperations`) needed **zero**
> direct edits; they already called `$student->isActive()` and now get the
> fix for free. `StudentAuthLifecycleGateTest` proves all 4 auth paths deny a
> withdrawn-by-enrollment student. H4: Option 1 (deleted the PHP branches,
> SQL-only) — smaller diff, one source of truth by construction. H5: instead
> of a second hardcoded list that can drift again, `$expectedStatuses` gives
> stable keys but any actual value not in that list is also merged in, so
> `array_sum(by_status) === total` holds structurally, not just for today's
> known values. `'active' => 'Active'`/`'green'` uncommented in
> `StudentLifecycleStatusPresenter.php` (moved there in phase 0, not
> `Student.php` — same fix, correct location).

# Phase 3: Roster, auth and dashboards

## Overview

The largest and riskiest phase. Red-team rewrote its core design:

- **C1 (Critical):** the original "compute from the relation only if loaded,
  never lazy-load" design meant `isActive()` **never actually flips** — every
  auth path silently kept the dead column while `scopeActive()` (pure SQL) did
  flip. Result: a withdrawn-by-enrollment student disappears from every list yet
  **keeps portal login and impersonation**. Correctness must not depend on
  invisible caller state.
- **H4:** `CourseOffering` counts rosters via three branches selected by
  whichever relation happens to be loaded; eager-loading one of three produces
  different counts for the same offering on the same page load.
- **H5:** `by_status` silently sums to 229 vs `total` 235, and the one `active`
  student renders "Unknown".
- **H6:** `UpdateDeferredStudentsCourseRegistrations` is **descoped** — it mass-writes with no prior-value capture.
- **M2:** `active_count` key is **frozen**; the "rename to `enrolled_count`" suggestion is deleted.

## Requirements

- [x] The four `Student` chokepoints compare against the projection — **and actually flip on every path, including auth** (C1).
- [x] **No silent legacy fallback** when the relation is unloaded. Either resolve explicitly or lazy-load with a loud guard.
- [x] Auth-path tests: withdrawn student denied by API middleware, parent access, token refresh, impersonation (C1).
- [x] `CourseOffering`'s three roster-count branches agree (H4).
- [x] `by_status` sums to `total`; the `active` label renders (H5).
- [x] Response key `active_count` unchanged (M2).
- [x] `UpdateDeferredStudentsCourseRegistrations` **not** migrated (H6).
- [x] Two-primary-row fixture proves highest-id wins in PHP and SQL (C5).
- [x] Open question #3 (the NULL `study_stage` row) resolved before starting.

## Architecture

### C1 — the auth paths must genuinely flip

`isActive()` is polymorphic: it is defined on **≥6 models**
(`AcademicHold:108`, `BillingCycle:84`, `Lecture:263`, `CourseRegistration:218`,
`Semester`, `User`). A name grep yields ~20 hits of which only these are
`Student` receivers — **verify each receiver, do not trust the name**:

| Caller | Consequence if it keeps reading the dead column |
|---|---|
| `app/Http/Middleware/StudentApiAuthorization.php:33` | withdrawn student keeps API access |
| `app/Http/Middleware/ParentStudentAccess.php:109` | parent keeps access to a withdrawn student |
| `app/Modules/StudentRegistry/Support/EloquentStudentPortalTokenRefresher.php:19` | withdrawn student keeps refreshing portal tokens |
| `app/Modules/StudentRegistry/Support/EloquentStudentImpersonationTokenIssuer.php:21` | admin can impersonate a withdrawn student |
| `app/Models/Student.php:513` (`canRegisterForCourses()`) | registration gate stays legacy |
| `app/Modules/Engagement/Http/Api/Student/FormController.php:41,71,115` | **confirmed 2026-08-17** — `! $this->isStudentActor($student) \|\| ! $student->isActive()`, receiver is a Student |
| `app/Modules/Engagement/Actions/EventParticipationOperations.php:357` | **confirmed 2026-08-17** — `if (! $student->isActive())` guarding gold award |

**Not** Student receivers (do not touch): `AuthController.php:47` (`$user`),
`StudentMiddleware.php:35` (`$request->user()`), `LecturerApiAuthorization.php:61`,
`ApiActorPolicy.php:25`, `EventParticipantResource.php:84`.

**Design rule: ban the silent legacy fallback.** These are single-student auth
paths — one extra query is fine and correct. Two acceptable shapes:

- **(a) preferred** — resolve explicitly through `StudentLifecycleStatusReader` on the auth paths (1 query each), or
- **(b)** lazy-load the relation with a **loud** guard (log/throw in non-production when unloaded on a list path).

**Forbidden:** "if the relation isn't loaded, quietly use `$this->status`". That
is exactly the C1 defect — it makes correctness a function of whether some
distant caller remembered a `with()`.

> The original `// ponytail:` note ("silently falls back, the guard is the
> query-count test") is **deleted**. A silent fallback on an auth path is not a
> documented simplification, it is an auth bypass.

### The four chokepoints

| Method | Line | Kind |
|---|---|---|
| `Student::isActive()` | `app/Models/Student.php:520-523` | PHP |
| `Student::isClassRosterActive()` | `app/Models/Student.php:525-529` | PHP |
| `Student::scopeActive()` | `app/Models/Student.php:534-537` | SQL |
| `Student::scopeClassRosterActive()` | `app/Models/Student.php:542-549` | SQL |

SQL scopes use the **phase-0 Shared scope**. PHP methods use `lifecycleStatus()`.

`Student::lifecycleStatus()` mirrors `StudentLifecycleStatusReader::legacyStatus()`
(`:65-75`) and uses the relation when loaded; when unloaded it follows the (a)/(b)
rule above — never a silent column read.

### C5 — the relation encodes highest-id

`primaryEnrollment(): HasOne` + **`->latestOfMany('id')`** — **not**
`oldestOfMany`. Phase 0 pinned this. Multiple `is_primary = 1` rows are
schema-legal (the unique index covers only primary **+ active**), and dev has no
duplicates today, so **no existing test catches a divergence**. The
two-primary-row fixture in step 6 is the only enforcement.

### H4 — CourseOffering's three branches

`app/Models/CourseOffering.php:231-256` and `:258-284` count rosters via three
paths chosen by whichever relation is loaded:

- SQL path → `Student::scopeClassRosterActive()`
- PHP paths (`:242,251,269,279`) → `Student::isClassRosterActive()`

The original plan eager-loaded `primaryEnrollment` only on
`activeClassRosterRegistrations` (`:170`), not on `classRosterRegistrations` or
`courseRegistrations` — the relations used by `CourseStatisticsService`,
`CourseOfferingSplitController`, `LecturerCourseService`,
`LecturerAttendanceService`. Post-flip the **same offering returns different
counts on the same page load**.

**Pick one, record it:**

1. **Delete the PHP branches, keep only the SQL path** — smallest surface, one source of truth. *Preferred.*
2. Add `primaryEnrollment` to all three relation definitions **and** make the unloaded case loud.

Option 1 is the lazy-correct move if the PHP branches exist only to avoid a
query; check whether any caller genuinely lacks the SQL path first.

### Dashboards — M2 and H5

- `active_count` **response key is frozen.** Consumer
  `resources/js/components/dashboard/StudentDistribution.vue:17,23,169` does
  `sum + p.active_count` and is typed non-optional — renaming yields `NaN` on
  screen. **Change the SQL, keep the key.** The Vue file is read-only context
  for this phase.
  Since the projection never emits `'active'`, the SQL behind `active_count`
  becomes `projected_status IN Student::FINANCIAL_STATUSES`.
- `DashboardStatsService.php:77` `$expectedStatuses` lacks **`pending_course_opening`
  (6 students)** and **`active` (1)** which the projection does emit, while `:74`
  sums **all** keys → `by_status` totals 229 vs `total` 235, silently. **Extend
  the list** and assert `array_sum($byStatus) === $total`.
- `app/Models/Student.php:~620` `statusLabelFor()` has `// 'active' => 'Active',`
  **commented out** → that one student renders **"Unknown"** on every
  `StudentReference.statusLabel` surface. **Uncomment it** (and the matching
  `statusColorFor()` entry).
- `graduated_count` (`DashboardChartsService.php:59`) and `graduation_rate`
  (`:267-268`) pass through the CASE unchanged — safe.
- **`DashboardStatsService.php:160-162` is `getRoomStats()` reading `rooms.status`.**
  Not in scope. The advisory listed it in error.

### Descoped (H6)

`app/Console/Commands/UpdateDeferredStudentsCourseRegistrations.php:45` —
**do NOT migrate.** It mass-writes `registration_status = 'defer'` at `:128`
with **no prior-value capture**, and the target population shifts by 8 out / 2 in.
If it moves later it needs prior-value capture plus an old-vs-new target count in
the confirmation prompt. Record as deferred scope with this reason; phase 0
classifies it accordingly.

`app/Services/CourseCompletionService.php:616` is **whitelist-historical** (user
decision 2) — not migrated here either.

## Related Code Files

**Modified (exclusive):**

- `app/Models/Student.php` — `primaryEnrollment()` (`latestOfMany('id')`) + `lifecycleStatus()`; `:513`, `:520-523`, `:525-529`, `:534-537`, `:542-549`; uncomment `'active'` in `statusLabelFor()` **and** `statusColorFor()`
- `app/Models/CourseRegistration.php:148-165` — `classRosterStatus()` (`:154`)
- `app/Models/CourseOffering.php:170, 231-284` — per the H4 decision
- `app/Services/DashboardChartsService.php:56-60, 265-269`
- `app/Services/DashboardStatsService.php:68-78` (**not** `:150-173`)

**Modified (auth paths, per C1 option (a)):**

- `app/Http/Middleware/StudentApiAuthorization.php:33`
- `app/Http/Middleware/ParentStudentAccess.php:109`
- `app/Modules/StudentRegistry/Support/EloquentStudentPortalTokenRefresher.php:19`
- `app/Modules/StudentRegistry/Support/EloquentStudentImpersonationTokenIssuer.php:21`
- Engagement sites (receiver confirmed 2026-08-17: FormController `:41,71,115`, EventParticipationOperations `:357`)

**Eager-load / relation additions (per H4 decision 2 only):**
`CourseOffering.php:170`, `LecturerAttendanceService.php:438`,
`OperationalAttendanceService.php:143`, `EloquentCourseRosterReader.php:17`

**Read-only:** phase-0 Shared scope; `StudentLifecycleStatusReader.php:65-75`;
`ProgramEnrollment.php`; `resources/js/components/dashboard/StudentDistribution.vue:17,23,169`

**Tests:**

- `tests/Feature/Academic/Delivery/ClassRosterLifecycleStatusTest.php` — new
- `tests/Feature/Reporting/DashboardStatusProjectionTest.php` — new (**directory `tests/Feature/Reporting/` does not exist — create it**)
- `tests/Feature/Registry/StudentAuthLifecycleGateTest.php` — new (C1)

## Implementation Steps

1. **Resolve open question #3** — the single `active` enrollment with `study_stage = NULL`. It is the only source of a literal `active`. Blocking.
2. **Confirm phase 0** delivered the Shared scope, the highest-id contract, and the receiver-resolved `isActive()` consumer list.
3. **Baseline** (failing **names**): `tests/Feature/Academic/Delivery/`, `tests/Feature/Academic/RetakeCourse/`, `tests/Feature/Registry/`. **Never run `tests/Feature/Academic/` whole** — exit 255 on a `class_sessions` CHECK constraint.
4. **RED — auth (C1)** `tests/Feature/Registry/StudentAuthLifecycleGateTest.php`, all fixtures with materialized enrollments: a student whose **column says `intake_course`** but whose **enrollment is `withdrawn`** is denied by `StudentApiAuthorization`, denied by `ParentStudentAccess`, throws on portal token refresh, and is refused for impersonation.
5. **RED — roster** `ClassRosterLifecycleStatusTest.php`: drifted student appears active; no-enrollment student keeps legacy verdict; **query count exactly 1** for a 20-student roster; `classRosterStatus()`/`classRosterStatusLabel()` return projected values; **H4**: the SQL count and both PHP-branch counts for one offering are equal.
6. **RED — tie-break (C5)**: two `is_primary = 1` rows for one student (one older non-active, one newer) → both `Student::lifecycleStatus()` and the SQL twin resolve to the **highest-id** row.
7. Add `primaryEnrollment()` (`latestOfMany('id')`) + `lifecycleStatus()`; rewrite the four chokepoints; **no silent fallback**.
8. Apply the C1 auth-path fix (option (a) preferred).
9. Apply the H4 decision; record which option.
10. `CourseRegistration::classRosterStatus()` → projected status.
11. **RED→GREEN** `DashboardStatusProjectionTest.php`: SQL aggregate per status equals `StudentLifecycleStatusReader::statusesFor(all ids)`; and `array_sum($byStatus) === $total` (H5).
12. Rewrite both dashboard services; extend `$expectedStatuses`; uncomment the `active` label + color; **keep the `active_count` key**.
13. `./scripts/dev.sh composer exec -- pint <modified files>`

## Success Criteria

- `./scripts/dev.sh artisan test tests/Feature/Academic/Delivery/` → same failing names as baseline, zero new.
- `./scripts/dev.sh artisan test tests/Feature/Academic/RetakeCourse/` → same as baseline.
- `./scripts/dev.sh artisan test tests/Feature/Registry/StudentAuthLifecycleGateTest.php` → **green, 4 auth paths denied** for the withdrawn-by-enrollment student (C1).
- `ClassRosterLifecycleStatusTest.php` → green; query count **exactly 1** for 20 students; the three H4 count branches **equal**.
- Two-primary-row test → green, **highest id** wins in PHP **and** SQL (C5).
- `DashboardStatusProjectionTest.php` → green; SQL equals PHP reader per status; `array_sum($byStatus) === $total` (235, not 229).
- `rg -n "'active' => 'Active'" app/Models/Student.php` → **1 hit, uncommented** (H5).
- `rg -n "active_count" app/Services/DashboardChartsService.php` → key present and unrenamed (M2); `git diff resources/js/components/dashboard/StudentDistribution.vue` → **empty**.
- `git diff --stat` does **not** list `app/Console/Commands/UpdateDeferredStudentsCourseRegistrations.php` (H6) or `app/Services/CourseCompletionService.php` (whitelist-historical).
- No silent-fallback branch: `rg -n "\?\? \\\$this->status" app/Models/Student.php` → only inside the documented explicit-resolution path, reviewed by hand.

## Risk Assessment

| Risk | L×I | Mitigation |
|---|---|---|
| **Auth bypass — `isActive()` never flips while lists do** (C1) | High × Critical | Silent fallback banned. Step 4's 4-path denial test is the mechanical check and must be written before step 7. |
| **N+1 across every class roster** | High × High | Query-count assertion (exactly 1 / 20 students) written before the change. |
| **Same offering, different counts on one page load** (H4) | High × High | Step 5 asserts the three branches equal. Preferred fix deletes two of them. |
| **`by_status` silently 229 vs 235** (H5) | High × Med | `array_sum === total` asserted, not eyeballed. |
| **`active_count` renamed → `NaN` on the dashboard** (M2) | Med × High | Key frozen; Vue diff must be empty. |
| **Tie-break divergence invisible on dev** (C5) | Med × High | Dev has no duplicate primaries — only the synthetic two-row fixture catches it. |
| **`Student.php` edited by two phases** | Low × High | Phase 3 owns it exclusively. Phase 4 must not touch it. |
| **Mass-write with no prior-value capture** (H6) | — | Descoped. |
| **Fixtures without enrollments** | High × High | 235/235 dev students are materialized; a factory-default fixture tests only the fallback branch. Every fixture here materializes one. |

### Rollback

Revert in this order to avoid a half-state:

1. **Dashboards** (independent, no callers of the new SQL).
2. **Auth-path files** — reverting these alone is safe and restores the *pre-plan* behavior, not the C1 half-state.
3. **Chokepoints** on `Student.php`.
4. **Relation + `lifecycleStatus()`** last (additive; harmless if left).

`tests/Feature/Reporting/` is created by this phase — delete the directory if
rolling back fully. Eager-load additions are inert and may stay.

**Do not** revert the chokepoints while leaving the auth-path fixes, or vice
versa — that reproduces exactly the C1 split (lists flipped, auth not).
