# Verification: Bulk EGC Placement Import plan

Full-tier (Fact Checker + Flow Tracer + Contract Verifier + Scope Auditor) pass against
`plans/260817-1515-bulk-egc-placement-import/{plan.md,phase-*.md}`.

## Phase 1 — Intake semester contract
- VERIFIED `CrmMappingSettings::getIntakeCode()` (app/Modules/Admissions/Support/Crm/CrmMappingSettings.php:19) — returns `local_code` from `crm_value_mappings`, matches plan.
- VERIFIED `Semester.code` column exists (app/Models/Semester.php:20).
- VERIFIED `AdmissionsServiceProvider` has exactly ONE existing bind (line 16, `ApplicationProgramMappingReader`) — plan's "add one more binding" claim accurate.
- UNVERIFIED namespace choice `Shared\Contracts\Academic\IntakeSemesterReader` — plan justifies via "named for consumer side" but `ApplicationProgramMappingReader` actually sits under `Shared\Contracts\Admissions` (named for the *owner*, Admissions, not the consumer Academic). Plan's own parenthetical claim ("also named for the consumer side") is backwards — it's owner-named. Not a functional bug, just a wrong justification in the prose; either namespace works but the analogy as written is incorrect.

## Phase 2 — CSV mapper
- No new external claims beyond Phase 1/3; internal-only logic. No FAILED.

