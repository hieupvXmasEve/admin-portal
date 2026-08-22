---
phase: 6
title: "Sibling-field drift from the is_passed batch"
status: done
priority: P1
effort: "4h"
dependencies: []
---

# Phase 6: Sibling-field drift from the is_passed batch

> **Runs before Phase 5.** Phase 5 recomputes `credit_points_earned` from the
> transcript; the 9 rows fixed here are currently wrong in the transcript, so
> correcting them first is what keeps Phase 5's output clean. Execution order is
> 3 → 1 → 2 → 4 → **6** → 5.

## Overview

The unattributed batch at 2026-06-02 15:20:18 flipped `is_passed` true→false in isolation and left its sibling fields untouched. Three consequences, all traced to that one write:

| Symptom | Rows | Real defect |
|---|---|---|
| `AcademicRecord::isPassed()` returns true for a student who failed | 162 | The method derives "passed" from `completion_status`, which does not mean "passed" |
| `completion_status = 'completed'` while `is_passed = false` | 212 | **Not a data error** — see semantics decision below |
| `credit_points_earned > 0` while `is_passed = false` | 9 | Data error: a failed unit awarded credits |

**Semantics decision (user, 2026-08-15, confirmed as the intended business meaning): `completion_status` means "đã học xong", not "đậu".** That matches the primary writer, `CourseCompletionService.php:315`, which sets `'completion_status' => 'completed'` unconditionally right next to `'is_passed' => $finalPassed`. Pass/fail lives in `is_passed`. So the 212 rows are correct as they stand and **must not be backfilled**; what must change is every place that reads pass/fail out of `completion_status`.

Verified cross-tab of `academic_records`: `completed`+passed 1912, `completed`+failed 212, `in_progress` 816, **`failed` 0 rows** — the enum value exists but nothing has ever carried it.

## Requirements

- Functional: pass/fail is read from `is_passed` only. No production code infers it from `completion_status`.
- Functional: no writer produces `completion_status = 'failed'` any more, so the vocabulary matches the decided meaning.
- Functional: the 9 rows where a failed unit carries earned credits are corrected in **both** `academic_records` and `transcript_entries`.
- Non-functional: the correction tool follows the repo's write-gate convention — `--dry-run` / `--commit` mutual exclusion as in `ApplyGradingSchemePackCommand.php:49`.
- Non-goals: no backfill of the 212 `completion_status` rows (they are correct under the decided semantics); no migration to drop the unused `'failed'` enum value (leaving it costs nothing and the plan stays migration-free); no change to how `CourseCompletionService` writes.

## Architecture

Three code corrections plus one data correction.

**1. `AcademicRecord::isPassed()`** (`app/Models/AcademicRecord.php:286`) currently reads:

```php
return $this->override_pass || ($this->completion_status === 'completed' && $this->grade_points > 0);
```

Under the decided semantics this is simply the wrong source. It should consult `is_passed`, keeping the manual `override_pass` escape hatch. Of the 212 failed-but-completed rows, 162 have `grade_points > 0`, so today this method reports those 162 as passed. Two real callers: `ModuleProgressService.php:74` (`$allPassed = $academicRecords->every(...)`) and `ModuleGradeCalculator.php:47`.

**2. `AcademicRecord::isFailed()`** (`:291`) reads `completion_status === 'failed'`, which is false for every row in the database — a method that can never return true. Zero callers today, so it is a trap rather than an active bug. Fix it to the `is_passed` source alongside `isPassed()` rather than leaving an always-false method in a model others will reach for.

**3. `CompleteExamResitAttemptAction.php:308`** writes `$isPassed ? 'completed' : 'failed'` — the only writer that still uses the pass/fail meaning. Align it to write `'completed'` unconditionally, matching `CourseCompletionService`.

**4. `TranscriptEntry::getCompletionStatusAttribute()`** (`app/Modules/Academic/Progression/Models/TranscriptEntry.php:54-57`) derives `is_passed ? 'completed' : 'failed'`. This accessor is the *sole reason* the reconciliation audit reports 213 `HUB-001` exceptions: it compares a transcript using pass/fail semantics against records using finished semantics. Aligning it to the decided meaning removes all 213 exceptions structurally, without touching a single row of data.

**5. The 9 data rows.** Correct `credit_points_earned` to 0 where `is_passed = false`, in `academic_records` and `transcript_entries` alike. Direct correction rather than re-running Recalculate: Recalculate performs a full Canvas re-pull and would change far more than these nine fields.

Affected rows (verified): `ar` 3130 (student 505), 3159 (570), 3776 (425), 3783 (407), 4338 (505), 4339 (515), 4353 (622), 4361 (519), 4370 (547). Note 494 rows have `is_passed = true` with `credit_points_earned = 0` — all of them 0-credit units, legitimate, and out of scope.

## Related Code Files

