# Fix: EGC level not reverted when recalculate flips a student from pass to fail

Status: ready-for-human

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## Origin

Filed from the EGC recalculate progression audit (`.scratch/course-offering-cockpit/issues/03-egc-recalculate-progression-audit.md`). See that issue's Comments section for the full audit writeup. Reproduction test: `.scratch/course-offering-cockpit/egc-recalc-audit-repro-test.php` (not part of the shipped test suite — copy into `tests/Feature/CourseOffering/` to re-run).

## What's wrong

`EgcLevelProgressionService::processEgcProgression()` only ever moves a student's `gc_current_level` forward (see `progressStudentLevel()`, `app/Services/EgcLevelProgressionService.php:252`). There is no code path that moves it backward. When `MarkCourseOfferingCompletedAction::run($offering, recalculate: true)` re-evaluates a student whose grade correction flips them from **passing to failing**, the "student failed" branch (`app/Services/EgcLevelProgressionService.php:205-243`) only appends to `failed_students` and logs "Level unchanged (failed course)" — it reads `$student->gc_current_level`, which is already the *post-promotion* level from the original finalize, and never reverts it.

Net effect: a student who was promoted (e.g. level 3 → 4) on the initial finalize, then has their grade corrected to a failing score on recalculate, keeps the level-4 credit they no longer qualify for. The in-app notification text ("Your level remains at Level 4") reinforces the wrong state instead of surfacing the demotion.

## Concrete failure scenario (verified)

1. Student `S` is `intake_pre_uni_gc`, `gc_current_level = 3`. Course offering is for an EGC unit at `level = 3`.
2. Offering is finalized (`recalculate: false`) with `S` scoring 85% (passes the 70% EGC threshold). `progressStudentLevel()` runs: `gc_current_level` 3 → 4, one `AcademicProgressionEvent` created.
3. Staff correct `S`'s grade down to 50% (fails the threshold) and hit Recalculate (`recalculate: true`).
4. `processEgcProgression()` sees `is_passed = false`, takes the "failed" branch, logs "Level unchanged (failed course)" — but `gc_current_level` is still `4`. It is never set back to `3`.
5. Result: `S` is permanently credited with a level they no longer qualify for, with no record that a demotion should have happened. The only trace is the audit repro test; there is no `AcademicProgressionEvent` recording a reversal, and the student-facing notification says the level "remains" at 4 (technically true of the buggy persisted state, but wrong relative to what actually happened).

Verified in `.scratch/course-offering-cockpit/egc-recalc-audit-repro-test.php` (`it('AUDIT: pass-to-fail recalculation does not revert an EGC level already progressed', ...)`): the assertion `expect($student->gc_current_level)->toBe(3)` fails — actual value is `4`.

Note: the reverse case (fail → pass on recalculate) and repeated recalculate with no change were both verified correct — the existing `$oldLevel !== $currentUnitLevel` guard in `progressStudentLevel()` prevents duplicate promotion events. This issue is scoped to the missing demotion path only.

## What to build

Add a demotion path to the "student failed" branch of `processEgcProgression()`, gated to `recalculate` mode only (finalize-mode failures never had a prior promotion to undo within the same call). When recalculating a student whose `is_passed` flipped from `true` (per `$previousStatusMap`) to `false`, and their `gc_current_level` matches `$unitLevel + 1` (i.e., they were promoted specifically by *this* unit), revert `gc_current_level` back to `$unitLevel`, record an `AcademicProgressionEvent` for the reversal (mirroring `progressStudentLevel()`'s event shape, but level decreasing), and adjust the notification copy to say the level was reverted rather than "remains."

Decisions to confirm with the PRD owner before implementing (out of scope for this audit to decide unilaterally):
- Should the reversal require `gc_current_level === $unitLevel + 1` exactly (safe, conservative — skips reversal if the student has since progressed further via another course), or should it always clamp back to `$unitLevel` whenever a previously-passing record for this offering flips to failing?
- Does a demotion need its own `AcademicProgressionEventType` (e.g. `ENGLISH_LEVEL_REVERTED`) or should it reuse `ENGLISH_LEVEL_CHANGED` with `from_english_level`/`to_english_level` reversed?
- Should demotion be blocked/flagged if the student already has a newer academic record for the next-level unit (i.e., they've already started or passed level 4 coursework) — reverting them to level 3 while they have a level-4 in-progress record could be a worse inconsistency than leaving level 4 as-is?

## Acceptance criteria

- [x] Recalculating a course offering that flips a previously-passing EGC student to failing reverts `gc_current_level` back to the unit's level when the student was promoted specifically by that unit's prior finalize.
- [x] A reversal is recorded (new or reused `AcademicProgressionEvent`) with a clear `from`/`to` level and a note distinguishing it from a normal promotion.
- [x] Notification copy accurately reflects a demotion when one occurs, instead of claiming the level "remains" unchanged.
- [x] The three existing audit scenarios (promotion on correction, no-op re-recalculate, demotion on correction) all pass — the demotion scenario in `.scratch/course-offering-cockpit/egc-recalc-audit-repro-test.php` should be moved into `tests/Feature` and updated to assert the fixed (reverted-to-3) behavior.
- [x] No duplicate or missing progression events across repeated recalculate calls (regression-test the existing idempotency guard alongside the new demotion path).

## Blocked by

None — can start immediately. Does not block Finalize; the Recalculate button issue (`04-recalculate-action.md`) may choose to ship with this as a documented known limitation per PRD owner direction, or wait for this fix — see that issue's coordination note.

## Comments

### Implemented (2026-07-03)

PRD owner decided (via the Recalculate action issue) to implement this fix and issue 04 together rather than ship a known limitation.

Resolved the three open design questions before implementing:
- Reversal guard: exact match only (`gc_current_level === unitLevel + 1`) — conservative, skips reversal if the student progressed further via another course.
- Event type: reuse `ENGLISH_LEVEL_CHANGED` with `from_english_level`/`to_english_level` reversed and a `notes` field marking it as a "Recalculate reversal", rather than adding a new enum case.
- Conflict guard: if the student already has a newer `AcademicRecord` (status `enrolled`, `in_progress`, or `completed`) for the next-level EGC unit, skip the revert and log a warning instead of demoting them out from under coursework they're already doing at that level.

`EgcLevelProgressionService::revertStudentLevel()` implements the fix; the "student failed" branch in `processEgcProgression()` calls it when `recalculate=true`, the student's previous status (per `$previousStatusMap`) was passing, and `gc_current_level` still equals `unitLevel + 1`. Notification copy now says the level "was reverted from Level X to Level Y" instead of "remains at Level X" when a reversal occurs.

The throwaway repro test was moved and rewritten as `tests/Feature/Academic/EgcRecalculateDemotionTest.php` (5 tests: the fixed demotion, the exact-match guard skipping when the student progressed further elsewhere, the conflict guard skipping when a newer record exists, plus the two already-safe audit scenarios re-verified). All pass.
