# Bulk EGC Placement Import — shipped in one session

**Date**: 2026-08-17 16:00
**Severity**: Low (feature ship, no incident) — Medium note on a repo-wide test landmine hit along the way
**Component**: Academic/Progression (bulk CSV placement import), tests/ Pest namespace
**Status**: Resolved (feature shipped); two follow-ups unowned

## What Happened

Implemented plan `plans/260817-1515-bulk-egc-placement-import/plan.md` end to end, all 5 phases, single session, single commit `274a3ad2e` on `dev`.

Shape: staff paste/upload a CSV of student IDs + EGC levels, preview shows valid/skip rows with reason codes, execute runs `InitializeStudentPlacementAction` per valid row. New pieces:
- `Shared\Contracts\Admissions\IntakeSemesterReader` + `EloquentIntakeSemesterReader` — Academic's first-ever inbound dependency on Admissions (previously only Admissions→Academic existed). Mirrors existing `ApplicationProgramMappingReader` pattern, bound in `AdmissionsServiceProvider`.
- `BulkEgcPlacementRowMapper` — classifies rows: `student_id_missing`, `student_not_found`, `level_missing`, `level_excluded_gcs`, `level_unmapped`.
- `ImportBulkEgcPlacementFromCsvAction` + controller + 2 FormRequests + preview/execute routes, cache-backed preview token, gated `can:change_student_status`.
- `BulkImportEgcPlacementDialog.vue` wired into existing Placement Worklist page.
- 15 new tests, 78 assertions, all green; full `tests/Feature/Academic/Progression` + `tests/Unit/Admissions` (77 tests) run clean, zero regressions.

Mandatory code-reviewer pass caught 5 real issues, 4 fixed, 1 accepted-as-is (see below). Docs updated: `docs/module-architecture-map.md` section 4 table + section 6 Mermaid diagram get the new Academic→Admissions edge — this is a real new architectural fact, not decoration.

## The Brutal Truth

Feature itself went smoothly — plan was solid, phases executed cleanly. The actual pain was a detour: copying two helper functions verbatim from another test file (as the plan literally instructed) silently landed us in a pre-existing repo-wide trap — Pest test files share ONE PHP global namespace when run together. A `function` with the same name declared in two files = fatal "cannot redeclare", but it doesn't look like a PHP fatal. It looks like the whole test run just dying with exit 255 and **zero output**. No stack trace, no assertion diff, nothing. If you don't know this trap exists, you burn real time assuming the runner itself is broken.

Also mildly annoying: no CI in this repo means nobody would ever catch this except by accident, exactly like we just did.

## Technical Details

- Fatal: `grantChangeStudentStatus()` / `makeWorklistStudent()` duplicated verbatim from `tests/Feature/Academic/Progression/PlacementWorklistTest.php` into the new feature test → whole-suite run crashes, exit code 255, no output.
- Fix: renamed the new copies to `grantChangeStudentStatusForBulkEgcImport()` / `makeBulkEgcWorklistStudent()`.
- Confirmed (not fixed, out of scope) this exact class of bug already lives elsewhere: `rosterStudent` duplicated between `StudentDecisionRosterTest.php` and `ClassRosterLifecycleStatusTest.php`; `makeWorklistStudent` duplicated between `PlacementWorklistTest.php` and `Finance/Dng/ListDngWorklistQueryTest.php`. Running either pair together today already crashes the same way.
- Separate landmine: shared `db_test` DB was left half-migrated from an earlier exit-255 crash (interrupts `RefreshDatabase` mid-migration). `php artisan db:wipe --database=testing --force` from a bare `docker exec` shell needs explicit `-e DB_TEST_HOST=db` — container's plain `.env` has `DB_TEST_HOST=localhost`, which resolves to nothing inside the container; only phpunit.xml's own bootstrapping resolves it to `db` correctly.

### Review findings (code-reviewer subagent)