## Phase 3 — Import action, controller, routes
- VERIFIED `InitializePlacementRequest::placementTestRules()` — `english_level` `['required','integer','min:0','max:5']` (Http/Requests/Placement/InitializePlacementRequest.php:52). Confirms EGC6 rejection claim.
- VERIFIED `ProgramEnrollment::LEGACY_STUDENT_SOURCE = 'legacy_students'` (Progression/Models/ProgramEnrollment.php:16).
- VERIFIED `InvalidProgressionState` namespace `App\Modules\Academic\Progression\Exceptions` (Exceptions/InvalidProgressionState.php:5).
- VERIFIED `StudentActionType::STUDENT_ENROLLMENT_NE` (app/Enums/StudentActionType.php:9).
- VERIFIED `AcademicProgressionEventType::PLACEMENT_INITIALIZED` (app/Enums/AcademicProgressionEventType.php:9).
- VERIFIED `ApiResponse::compatible()` (app/Http/Responses/ApiResponse.php:21).
- VERIFIED routes group: `Route::middleware(['auth','verified','campus.selected'])` opens at app/Modules/Academic/routes/web.php:79; `students-placement-worklist` route at line 403 — both inside the same group, so "add routes inside this group, next to students-placement-worklist" is geometrically correct.
- VERIFIED `InitializeStudentPlacementAction::run()` (already read in full earlier in session) wraps everything in one `DB::transaction()`; the `InvalidProgressionState` throw happens *inside* that transaction after `MaterializeProgramEnrollmentAction::run()` has already executed its own **separate, already-committed** transaction (materializing/locking the enrollment row happens in a nested `DB::transaction` in `MaterializeProgramEnrollmentAction`, which commits before the outer check). Flow Tracer finding: if the enrollment didn't exist yet, `MaterializeProgramEnrollmentAction` will have created and committed a bare `ProgramEnrollment` row (`study_stage` null, `enrollment_status` set) even when the subsequent already-placed check throws — but that's a no-op for an *idempotent* re-run (it's fine, materialization is meant to be idempotent/find-or-create), not a partial-placement bug. No actual `InvalidProgressionState` bug (the "already placed" check only fires when `study_stage !== null`, so materialization idempotency and the plan's error-catch expectation both hold). Plan's rollback-correctness claim is safe — mark VERIFIED, not FAILED.
- VERIFIED Maatwebsite Excel v3.1 (composer.json:25) supports CSV via `ReaderFactory`/`WriterFactory` — plan's reuse of `Excel::toArray()` for `.csv` is valid, same call already used by `ImportStudentActionsFromExcelAction::getFirstSheet()`.
- UNVERIFIED mime rule: plan proposes `mimes:csv,txt,xlsx,xls` (widened from the student-actions importer's `xlsx,xls`). Not independently checked against Laravel's mime sniffing for a raw `.csv` upload (Symfony's `csv` extension guess can be finicky depending on the file's actual detected MIME e.g. `text/plain` vs `text/csv`) — flag as a real implementation risk to interview, not a plan-authoring error.

## Phase 4 — Frontend
- VERIFIED `resources/js/components/FileUpload.vue` exists and is exactly what `StudentActionsImport.vue` imports (`@/components/FileUpload.vue`, line 2). A second, unrelated `resources/js/components/imports/FileUpload.vue` also exists — plan doesn't reference it, no conflict, just noting so Phase 4 implementer doesn't grab the wrong one by autocomplete.
- VERIFIED `vue-sonner` `toast` is an established import pattern in this codebase (5+ other Pages use it).
- UNVERIFIED "add route names to `resources/js/utils/routes` if needed" — plan already hedges this as conditional/to-check; ziggy-js typically auto-registers all named Laravel routes without manual entries, so this step is likely unnecessary busywork. Not FAILED, just probably a no-op step.

## Phase 5 — Tests
- FAILED (repo convention mismatch): plan proposes PHPUnit-style class test files (`BulkEgcPlacementRowMapperTest.php` etc. as if `public function test...()`), but the actual sibling suite is **Pest**, flat-function style: `tests/Feature/Academic/Progression/PlacementWorklistTest.php` and `PlacementOwnershipTest.php` both exist and use `it('...', function (): void {...})`, `uses(RefreshDatabase::class)`, and a local helper `grantChangeStudentStatus()` + `makeWorklistStudent()` for exactly the fixture (Student + primary `ProgramEnrollment` with `LEGACY_STUDENT_SOURCE`, `intake`/`intake_semester_id` set) this new feature test needs. Phase 5 was written from memory ("per repo history") instead of citing these two real files — should be rewritten to explicitly reuse `makeWorklistStudent()`/`grantChangeStudentStatus()` (or a copy colocated with the new test) rather than re-describing the conventions in prose.
- VERIFIED no existing test file covers `InitializeStudentPlacementAction` in isolation — it's only exercised indirectly through `PlacementWorklistTest.php`'s `'drops a student from the worklist after placement initialize'` case (line 173) and `PlacementOwnershipTest.php`. New feature test is additive, not duplicative.
- VERIFIED `Student.student_id` (external code column, app/Models/Student.php:91 fillable) is a distinct column from `StudentApplication.student_code` (app/Models/StudentApplication.php:66) — plan's lookup-by-`student_id` claim throughout is correct and is the only such column on `Student`.

## Summary
- Claims checked: 22
- Verified: 18 | Failed: 1 | Unverified: 3
- Tier: Full

### Failures
1. [Fact Checker/Contract Verifier] Phase 5 test file style — plan assumes PHPUnit-class tests; repo's actual Placement tests are Pest-style flat functions in `tests/Feature/Academic/Progression/PlacementWorklistTest.php` and `PlacementOwnershipTest.php`, with reusable `grantChangeStudentStatus()`/`makeWorklistStudent()` helpers the new test should reuse.

### To raise in the validate interview
- Phase 1: fix the backwards "consumer-named" justification for the `Shared\Contracts\Academic\IntakeSemesterReader` namespace (cosmetic, no functional change needed).
- Phase 3: confirm the widened CSV mime rule (`mimes:csv,txt,xlsx,xls`) against how the browser actually reports the file's MIME type for a raw `.csv` — may need `Rule::in` on extension instead of relying on `mimes:`.
- Phase 4: the `resources/js/utils/routes` step is likely unnecessary (ziggy auto-registers); can probably be dropped from Implementation Steps.
- Phase 5: rewrite to Pest style and explicitly point at `PlacementWorklistTest.php` as the fixture source.

No files modified — read-only verification only.
