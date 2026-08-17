---
phase: 5
title: "Guard test and drift probe"
status: done
priority: P1
effort: 2h
dependencies: [0, 1, 2, 3, 4]
---

> **Done 2026-08-17.** Guard whitelist: 18 entries, set-equal against a live
> `File::allFiles(app/)` scan (zero unexpected, zero stale) — matches phase
> 0's classification exactly. Receiver anchoring caught its own false
> positives during build (bare `->status` on unrelated models once
> `app/Models/` was a blanket candidate; a proximity-unbound `whereHas(...)`
> window crossing into an unrelated later scope method) — both fixed by
> tightening to import-based candidacy plus bounded proximity windows,
> verified empirically against the real codebase, not assumed. The guard
> caught one genuine, previously-unfixed gap:
> `EloquentSemesterEnrollmentEligibilityReader.php:49` was classified
> "migrate, phase 1" in phase 0 but never actually migrated — fixed here.
> Drift probe (`a5-drift-probe.json`) against dev `asia`, read-only, no
> `--env=testing`: all three SQL-vs-PHP symmetric differences empty (active,
> class-roster-active, financial), dashboard tally sums to 235 (not 229),
> and drift is exactly the 15 known students from `a4-baseline.json` — zero
> unexplained. ADR-0033 amended with the canonical-status decision and the
> binding highest-id tie-break; no docs-site page mentions
> `transfer_count`/`active_count`, so that candidate is skipped per
> documentation-management.md ("no surface exists, skip and record that").
>
> **Residual, accepted:** the guard still cannot run automatically — no CI
> in this repo. Stated as the honest ceiling, not implied coverage.

# Phase 5: Guard test and drift probe

## Overview

The repo has **no CI running tests** (`.github/workflows` is docs + deploy only).
The guard arch test is the only mechanical barrier against a `students.status`
read creeping back — and only if someone runs it.

Red-team rewrote both deliverables: the guard was **broken in both directions**
(C4) and the probe was **tautological and vacuous** (M1).

## Requirements

- [x] Guard matches the SQL shapes the plan itself migrates (C4).
- [x] Guard ignores comment-only mentions (C4).
- [x] Guard distinguishes `Student` model reads from `StudentReference` **DTO** reads (C4).
- [x] Whitelist = phase 0's classification, not an invented number.
- [x] Probe compares the **flipped surfaces**, not the reader against the column (M1).
- [x] Probe gates on diff vs. phase 0's **recorded** baseline (C2).

## Architecture

### C4 — the guard was broken both ways

**Missed shapes** — all of them things this plan migrates:

| Shape | Example |
|---|---|
| `whereHas('student', fn($q) => $q->where('status', …))` | `GetLifecycleDueExceptionSummaryQuery.php:37-39`, `CourseCompletionService.php:615-617` |
| `Student::where('status', …)` without `::query()` | `UpdateDeferredStudentsCourseRegistrations.php:45` |
| `->select('status')->groupBy('status')` | `DashboardStatsService.php:69-70` |
| `whereNotIn('status', …)` inside a model scope | `Student.php:535,543` |

**False positives** — comment-only mentions in
`SendDueItemRemindersAction.php:57`, `BillingExceptionCollector.php:495`,
`LifecycleStatusTimeline.php:16`,
`GetStudentLifecycleCohortMatrixQuery.php:22`.
**Fix:** strip comments with `token_get_all()`, dropping `T_COMMENT` and
`T_DOC_COMMENT`, before matching.

**The worst failure — path-set equality blinds the guard at the win.**
`$student->status` where `$student` is a **`StudentReference` DTO**
(`app/Shared/Contracts/StudentRegistry/DTO/StudentReference.php:24`) is the
migration's *success*, not a violation. The 5 Delivery gates read exactly that.
Path-set equality would force whitelisting those 5 files permanently — blinding
the guard in the most important place in the codebase.

**Fix — anchor on the receiver type.** A file is a candidate only if it
`use App\Models\Student` (or is in `app/Models/`) **and** contains a matching
read. Files that import only the DTO are not candidates. This is heuristic, not
type inference — see the ponytail note below.

