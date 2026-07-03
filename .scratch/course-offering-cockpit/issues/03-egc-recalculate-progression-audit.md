# EGC recalculate progression audit

Status: ready-for-human (audited 2026-07-03; verdict unsafe, follow-up filed as issue 09)

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## What to build

This is investigation work, not a feature slice: deep-audit `CourseCompletionService`'s `processEgcProgression` path when called in recalculate mode (`MarkCourseOfferingCompletedAction::run($courseOffering, recalculate: true)`).

Recalculate's existing guarantees are: survey is not re-attached, only students whose pass/fail status changed are notified, and EGC progression receives the previous-status map. The audit must determine whether `processEgcProgression`'s level demotion/promotion behavior is correct and safe when scores change on recalculation — e.g. does a student's EGC level move correctly and exactly once when a grade correction flips their pass/fail status, without duplicate or missing progression events.

Do not expand scope to fix unrelated EGC progression issues found along the way. If the audit finds unsafe behavior specific to the recalculate path, file a separate follow-up issue describing the defect and proposed fix — do not fix it inside this issue or block the Recalculate button issue on a full fix; the follow-up issue becomes a new dependency for whoever picks up the Recalculate button work if the finding is severe enough to require a fix first.

## Acceptance criteria

- [x] Written audit findings covering: how `processEgcProgression` behaves in recalculate mode across level-demotion and level-promotion scenarios, using the previous-status map.
- [x] Explicit verdict: either "safe to ship Recalculate button as-is" or "unsafe — see follow-up issue [link/path]".
- [x] If unsafe, a follow-up issue is filed with a concrete failure scenario (inputs → wrong outcome) and enough detail for another agent to fix it independently of this audit.
- [x] Audit findings are recorded in this issue's Comments section (or linked doc) so the Recalculate action issue can reference the verdict.

## Blocked by

None - can start immediately

## Comments

### Audit findings (2026-07-03)

Traced the full recalculate path: `MarkCourseOfferingCompletedAction::run($offering, recalculate: true)` → `CourseCompletionService::finalizeCourse()` (captures `$previousStatusMap` from `is_passed` before re-finalizing academic records) → `EgcLevelProgressionService::processEgcProgression($offering, recalculate: true, $previousStatusMap)`.

Three scenarios were reasoned through and then verified empirically with a throwaway Pest reproduction (`.scratch/course-offering-cockpit/egc-recalc-audit-repro-test.php`, run via `./scripts/dev.sh artisan test`, not part of the shipped suite):

1. **Promotion on correction (fail → pass on recalculate): safe.** A student who failed on first finalize (level unchanged) and is corrected to passing on recalculate progresses exactly once, to the expected next level. The `previousStatus !== true` notify-guard in `processEgcProgression` correctly fires the notification. `progressStudentLevel()`'s `$oldLevel !== $currentUnitLevel` guard prevents any double-progression. **Verified: test passes.**

2. **Idempotent re-recalculate with no grade change: safe.** Recalculating a second time with the same passing grade does not create a duplicate `AcademicProgressionEvent` and does not re-notify (previous status already matches). It does produce a spurious "Level mismatch" warning in the result payload (because the student's level has moved past the unit's level, which now looks like a mismatch rather than "already progressed") — this is log/response noise, not a state or notification defect, so it's not filed as a follow-up. **Verified: test passes.**

3. **Demotion on correction (pass → fail on recalculate): UNSAFE.** A student promoted on the initial finalize (e.g. level 3 → 4) whose grade is later corrected to failing on recalculate is *not* reverted. `processEgcProgression`'s "student failed" branch (`app/Services/EgcLevelProgressionService.php:205-243`) only logs "Level unchanged (failed course)" and reads the student's *current* (already-promoted) level — there is no code path anywhere in `EgcLevelProgressionService` that ever decreases `gc_current_level`. The student keeps a level they no longer qualify for, with no progression event marking the reversal, and the notification text ("Your level remains at Level 4") reinforces the incorrect state. **Verified: reproduction test fails as predicted** — `expect($student->gc_current_level)->toBe(3)` after the corrected recalculate actually yields `4`.

### Verdict

**Unsafe — see follow-up issue [`.scratch/course-offering-cockpit/issues/09-fix-egc-level-demotion-on-recalculate.md`](./09-fix-egc-level-demotion-on-recalculate.md).**

Scope note: only the demotion path (finding 3) is unsafe. Promotion-on-correction and repeated-recalculate idempotency (findings 1-2) are both correct and need no follow-up. The Recalculate button issue (`04-recalculate-action.md`) should coordinate with the follow-up fix per its own "Blocked by" note — either wait for the fix, or the PRD owner may choose to ship with this as a documented known limitation.
