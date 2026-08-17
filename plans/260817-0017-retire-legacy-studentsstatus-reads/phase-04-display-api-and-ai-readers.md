---
phase: 4
title: "Display, API and AI readers"
status: done
priority: P2
effort: 3h
dependencies: [0]
---

> **Done 2026-08-17.** Q5 resolved: `McpFieldAllowlistArchitectureTest` only
> checks `status` is an allowed field *name*; `McpSearchEntitiesToolTest` fully
> mocks `AiAcademicEntitySearchReader`, so no test asserts on the real
> resolved status *value* — migrating both AI readers is safe, not an
> external contract change. `tests/Feature/Mcp/` green (no known baseline
> failures). Bucket A/C verified untouched via `git diff --stat` +
> `git diff app/Shared/Contracts/Academic/DTO/AcademicEgcBlockData.php`
> (empty). `EventParticipantResource.php:47` — accepted the documented
> per-row cost (no batching seam inside a Resource; not threading a status
> map through every Engagement controller that builds this collection).
> `rg -n "updateStudentStatus" app routes tests` → 0 hits (M4, deleted).
> Two extra sites found during phase-0 inventory beyond the plan's original
> list — `StudentAcademicSummaryService.php:1133` and
> `CreateEgcStudentActionLogsCommand.php:166` — migrated too (both `migrate`
> bucket in the phase-0 classification table).

# Phase 4: Display, API and AI readers

## Overview

The remaining direct `$student->status` reads that are genuinely display.

**Red-team rewrote this phase's scope.** The original "bucket A — real gates"
was wrong: **user decision 2** rules that the three EGC sites keep the
historical column deliberately. Their migration steps and the tests asserting a
"drifted-in EGC gain" are **deleted** — they asserted the wrong outcome.

## Triage buckets

### Bucket A — whitelist-historical, do NOT migrate (user decision 2, binding)

The frozen column encodes **stage at admission / at the time the block or action
was created**, which is exactly what these rules ask. Migrating is a regression.

| Site | If migrated (wrong) |
|---|---|
| `app/Modules/Finance/Queries/Egc/ListEgcRetakeAdjustmentsQuery.php:72` + feeder `app/Modules/Academic/Support/AcademicFinanceChargeSourceGateway.php:1016` (`EgcBlockData.student_status`) | strips EGC retake discounts from 3 students |
| `app/Modules/Academic/Progression/Support/StudentActionExcelRowMapper.php:132` | rejects previously-valid import files |
| `app/Services/CourseCompletionService.php:616` | mutates `egc_students_count` on already-closed offerings |

**Consequence:** the `egcBlockData()` batching work and the two EGC tests from
the previous revision are **removed**. No N+1 fix is needed there because no
reader call is added.

### Bucket B — display, migrate

| File:line | Batch? |
|---|---|
| `app/Http/Controllers/Api/StudentController.php:133` | single |
| `app/Http/Controllers/Api/AuthController.php:107` | single |
| `app/Http/Controllers/Api/AuthController.php:217` | single |
| `app/Modules/StudentRegistry/Support/EloquentStudentPortalProfileReader.php:99` | single |
| `app/Modules/Academic/Support/AiAcademicStudentProfileReader.php:79` | single |
| `app/Modules/StudentRegistry/Support/EloquentStudentImpersonationTokenIssuer.php:22, :33` | single — see M3 |
| `app/Http/Resources/EventParticipantResource.php:47` | **see note** |
| `app/Modules/Academic/Delivery/Support/LecturerAttendanceService.php:110` | **batch** |
| `app/Modules/Academic/Progression/Queries/PreviewStudentDecisionBulkLinkQuery.php:58` | **batch** |
| `app/Modules/Academic/Support/AiAcademicEntitySearchReader.php:55` | **batch** |
| `app/Modules/Academic/Progression/Support/EloquentStudentDeferLifecycleReader.php:56` (`currentlyDeferred`) | **batch** |

`EventParticipantResource` is a Laravel Resource — one instance per row, no
batching seam. **Do not call the reader inside `toArray()`.** Resolve in the
collection/controller that builds it and pass a preloaded map, or accept and
document the per-row cost.

### Bucket C — whitelist, no edit

| File:line | Why |
|---|---|
| `app/Models/StudentAuditableModel.php:206` | audit observer logs the **column's own** old/new value on `isDirty('status')`. Logging a projected value it never wrote would corrupt the audit trail. |
| `app/Modules/Academic/Progression/Actions/ProcessEgcCourseResultsAction.php:640` | dry-run mirror of `MaterializeProgramEnrollmentAction:88-100,123` — seeds `study_stage` **from** the column to bootstrap an enrollment. Intake seed. |

### Bucket D — delete (M4)

`app/Services/StudentService.php:546-556` `updateStudentStatus()` — **0 callers**
in `app/`, `routes/`, `tests/`. **Dead code.** Delete it; do not whitelist it as
a "create-time writer" (the previous revision mislabeled it).

## Requirements

- [x] Open question #5 (MCP contract) answered before touching the two AI readers.
- [x] Bucket A untouched — verified by `git diff --stat`.
- [x] Bucket B migrated, batched where the table says so.
- [x] Bucket C untouched.
- [x] Bucket D deleted.
- [x] `app/Models/Student.php` **not** touched (phase 3 owns it).
- [x] Fixtures materialize enrollments.

### Open question #2 — RESOLVED, no work