- HIGH, fixed: `BulkEgcPlacementRowMapper` looked up students with no campus filter — an operator with `change_student_status` at one campus could paste another campus's Student IDs and place them cross-campus. Fixed by threading `$campusId` (from `session('current_campus_id')`) into `mapRow()`.
- MEDIUM, fixed: preview table `row_number` was off by one (`+2` vs real CSV line). New code, no shared blast radius, fixed clean.
- MEDIUM, fixed: `handleExecute()` in the Vue dialog didn't check `global_errors` in the response — hitting the "intake semester not configured" guard on execute (valid preview token) toasted "Import done: 0 placed, 0 failed" as success while a contradictory error Alert also rendered. Fixed to mirror `handlePreview()`'s existing check. The test covering this path was also wrong — it was accidentally exercising the token-mismatch 422 guard instead of the real semester guard — rewritten to hit the actual guard with a valid token.
- LOW, fixed: swapped `ApiResponse::compatible()` for `success()`/`error()` — `compatible()`'s own docblock says legacy-envelope migration only; these are greenfield routes.
- LOW, fixed: `mapLevel()` now strips NBSP (`\x{00A0}`) alongside regular whitespace — spreadsheet exports carry it routinely.
- MEDIUM, accepted not changed: reviewer flagged checking `student_not_found` before `level_excluded_gcs`/`level_unmapped` wastes DB queries on GCS/EGC6 rows, could skew plan's predicted preview counts (~219 GCS, ~34 EGC6) if some of those students don't exist. Left as-is — this exact skip-reason priority order is the plan's own user-confirmed Business Rule (plan.md "Business Rules (confirmed with user)"). Per repo rule: don't reverse a verified/confirmed decision on an audit's abstract concern. CSV capped at 1000 rows on an indexed column, run rarely by staff — not a perf fire.
- LOW, accepted not changed: plan's Phase 4 said reuse `FileUpload.vue`; dialog uses a raw `<Input type="file">` instead — simpler for single-file non-drag-drop. Noted as deviation.

## What We Tried

Nothing failed outright this session — the near-miss was the duplicate-function crash, resolved on first diagnosis once isolated (ran files one at a time to bisect which test file was the collision source, since the crash gave no useful output pointing at it directly).

## Root Cause Analysis

Pest's shared global namespace for top-level `function` helpers across test files is a real design gotcha in this repo, undocumented at the point of use — the plan told this session to copy helpers "verbatim," which was correct advice for behavior but wrong for naming uniqueness. Nothing in the test file or Pest itself warns you before you collide.

## Lessons Learned

- When a plan says "copy helper X verbatim" from another test file, always suffix/rename it to something file-scoped unless you've grepped the whole tests/ tree for the same name first.
- Exit code 255 + zero output from a Pest run in this repo = suspect a duplicate global `function` declaration before anything else. Bisect by running suspect files individually.
- `docker exec` bare artisan commands against the test DB need `-e DB_TEST_HOST=db` explicitly; don't trust the container's `.env` for test-DB targeting.
- Accepting a reviewer's MEDIUM finding as "not changed" is fine when it's re-litigating a user-confirmed business rule — cite the confirmation source and move on, don't debate the audit.

## Next Steps

- Owner TBD: dedicated cleanup pass across tests/ tree to rename the pre-existing duplicate Pest helpers (`rosterStudent`, `makeWorklistStudent`) before the next person collides with them blind. Not scheduled.
- No CI test workflow exists in this repo (see memory `no-ci-test-workflow-in-repo`) — nothing currently catches either the duplicate-helper class of bug or this feature's regressions automatically on merge. Still open, no owner.
- Success Criterion 1 in plan.md (405-row real `student_egc.csv` preview counts) stays unverified until a real staff run — the actual file was never available in this repo/session; verified only against an equivalent synthetic CSV in Phase 5 tests.

## Unresolved Questions

- Who owns fixing the pre-existing duplicate-Pest-helper collisions (`rosterStudent`, `makeWorklistStudent`) elsewhere in tests/?
- Is a CI test workflow being planned at all, or is exit-255-with-no-output an accepted risk indefinitely?
- Does staff running the real `student_egc.csv` produce the plan's predicted ~219 GCS / ~34 EGC6 skip counts? Unverified.