- Modify: `app/Models/AcademicRecord.php` (`isPassed()` at `:286`, `isFailed()` at `:291`)
- Modify: `app/Modules/Academic/Delivery/Actions/CompleteExamResitAttemptAction.php` (`:308`)
- Modify: `app/Modules/Academic/Progression/Models/TranscriptEntry.php` (`getCompletionStatusAttribute()` at `:54-57`)
- Create: `app/Console/Commands/Academic/FixFailedRecordEarnedCreditsCommand.php` — sibling convention directory; `--dry-run` / `--commit` gate per `ApplyGradingSchemePackCommand.php:49`
- Read-only callers to re-check after the change: `app/Services/ModuleProgressService.php:74`, `app/Services/ModuleGradeCalculator.php:47`
- Create test: `tests/Feature/Academic/Gpa/PassFailSourceOfTruthTest.php`
- Create test: `tests/Feature/Academic/Gpa/FixFailedRecordEarnedCreditsCommandTest.php`
- Migration: none. Route: none. FormRequest: none. Frontend: none.

## Implementation Steps

1. Failing tests first: a record with `is_passed = false`, `completion_status = 'completed'`, `grade_points > 0` returns false from `isPassed()` and true from `isFailed()`; `override_pass = true` still forces `isPassed()` true; the resit action writes `'completed'` on a failed resit; the transcript accessor no longer returns `'failed'`.
2. Apply the four code corrections.
3. Re-check `ModuleProgressService` and `ModuleGradeCalculator` behaviour under the corrected method — these two are the reason the 162 rows matter, so assert their output changes for a failed record.
4. Write the correction command with the dry-run gate; failing test first, covering that it touches only `is_passed = false` rows and updates both tables.
5. Run `--dry-run` on dev, confirm exactly the 9 expected rows, then `--commit`.
6. Re-run `academic:audit-progression-reconciliation --all` and confirm `HUB-001` drops from 213 to 0 and the `*_credit_points_earned` divergences clear.
7. Run: `./scripts/dev.sh artisan test tests/Feature/Academic/Gpa`

## Todo

- [x] Tests red then green
- [x] `isPassed()` / `isFailed()` read `is_passed`; module callers verified (both module suites green)
- [x] Resit action and transcript accessor aligned to "finished" semantics
- [x] Correction command written with dry-run gate, tests green (points + hours)
- [x] Dry run shows exactly 9 rows (ar 3130..4370); **--commit is an operator step** (not run here)
- [ ] Audit shows HUB-001 = 0 (real-data check after deploy; reconciliation unit test green)

## Success Criteria

- [x] No production code infers pass/fail from `completion_status` (incl. CourseStatisticsService, CreditProgressService, CurriculumService — beyond enumerated scope, user-approved)
- [ ] `academic:audit-progression-reconciliation --all` reports zero `HUB-001` exceptions
- [ ] Zero rows remain with `is_passed = false` and `credit_points_earned > 0`
- [ ] The 212 `completion_status = 'completed'` + `is_passed = false` rows are untouched

## Risk Assessment

- Changing `isPassed()` changes module-progress and module-grade outcomes for the 162 affected records — that is the intended correction, but it is a visible behaviour change for those students' progress views. Assert it explicitly in tests rather than discovering it in production.
- `GradeFilterRequest.php:46` advertises `failed` as a filter value that will now never match anything. Cosmetic; note it, and decide separately whether to drop it from the filter vocabulary.
- The unused `'failed'` enum value stays in the column. Nothing writes it after this phase, but nothing prevents a future writer from reintroducing the ambiguity either. The tests added here are the guard.
- Correcting the transcript accessor changes what `GetStudentRegistrationsQuery:99` shows students for failed units (from `failed` to `completed`). Under the decided semantics that is correct — the unit was finished — and pass/fail is carried by other fields on the same payload. Confirm the student-facing view still communicates failure clearly before shipping.

## Scope additions (post-review, user-approved 2026-08-21)

Grep + review found three more production readers inferring pass/credit from
`completion_status`, beyond the four enumerated corrections. All fixed to read
`is_passed`, satisfying success criterion #1 repo-wide:

- `app/Services/CourseStatisticsService.php` — `students_passed` (SQL) + `calculatePassRate()` now read `is_passed`.
- `app/Services/V1/Student/CreditProgressService.php` — `courses_failed` = completed + `is_passed=false`; `success_rate` = `is_passed=true` share.
- `app/Services/V1/Student/CurriculumService.php::determineStudyStatus()` — the `completed` branch now splits on `isPassed()`; a finished-but-failed unit takes the retake/failed path (previously unreachable dead `'failed'` branch).

H1 (reviewer): the correction command also zeroes `credit_hours_earned` on
academic_records (paired with `credit_points_earned`, matching the resit
writer's "failed => both zero" invariant); `transcript_entries` has only the
points column. Same 9 rows. Commit logs the affected ids (mass update bypasses
the model audit trail).

Tests added: `PassFailSourceOfTruthTest`, `PassFailStudentViewsTest`,
`FixFailedRecordEarnedCreditsCommandTest`. `['completed','failed']` "resolved"
checks (ModuleProgressService, ModuleGradeCalculator, AcademicRecord scope) are
finished-checks paired with `isPassed()`, not pass-inference — left as-is.