`inactive` / `suspended` are **dead values** (0 students hold either). No
account-state carve-out. `BLOCKED_STATUSES` stays intact as a defensive
allow-list — the projection still emits `dropout`, `graduated`, `pending`,
`pending_course_opening`. A self-registered `inactive` student has no enrollment
→ fallback → still blocked. The `isActive()` **gate** itself belongs to phase 3.

### Open question #5 — MCP contract, still blocking

`AiAcademicEntitySearchReader` and `AiAcademicStudentProfileReader` feed the MCP
server. Check `tests/Feature/Mcp/McpFieldAllowlistArchitectureTest.php` and
`tests/Feature/Mcp/McpSearchEntitiesToolTest.php`: if either asserts on the
`status` **value** (not just its presence), changing the source is an external
contract change and needs its own PR note.

### M3 — corrected line refs for the impersonation issuer

Previously wrong. Actual:

- `:21` — `if (! $student->isActive())` — the **gate**. Belongs to **phase 3**, not here.
- `:22` — `'Cannot impersonate inactive student. Student status: '.$student->status` — message interpolation. **This phase.**
- `:33` — `'status' => $student->status` in the **returned payload** — never mentioned in any earlier revision. **This phase.** State in the PR whether this field is contract-visible to the admin frontend.
- `:17` — the issuer matches on email/student_id with **no campus scoping**. Pre-existing; note it, do not fix here.

## Related Code Files

**Modified:** every bucket-B file above, plus `app/Services/StudentService.php:546-556` (delete).

**Read-only / must not change:** bucket A (3 sites + feeder), bucket C (2 sites),
`app/Models/Student.php`, `app/Shared/Contracts/Academic/DTO/AcademicEgcBlockData.php`,
`tests/Feature/Mcp/McpFieldAllowlistArchitectureTest.php`.

**Tests:**

- `tests/Feature/Registry/StudentDisplayLifecycleStatusTest.php` — new
- ~~`tests/Feature/Finance/Egc/EgcRetakeAdjustmentLifecycleStatusTest.php`~~ — **deleted from scope** (asserted the wrong outcome)
- ~~`tests/Feature/Academic/Progression/StudentActionExcelRowMapperLifecycleTest.php`~~ — **deleted from scope** (same)

## Implementation Steps

1. **Answer open question #5.** Blocking for the two AI readers only; the rest may proceed.
2. **Baseline** (failing **names**): `tests/Feature/Academic/Progression/`, `tests/Feature/Mcp/`, `tests/Feature/Engagement/`, `tests/Feature/Registry/`.
3. **RED** — `tests/Feature/Registry/StudentDisplayLifecycleStatusTest.php`, fixtures with materialized enrollments: a drifted student's displayed `status` is the projected value on the portal profile reader and the student API; **query count exactly 1** for the batched list readers (`PreviewStudentDecisionBulkLinkQuery`, `EloquentStudentDeferLifecycleReader`).
4. Migrate bucket B, batching per the table.
5. Delete `StudentService::updateStudentStatus()` (bucket D); re-grep for callers immediately before deleting.
6. Verify buckets A and C untouched via `git diff --stat`.
7. **GREEN.** Re-run all four baselines.
8. `./scripts/dev.sh composer exec -- pint <modified files>`

## Success Criteria

- `./scripts/dev.sh artisan test tests/Feature/Academic/Progression/` → same failing names as baseline, zero new.
- `./scripts/dev.sh artisan test tests/Feature/Mcp/` → **green** (no known baseline failures; red here is an MCP contract break).
- `./scripts/dev.sh artisan test tests/Feature/Engagement/` → same as baseline.
- `tests/Feature/Registry/StudentDisplayLifecycleStatusTest.php` → green, query counts **exactly 1** on both batched readers.
- `git diff --stat` does **not** list: `ListEgcRetakeAdjustmentsQuery.php`, `AcademicFinanceChargeSourceGateway.php`, `StudentActionExcelRowMapper.php`, `CourseCompletionService.php`, `StudentAuditableModel.php`, `ProcessEgcCourseResultsAction.php`, `Student.php`.
- `rg -n "updateStudentStatus" app routes tests` → **0 hits** (M4).
- `git diff app/Shared/Contracts/Academic/DTO/AcademicEgcBlockData.php` → **empty** (no new DTO field).
- Impersonation payload `:33` decision recorded in the PR (M3).

## Risk Assessment

| Risk | L×I | Mitigation |
|---|---|---|
| **EGC sites migrated by reflex** ("it reads the column, migrate it") | High × High | User decision 2 is binding. Step 6's `git diff --stat` check is the mechanical guard. Migrating them strips discounts from 3 real students. |
| **MCP external contract silently changes** | Med × High | Q5 gates the edit; `tests/Feature/Mcp/` green is a hard criterion, not a baseline comparison. |
| **Bucket C edited** → corrupted audit trail | Med × High | Same `git diff --stat` check. |
| **N+1 in the 4 batched readers** | Med × Med | Query-count assertions on the two list readers. |
| **Reader call hidden inside `toArray()`** | Med × Med | Explicitly forbidden for `EventParticipantResource`. Resolve upstream or document. |
| **Deleting `updateStudentStatus()` breaks a dynamic caller** | Low × Med | Re-grep immediately before deleting (step 5), including string-name dispatch. |
| **Phase 3 collision on `Student.php`** | Low × Med | This phase never edits it. |

### Rollback

Per-file, all independent. No shared helper introduced, so no half-state.
Restoring `updateStudentStatus()` is a single revert; it had no callers, so
nothing depends on either state.