**Dropped criteria** (both unmeasured, both from the pre-red-team revision):
"whitelist ≤ 14 paths" and "`rg … | wc -l` equals whitelist length".

> `// ponytail:` receiver anchoring is by import, not real type resolution. A
> file importing both `App\Models\Student` and the DTO gets flagged on a DTO
> read. Accepted — that combination is rare and a false positive costs one
> whitelist line, whereas the alternative (real type inference) is a static
> analyser nobody will maintain. Upgrade only if it actually misfires.

### Whitelist

**Comes from phase 0's classification table** — buckets `whitelist-fallback`,
`whitelist-historical`, `whitelist-writer`. Do not re-derive it here. Every
entry carries its bucket and reason as a trailing comment.

Notable, so nobody "cleans them up":

| Path | Bucket |
|---|---|
| `StudentLifecycleStatusReader.php:30-36` | fallback — **is** the mechanism |
| `EloquentProgramEnrollmentReader.php:178`, `MaterializeProgramEnrollmentAction.php:88-100,123`, `ProcessEgcCourseResultsAction.php:640` | writer / intake seed |
| `StudentAuditableModel.php:206` | writer — logs the column's own value |
| `ListEgcRetakeAdjustmentsQuery.php:72` + `AcademicFinanceChargeSourceGateway.php:1016`, `StudentActionExcelRowMapper.php:132`, `CourseCompletionService.php:616` | **historical** (user decision 2) |
| `UpdateDeferredStudentsCourseRegistrations.php:45` | **descoped** (H6) — whitelist with the descope reason |
| `LifecycleDueItemPredicate.php:78,100`, `EloquentStudentCollectionEligibilityReader.php:40`, the Shared scope, both dashboards' `COALESCE(…, students.status)` | fallback |
| `Api/AuthController.php`, `RegisterAdmittedStudentAction.php` | writer (create-time) |

`StudentService::updateStudentStatus()` is **deleted in phase 4** — it must
**not** appear.

### M1 — the probe was tautological

The old probe compared `StudentLifecycleStatusReader` against
`students.status`, but the reader's own fallback arm **is** `pluck('status','id')`
— so `fallback_broken == []` was true **by construction**. And vacuous anyway:
**0 of 235 dev students take the fallback branch.** It never touched
`scopeActive()`, `scopeClassRosterActive()`, the Finance scopes, or the
dashboard CASE.

**Replacement — symmetric differences over the surfaces that actually flipped.**
Chunk the id list; do not load 235+ ids into one `whereIn` on prod-sized data.

| Comparison | Expected |
|---|---|
| `Student::active()->pluck('id')` vs per-student `isActive()` verdict | symmetric difference **empty** |
| `Student::classRosterActive()->pluck('id')` vs per-student `isClassRosterActive()` | empty |
| Finance `whereFinancial` scope id set vs `statusesFor()` ∈ `FINANCIAL_STATUSES` | empty |
| dashboard per-status aggregate vs `statusesFor()` tally | equal per key, and total = 235 |

These are the SQL-vs-PHP agreement checks the old probe never made. The
fallback branch gets a **fixture-level** test instead (a student with no
enrollment through all four chokepoints **and** `reference()`), since dev data
cannot exercise it.

### C2 — gate on the recorded baseline

Phase 0 (A4) pastes the pre-change JSON into the PR. This phase's gate is
**diff vs. that JSON**, containing only the 15 known students, not a predicted
transition list. The old gate ("every key is `deferred->*` or withdrawn-derived")
fails on day one against the measured 5 transition classes.

### Docs

Per `.claude/rules/documentation-management.md`, update only if user-visible
behavior, architecture, or a durable maintainer decision changed. **Three
qualify — decide, don't auto-write:**

1. `transfer_count` → 0 and the `active_count` SQL change are **user-visible metric changes**. If documented in `docs-site/`, that page needs a line. Find the owning page via the docs navigation; do not assume a path.
2. "Canonical status = `program_enrollments` primary row; `students.status` is write-dead, read only as fallback **or as the deliberate historical source for 3 EGC rules**" is a **durable maintainer decision**. Check `docs/adr/` for an existing student-lifecycle ADR to amend before creating one.
3. The **highest-id tie-break** (phase 0, A2) belongs in the same ADR — it is a contract three subsystems must share.

If no surface exists, skip and record that.

## Related Code Files

**New:** `tests/Feature/Architecture/LegacyStudentStatusReadGuardArchTest.php`

**Read-only:** phase 0's classification table; every whitelisted path;
`app/Shared/Contracts/StudentRegistry/DTO/StudentReference.php:24`;
`tests/Feature/Architecture/ProgramEnrollmentBoundaryArchTest.php:5-17` (the
repo's Pest + `file_get_contents` + regex pattern to follow).

**Possibly modified:** one `docs-site/` page and/or one `docs/adr/` entry — path
resolved at implementation time.

## Implementation Steps

1. Confirm phase 0's A4 baseline JSON is in the PR body. If not, phase 5 cannot gate.
2. Re-run the probe (new form, per M1) after phases 1–4 merge.
3. Take phase 0's classification table as the whitelist. Reconcile against `git status` — phases 1–4 removed entries.
4. Write the guard: comment stripping (`token_get_all`), receiver anchoring by import, the 4 missed SQL shapes, set **equality** against the whitelist, failure message printing both unexpected additions and stale entries.
5. **Prove the guard works — mandatory.** Temporarily add `$student->status;` to a non-whitelisted file that imports `App\Models\Student`; confirm red with a useful message; revert. Then add the same line to a DTO-only file; confirm it stays **green** (the receiver anchoring works).
6. Decide the docs question (3 candidates above).
7. `./scripts/dev.sh artisan test tests/Feature/Architecture/`

## Success Criteria

- `./scripts/dev.sh artisan test tests/Feature/Architecture/` → **green**, all tests, including `DomainBoundaryArchitectureTest` and the new guard.
- Step 5 executed **both ways**: red on a `Student`-model violation (message names the file), **green** on a DTO-only read. Recorded in the PR.
- Guard matches all 4 previously-missed SQL shapes — assert by pointing the matcher at the 4 named example files and confirming each is either flagged or whitelisted-with-reason.
- Guard produces **zero** hits in the 4 comment-only files.
- Whitelist entry count **equals phase 0's whitelist-bucket count** — no invented ceiling.
- Probe: all four symmetric-difference comparisons **empty**; dashboard tally total = **235**.
- Probe diff vs. phase-0 baseline contains **only the 15 known students**. Zero unexplained.
- Fixture-level fallback test green (no-enrollment student through 4 chokepoints + `reference()`).
- `rg -n "updateStudentStatus" app` → 0 hits and the path is absent from the whitelist.

## Risk Assessment

| Risk | L×I | Mitigation |
|---|---|---|
| **Guard never run — no CI** | High × High | The honest ceiling; not solvable here. Mitigate by keeping it in `tests/Feature/Architecture/` (small, fast, routinely run) and putting the command in the PR template. Stated plainly rather than implying coverage that does not exist. |
| **Guard blinds itself on the 5 Delivery gates** (C4) | High × High | Receiver anchoring + step 5's DTO-stays-green check. Path-set equality alone would have permanently whitelisted the migration's biggest win. |
| **Guard too broad → deleted within a week** | Med × High | Comment stripping + receiver anchoring. A bare `->status` grep hits 637 files. |
| **Whitelist invented rather than derived** | Med × Med | Comes from phase 0; the count criterion ties them together. |
| **Probe run against the wrong DB** | Med × Critical | **Never pass `--env=testing`** — there is no `.env.testing`, so it targets the dev `asia` database. The probe is read-only, but the flag is the hazard. |
| **Unexplained drift dismissed as noise** | Med × High | "Only the 15 known students" is a hard criterion. An unpredicted key means the enrollment data has an unmodelled case — stop and investigate. |
| **Fallback branch remains untested** | High × Med | 0/235 dev students exercise it, so the probe **cannot**. The fixture-level test is the only coverage; it is a success criterion, not optional. |

### Rollback

Deleting the test file is the whole rollback — additive, touches no application
code. If the docs step wrote a page, revert that separately.
