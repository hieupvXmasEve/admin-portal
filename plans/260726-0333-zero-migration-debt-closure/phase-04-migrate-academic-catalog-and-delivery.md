---
title: "Phase 4: Migrate Academic catalog and delivery"
status: in-progress
priority: P0
effort: XL
dependencies: [3]
---

# Phase 4: Migrate Academic catalog and delivery

## Overview

Complete Academic Catalog, Calendar, Delivery, attendance, scheduling,
assessment, roster, and Canvas ownership as vertical slices. Remove legacy
controllers/services/routes and frontend debt with each migrated workflow.

## Requirements

- [ ] Assign every generic Academic action/query/support class to one logical context.
- [ ] Eliminate non-owner CourseOffering, Semester, Lecture, curriculum, and result imports.
- [ ] Preserve route, permission, filter, export/import, schedule, and Canvas behavior.
- [ ] Resolve human review gates for repository issues 09 and 10.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Catalog` | Own units, curriculum, programs, syllabus |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Delivery` | Own offerings, sessions, roster, attendance |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Delivery` | Also own assessment/gradebook/result commits per accepted boundary |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers` | Retire migrated Academic shells |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services` | Retire migrated Academic/Canvas services |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Academic` | Canonical pages, filters, named routes |
| `/Users/hunt2412/hieupvdev/project/swinx/routes` | Remove superseded split routes |

## Interface Checklist

- Cross-context calls use Academic-internal contracts/events or Shared contracts.
- Controllers use FormRequests and Actions/Queries; API payloads use Resources/ApiResponse.
- Filtered pages accept whitelisted filters/sorts and echo sanitized state.
- Canvas sync is idempotent and retries without duplicate enrollments/content.

## Dependency Map

`reference foundations + relevant page batch → Catalog → Delivery including assessment → shell retirement`

## Implementation Steps

1. Freeze route/component/payload behavior with targeted characterization tests.
2. Migrate Catalog: units, curriculum versions, programs, specializations, syllabus.
3. Migrate Calendar/Delivery: semesters, offerings, registrations, lectures, sessions.
4. [x] Migrate attendance, roster, assessment/gradebook, course result, and all Canvas
   mapping/sync/provider behavior within Academic Delivery.
5. In each slice replace inline validation, raw responses, legacy filters, and literal URLs.
   - [x] Unit Catalog: migrated index filtering to `useDataTable`, sanitized
     filter state, and named-route HTTP/frontend interactions; preserved filtered
     exports and added bulk-delete authorization coverage.
   - [x] Curriculum Versions and Curriculum Units: replaced inline HTTP/action
     validation with Catalog FormRequests, aligned supported filter contracts,
     removed unsupported Unit controls, and migrated active frontend interactions
     to `useDataTable`, named routes, and the API wrapper.
   - [x] Syllabus Templates: replaced page-index inline validation and the
     duplicate POST route, migrated template filtering to `useDataTable`, and
     moved active template navigation and mutations to named routes while
     preserving API response envelopes and edit-lock behavior.
   - [x] Lectures and Teaching Hours: replaced inline validation and raw JSON
     responses on lecture helpers and exports, preserved campus and `all`
     filter compatibility, and migrated teaching-hours detail filtering to
     `useDataTable`, named routes, and DatePicker controls.
   - [x] Canvas Course Mapping: moved mapping-list and offering lookup filters
     into Delivery FormRequests, preserved sanitized sorting and campus-scoped
     `all` lookup behavior, and migrated mapping interactions to the API
     wrapper and named routes.
   - [x] Canvas Syllabus, Assignment, and Grade Sync: standardized summary and
     grade-sync API envelopes, added assignment selection validation, restored
     the registered syllabus-summary route, and preserved Canvas sync dialogs
     and course-offering grade preview/apply behavior.
   - [x] Lecturer Assessment: moved bulk-grade and report query validation into
     Delivery lecturer FormRequests, preserved authorization-before-validation
     and export-format behavior, and standardized validation failures with the
     existing API envelope.
   - [x] Calendar/Course Registration retirement: removed the unregistered
     legacy registration service and API controller shells after confirming
     live Delivery actions/controllers own the active flows; lowered the
     frozen-service, frozen-controller, and direct-JSON ratchets accordingly.
   - [x] Calendar service retirement: removed the unreferenced semester
     management and automated-enrollment services after confirming no live
     routes, container bindings, or callers remained; lowered the
     frozen-service ratchet accordingly.
   - [x] Course Offering route retirement (issues 09, 10, 11): moved the
     finalize, Canvas grade-sync preview/apply (issue 10), and recalculate
     preview/apply (issue 11) live routes from the frozen
     `routes/web/course-offerings.php` split file into the owned
     `app/Modules/Academic/routes/web.php`, removed the now-empty frozen
     file, updated the retired-path assertion in
     `AttendanceDeliveryMigrationArchTest`, and lowered the frozen-route
     ratchet in both `config/migration_debt.php` and
     `MigrationDebtContract::BASELINE_CEILINGS`. Route names, permissions,
     and the EGC recalculate-demotion fix (issue 09) are covered by green
     tests; no orphan reference to the deleted file remains.
   - [x] Course Statistics retirement: added first-time characterization
     coverage (index, per-unit detail, combined export), then moved
     `CourseStatisticsController`/`UnitStatisticsController`,
     `CourseStatisticsRequest`, `GetUnitStatisticsAction`, and
     `UnitStatisticsResource` off the frozen `routes/web/course-statistics.php`
     split file into Academic Delivery. Extracted the previously-hidden
     cross-submodule model reads into owner-scoped seams
     (`Catalog\Queries\GetSemesterFilterOptionsQuery`,
     `Catalog\Queries\GetUnitGradingThresholdsQuery`,
     `Progression\Queries\GetUnitAcademicOutcomesQuery`) so the move added
     zero new `shared_model_imports` debt instead of the +4 a naive move
     would have introduced. Lowered the frozen-route and frozen-controller
     ratchets accordingly; `CourseStatisticsService` itself stays frozen
     (still consumed by `GetCourseOfferingScoresQuery`, out of this
     slice's scope).
   - [x] Specialization retirement: added first-time characterization
     coverage (list/filter/sort, create/store/update/destroy including both
     redirect branches, `apiDestroy`, bulk-delete, and the curriculum-version
     update action), then moved `SpecializationController` and its
     FormRequests off the frozen `routes/web/specializations.php` split file
     into `App\Modules\Academic\Catalog`. Replaced the three remaining inline
     `$request->validate()` calls with FormRequests and removed the
     unregistered dead `apiDeleteCurriculumVersion` method. Fixed a live
     production bug found via characterization: the `bulk-delete` API route
     was shadowed by the `{specialization}` wildcard destroy route
     registered before it, so the frontend's bulk-delete button 404'd;
     reordered so the literal route wins, matching the existing
     `units/bulk-delete` precedent already established in Catalog's own
     route file. Code review caught that the retired file's bulk-delete
     route had never carried `can:delete_specialization` (harmless while
     shadowed and 404ing; live and unauthorized once reachable) — added the
     gate and a structural test asserting it stays there. Also changed the
     curriculum-version-update action's field defaults from `?? null`
     (silent data-wipe on a partial payload) to `?? $curriculumVersion->x`
     (preserve existing value), and removed the two dead
     `API_CURRICULUM_VERSION_DESTROY` references (PHP constant + unused TS
     helper) left behind by the `apiDeleteCurriculumVersion` deletion.
     Lowered the frozen-route and frozen-controller ratchets accordingly;
     `SpecializationService` itself stays frozen (single consumer, out of
     this slice's scope).
   - [x] Module retirement: added first-time characterization coverage
     (index search/filter/sort, create/edit form data, store/update/destroy
     including the curriculum-assignment delete guard, and unit sync with
     total-credits recalculation), then moved `ModuleController` and its
     `Store`/`UpdateModuleRequest` off the frozen `routes/web/modules.php`
     split file into `App\Modules\Academic\Catalog`. Added
     `ListModulesRequest`/`SyncModuleUnitsRequest` FormRequests to replace
     the two remaining inline `$request->validate()` calls. Replaced the
     direct `Campus` model reads with the existing
     `App\Shared\Contracts\Institution\CampusReferenceReader` shared
     contract (the established Catalog precedent, see
     `AcademicPeriodController`) so the move added zero new
     `shared_model_imports` debt instead of the +1 a naive move would have
     introduced. No route-order shadow bug this time. Permission gates: the
     frozen file had none of the `view_module`/`create_module`/
     `edit_module`/`delete_module` permissions wired to any of the 8 routes,
     even though those permissions exist and gate the frontend menu entry;
     preserved verbatim rather than silently closed, since it is a
     pre-existing gap (not a regression from this move) and adding
     `can:*_module` gates is a security decision pending user sign-off —
     tracked as an open item against step 7's permissions review.
     Lowered the frozen-route and frozen-controller ratchets accordingly;
     `ModuleService` itself stays frozen (single consumer, out of this
     slice's scope).
   - [x] Semester Enrollment retirement: a five-sub-slice migration plan was
     drafted for the then-1252-line `SemesterEnrollmentController` (see
     [phase 4b](./phase-04b-retire-semester-enrollment.md)), but before any
     sub-slice started, the product owner cut scope directly: the enrollment
     page now keeps only the overview and `generateEnrollments`; the other 6
     methods (suggested-courses, both open-course flows, registration stats,
     bulk-register, registrable-students) were deleted outright rather than
     migrated, along with their now-orphaned action/request/UI files. The
     remaining 227-line/2-method controller was then moved into
     `App\Modules\Academic\Progression` (claiming `Enrollment` ownership
     there — it tracks student semester progression, the same concern as
     sibling `AcademicRecord`/`AcademicHold`). Two new seams
     (`AcademicPeriodReader::courseOfferingCount()` on the existing Catalog
     contract, and a new `SemesterEnrollmentEligibilityReader` in
     StudentRegistry) replaced every direct `Student`/`Campus`/`Semester`
     model read so the move added zero new `shared_model_imports` debt at a
     time the ratchet had zero headroom (494/494). First-time
     characterization tests written and confirmed green against the old
     code, then again after the move. Lowered the frozen-route and
     frozen-controller ratchets accordingly; see phase 4b for full detail.
   - [x] Class Schedule retirement: added first-time characterization
     coverage (guest redirect, authenticated render), then moved the
     single static `schedules.index` page route off the frozen
     `routes/web/class-schedule.php` split file into
     `App\Modules\Academic\routes\web.php` verbatim (no controller, no
     permission gate — matches the original). Deleted the now-empty
     frozen file and its `require` in `routes/web.php`. Lowered the
     frozen-route ratchet accordingly.
   - [x] Academic route retirement: added first-time characterization
     coverage (permission gates, page render, filters, finalize
     validation, ranking output), then moved `GpaManagementController`,
     `AcademicReportController`, and `CourseRankingController` off the
     frozen `routes/web/academic.php` split file into
     `App\Modules\Academic\Http\Web` alongside the three sibling
     controllers (`GpaHistoryController`, `PerformanceDashboardController`,
     `WarningCenterController`) that already lived there; the whole
     `academic.*` route group now registers from
     `App\Modules\Academic\routes\web.php`. Replaced all four inline
     `$request->validate()` calls with FormRequests
     (`Gpa\PreviewGpaFinalizationRequest`, `Gpa\FinalizeGpaRequest`,
     `ListAcademicReportRequest`, `ListCourseRankingRequest`). Replaced
     `GpaManagementController`'s direct `Campus`/`Semester` reads with
     `CampusReferenceReader` and the existing
     `Catalog\Queries\GetSemesterFilterOptionsQuery` seam (already used
     elsewhere in Academic reporting); reusing that seam narrows the
     semester dropdown to non-archived semesters, a deliberate behavior
     tweak versus the old unfiltered list. Extracted
     `CourseRankingController`'s direct `Unit`/`Semester`/`AcademicRecord`
     reads into a new `Progression\Queries\GetCourseRankingQuery` that
     reaches `Unit`/`Student` through `AcademicRecord`'s own relations
     instead of importing those models, so the move added zero new
     `shared_model_imports` debt. Deleted the three old controller files,
     the now-empty `app/Http/Controllers/Web/Admin/Academic/` directory,
     the frozen route file, and its `require` in `routes/web.php`.
     Fixed `ReportingOwnerReadersArchTest`, which still read the deleted
     controller path directly. Lowered the frozen-route and
     frozen-controller ratchets accordingly.
   - [x] Lecturer route retirement: discovered `App\Modules\Academic\FacultyWorkforce`
     already exists (owns lecturer-access-eligibility concerns) and is the
     correct home for `Lecture`, superseding an initial `Delivery` proposal.
     Added `Lecture` to `owned_shared_models.Academic.FacultyWorkforce`,
     which also retroactively exempted 9 pre-existing findings from
     `FacultyWorkforce`'s existing eligibility readers (net -9 on the
     `shared_model_imports` ratchet). Added first-time characterization
     coverage (permission gates, CRUD, teaching-hours, import/export
     envelopes), then moved `LectureController`, `LectureExportController`,
     `LectureImportController`, and `LecturerGpaController` off the frozen
     `routes/web/lectures.php` split file into
     `App\Modules\Academic\FacultyWorkforce\Http\Web`, with a new
     `FacultyWorkforce\routes\web.php` required from the Academic route
     aggregator (mirroring the existing Catalog require). Left the
     controllers' existing `app/Http/Requests/Lecture/*` FormRequests in
     place (unscanned location, already correct); only authored new
     FormRequests for the previously-inline-validated import endpoints
     (`Upload`/`Preview`/`ProcessLectureImportRequest`). Extracted the
     user-account create/update side effects out of
     `LectureController::store/update` into
     `App\Actions\Lecture\{Create,Update}LectureAction` (unscanned
     location, matches the existing `GetTeachingHoursAction` precedent) so
     `User` never needs importing from inside the module. Replaced direct
     `Campus`/`CourseOffering`/`Semester` reads with
     `CampusReferenceReader`, a new
     `Delivery\Queries\GetCourseOfferingUnitTypesQuery`, the existing
     `Catalog\Queries\GetSemesterFilterOptionsQuery`, and a new
     `Catalog\Queries\GetLecturerGpaSemesterContextQuery` (kept separate
     from the shared semester-options seam because its active-or-latest
     fallback and lack of an archived-semester filter are genuinely
     different behavior) — zero new `shared_model_imports` debt. Replaced
     `LectureImportController`'s 8 raw `response()->json()` calls with
     `ApiResponse::compatible()`, which preserves the exact flat
     `{success, ...}` envelope the frontend already consumes (unlike
     `ApiResponse::success()`, which would nest the payload under `data`
     and break the frontend) — this is the established escape hatch for
     migrating an endpoint's call site without changing its contract.
     Fixed a second stale reference: `routes/api/admin.php`'s `apiIndex`
     route still imported the deleted `Web\LectureController`. Found (but
     left alone, pre-existing and unrelated) one failing
     `TeachingEligibilityAssignmentBoundaryTest` case: Delivery's
     `LecturerAssessmentRequest` already imported `Lecture` directly before
     this move. Lowered the frozen-route and frozen-controller ratchets
     accordingly.
   - [x] Canvas route retirement: the frozen `routes/web/canvas.php` split
     file already registered its 18 routes against already-migrated
     `App\Modules\Academic\Delivery\Http\Web\Canvas\*` controllers (no
     controller move needed). Moved the route group verbatim into
     `app/Modules/Academic/routes/web.php` alongside the other Canvas
     routes already registered there (grade-sync preview/apply from the
     course-offerings retirement), deleted the frozen file and its
     `require` in `routes/web.php`, and updated the two stale
     `routes/web/canvas.php` path references in
     `docs/features/canvas/{operations,integration}.md`. Lowered the
     frozen-route ratchet accordingly. This closes phase-04's remaining
     route-retirement scope (`class-schedule.php`, `academic.php`,
     `lectures.php`, `canvas.php` all retired).
   - [x] Parity review for the four route retirements above: diffed each
     retired file's `name()`/`middleware('can:...')` sequence against its
     new location — byte-identical for `academic.php`, `canvas.php`, and
     `lectures.php`. Verified every route's controller/method binding via
     `route:list -v`. Confirmed no scheduled command
     (`routes/console.php`) references any moved or retired class.
     Confirmed `campus.selected` middleware presence/absence preserved
     per group (Canvas has it; Academic and Lectures never did). One
     accepted behavior delta: `GpaManagementController::index`,
     `LectureController::index`, and `LectureController::teachingHours`
     now source their semester dropdown from the shared
     `GetSemesterFilterOptionsQuery`/`AcademicPeriodReader` seam (filters
     `is_archived = false`) instead of an unfiltered raw `Semester` query,
     matching the already-established pattern in `AcademicReportController`
     and `CourseRankingController`. Product owner confirmed keeping this
     (closes a pre-existing inconsistency rather than introducing one).
     `GpaHistoryController` (untouched, outside this session's scope)
     still shows archived semesters — a leftover inconsistency for a
     future slice, not a regression from this work.
   - [x] API Academic Report retirement: moved the frozen
     `app/Http/Controllers/Api/Admin/AcademicReportController` into
     `App\Modules\Academic\Http\Api` (already clean — FormRequest plus
     `ApiResponse`, zero `App\Models\*` imports), retiring both the frozen
     controller and its single-route `routes/api/admin/academic.php` split
     file in one move. Fixed `ReportingOwnerReadersArchTest`'s stale path
     references and added `AcademicReportApiTest` coverage. Also removed
     the dead `syncAcademicRecords()`/`processSyncChunk()` pair from
     `AcademicRecordGenerationServiceOptimized` (808 → 600 lines),
     unreferenced by its only caller `GenerateAcademicRecordsCommand` or
     anywhere else; the service itself stays frozen pending a separate
     ownership move (it spans Progression/Delivery/StudentRegistry model
     boundaries, tracked under phase 9). Lowered the frozen-controller and
     frozen-route ratchets accordingly.
   - [x] Student lifecycle controllers assigned to Progression: claimed
     `StudentActionLog` and `StudentDecision` for
     `owned_shared_models.Academic.Progression` — both models are already
     read almost exclusively from inside `Academic/Progression/*`, so the
     claim retroactively exempted 18 pre-existing findings on its own
     (matching the `Lecture`/FacultyWorkforce precedent). Added first-time
     characterization coverage for the three uncovered surfaces (student
     enrollments overview incl. campus scoping and filters, lifecycle
     yearly analysis incl. default-semester selection and export, and the
     student-actions import page/template), then moved
     `StudentStatusController`, `StudentActionController`,
     `StudentActionAuditController`, `StudentDecisionController`,
     `StudentLifecycleYearlyAnalysisController`, and
     `StudentAcademicSummaryController` out of the unassigned
     `Academic/Http/Web` into `App\Modules\Academic\Progression\Http\Web`.
     Replaced all 9 inline `$request->validate()` calls with Progression
     FormRequests and the 3 raw `response()->json()` calls with
     `ApiResponse::compatible()` (byte-identical passthrough, so the
     frontend envelope is unchanged). Extracted the remaining non-owned
     model reads into seams: a new
     `Catalog\Queries\GetSemesterReferenceOptionsQuery` (deliberately
     separate from `GetSemesterFilterOptionsQuery` — audit and lifecycle
     reports must keep archived semesters selectable, which that seam
     filters out), the existing `CampusReferenceReader` for campus codes,
     and a new `UserDirectoryReader::staffMembers()` for the actor filter.
     One accepted behavior delta: `StudentStatusController`'s
     no-campus-selected redirect now runs after FormRequest validation
     instead of before it, so a request that both lacks a campus and
     carries an invalid filter returns validation errors rather than the
     campus-selection redirect. Two `App\Models\Student` imports remain
     (`StudentAcademicSummaryController`, `StudentActionController`) —
     they are route-model bindings, and removing them needs a
     StudentRegistry binding contract that is out of this slice's scope.
     Net ratchet effect: `shared_model_imports` 485 → 459,
     `direct_json_responses` 29 → 27, `inline_request_validation` 46 → 41.
   - [x] Student directory HTTP cleanup (destination-independent half of the
     `StudentController` slice): added first-time characterization coverage
     for the create form, export, and the three API endpoints
     (`apiSearch`, `apiShow`, `getByStudentIds`) — `index`/`edit`/`update`
     were already covered by `StudentDirectoryFilterTest` and
     `StudentUpdateTest`. Replaced the 4 inline `$request->validate()`
     calls with FormRequests under
     `Academic\Http\Requests\Student`, sharing the directory filter
     vocabulary through a `ChecksStudentDirectoryFilters` trait so the
     listing and its export cannot drift. Replaced all 8 raw
     `response()->json()` calls with `ApiResponse::compatible()`.
     Extracted the Catalog reads (`Program`, `Specialization`,
     `CurriculumVersion`, `Semester`) into a new
     `Catalog\Queries\GetStudentDirectoryFormOptionsQuery` returning the
     same model collections the Inertia props already carried, and the
     `Campus` reads into the existing `CampusReferenceReader`.
     Deleted two unregistered dead methods found via `route:list`
     (`apiIndex`, superseded by `Api\StudentController@index`; and
     `destroy`, which has no route at all), plus the dead references they
     left behind: `StudentRoutes::DESTROY`, `STUDENT_ROUTE_NAMES.DESTROY`,
     and the `studentRoutes.destroy`/`.update` TS helpers, both of which
     resolved to routes that do not exist and would have thrown if ever
     called. Recorded one unreachable branch rather than deleting it:
     `getByStudentIds`' "No campus selected" 400 never fires because the
     web middleware stack redirects to campus selection first.
     Ratchets: `shared_model_imports` 459 → 455,
     `direct_json_responses` 27 → 26, `inline_request_validation` 41 → 40.
   - [x] Student directory moved to StudentRegistry: product owner confirmed
     the directory is a Registry surface, not an Academic one, so
     `StudentController`, `ListStudentsQuery`, `ExportStudentsQuery`, and the
     new directory FormRequests moved into `App\Modules\StudentRegistry`,
     which already owns `Student` in the ownership contract. Relocated
     `StudentDirectoryReader` from `Shared\Contracts\Academic` to
     `Shared\Contracts\StudentRegistry` and moved its binding from the
     Academic provider to the StudentRegistry one. Because
     `cross_context_concrete_imports` is zero-tolerance, the two remaining
     reach-ins into Academic became Shared contracts implemented by their
     Academic owners: `ProgramEnrollmentStatusFilter` (Progression's
     `FilterStudentsByProgramEnrollmentStatus`, which owns the
     enrollment-before-legacy-status precedence) and
     `StudentDirectoryFormOptionsReader` (Catalog's
     `GetStudentDirectoryFormOptionsQuery`, now returning plain arrays —
     identical JSON, since the props serialized those models anyway).
     `ListStudentsQuery` took the status filter as a constructor dependency
     instead of instantiating it inline. Moved `ListStudentsQueryTest` to
     `tests/Feature/Registry`. Ratchet: `shared_model_imports` 455 → 451;
     `cross_context_concrete_imports` held at 0.
   - [x] Lifecycle actions, queries, and support assigned to Progression:
     claimed `AcademicProgressionEvent` and `IeltsCertificate` for
     `owned_shared_models.Academic.Progression` — both are read only from
     inside Academic and almost entirely from Progression, so the claim
     retroactively exempted 12 findings on its own. Then moved 17 classes
     out of the unassigned `Academic/{Actions,Queries,Support,Exports}`
     into their Progression equivalents: the student-action import chain
     (`ImportStudentActionsFromExcelAction`,
     `StudentActionExcelRowMapper`, `StudentActionExcelReferenceResolver`,
     `StudentActionImportConflictValidator`,
     `UploadActionAttachmentAction`, `StudentActionLogsExport`), the
     action/decision read side (`GetStudentActionHistoryQuery`,
     `ListStudentActionLogsQuery`, `ListStudentDecisionsQuery`,
     `GetStudentDecisionDetailQuery`,
     `PreviewStudentDecisionBulkLinkQuery`), the lifecycle reporting and
     placement queries (`ExportStudentLifecycleReportQuery`,
     `Placement\GetAcademicProgressionAuditQuery`,
     `Placement\GetAcademicProgressionHistoryQuery`), and the lifecycle
     support classes (`AcademicLifecycleEventFactory`,
     `LifecycleFormOptions`, `StudentLifecycleStatusReader`).
     `StudentActionExcelRowMapper` carried no shared-model import of its
     own but had to move with the resolver it depends on through an
     unqualified same-namespace reference — the container failed to
     resolve it until it did. Ratchet: `shared_model_imports` 451 → 421;
     `cross_context_concrete_imports` held at 0.
     Not yet done in these files: 12 reads of `Student` (5 files),
     `Semester` (3), `Campus` (1), `CourseOffering` (1), and
     `StudentWarningLog` (1) still import shared models directly. Most map
     onto the existing `StudentReferenceReader` and `AcademicPeriodReader`
     contracts, but `StudentActionExcelReferenceResolver` returns Eloquent
     models straight to its row mapper, so contract-ising it means
     reshaping that consumer too — a follow-up slice, not a rename.
   - [x] Exam Resit assigned to Delivery: claimed `ExamResitAttempt`,
     `ExamResitSession`, `ExamRoomSlot`, and `ExamRoomSlotInvigilator` for
     `owned_shared_models.Academic.Delivery` — none of the four had an owner,
     and outside the exam cluster they are read only by
     `Delivery/Support/{EloquentAcademicSpaceOccupancyReader,InvigilationDutyQuery}`,
     which the claim exempted immediately. Then moved 23 classes out of the
     unassigned `Academic/{Actions,Queries,Services,Http}` into Delivery: 9
     exam-resit/room-slot actions, 3 list queries, `ExamScheduleConflictChecker`
     (`Services` → `Delivery/Support`), the 8 `ExamResit` FormRequests, and
     both `Http/Web` controllers. Replaced the controllers' dropdown reads with
     seams: the existing `GetSemesterReferenceOptionsQuery` (its `options()`
     was already the identical query) and `CampusReferenceReader`, plus two new
     owner-side queries, `Catalog\Queries\GetUnitReferenceOptionsQuery` and
     `FacultyWorkforce\Queries\GetLecturerReferenceOptionsQuery`.
     Two same-namespace references broke on the move and had to be re-imported
     explicitly — `CancelExamResitAttemptAction` calls
     `RecordAcademicFinanceCancellationHandoffAction`, which stays behind with
     the rest of the finance-handoff cluster, and
     `CompleteFinanceCancellationOperationAction` injects
     `SendExamResitCancellationNoticeAction`, which moved. Three `@see`
     docblocks pointing at the retake equivalents were fully qualified for the
     same reason. Ratchet: `shared_model_imports` 421 → 389;
     `cross_context_concrete_imports` held at 0; 100 exam-resit tests green.
     Left behind: 6 domain-logic reads in 4 files — `AcademicRecord` (3),
     `Student`, `SyllabusTemplate`, and `Lecture` (in
     `AssignExamResitInvigilatorAction`). Unlike the controller dropdowns these
     sit inside eligibility and record-writing logic, so they need real
     owner-side queries rather than a picker seam.
6. Remove replaced routes/controllers/services immediately and lower all affected ratchets.
   - [x] Ratchet tighten: earlier slices lowered the frozen-* ceilings per
     move but left incidental headroom on the other rules. Re-pinned every
     baseline to the measured current count in both
     `config/migration_debt.php` and
     `MigrationDebtContract::BASELINE_CEILINGS`:
     `shared_model_imports` 494 → 485, `direct_json_responses` 34 → 29,
     `inline_request_validation` 50 → 46, `missing_route_strict_types`
     16 → 13, `legacy_filter_stacks` 25 → 22, `literal_frontend_urls`
     55 → 43. Every rule now sits at its ceiling, so any regression fails
     `migration-debt:inventory --check` immediately.
7. Review import/export parity, scheduler entries, permissions, and campus scoping.
   - [x] Done for this session's four route retirements (see above). The
     remaining Catalog/Delivery/assessment slices from earlier phase-04
     work are not yet re-reviewed under this step.

## Remaining Scope

Measured 2026-07-28 from `migration-debt:inventory --format=json`, filtered to
work packages tagged `phase-04:academic`, after the Progression lifecycle slice.
Route retirement is complete: Academic owns zero frozen routes and zero frozen
controllers.

| Rule | Count | Concentration |
|---|---|---|
| `shared_model_imports` | 161 | 101 in unassigned generic dirs, 59 Delivery, 1 Catalog |
| `inline_request_validation` | 6 | `Http/Web` (Gpa, Placement, ProgressionAudit, PerformanceDashboard) and `Delivery/Http/Api/Lecturer/*` |
| `direct_json_responses` | 1 | `Catalog/Http/Web/SpecializationController` |

Requirement 1 (assign every generic Academic class to one logical context) is the
dominant remainder. The unassigned findings sit in `Academic/Actions` (28),
`Academic/Http` (28), `Academic/Support` (23), `Academic/Queries` (19), and
`Exports`/`Observers`/`Providers` (3).

The generic remainder now clusters into three coherent verticals, each of which
can be assigned as one slice:

- **Retake Course** (~18): the `*RetakeCourse*`/`*RetakeRegistration*` actions
  and queries, plus `RetakeCourseRegistrationController`.
- **Course offering and grading** (~15): the course-offering write actions,
  operational-state and survey queries, `CourseRegistrationObserver`, and the
  `Support/Grading` presenters — all Delivery.
- **Warnings** (~11): `Actions/Warnings/*` and `ListWarningCenterQuery`. Owner
  is not obvious: `StudentWarningLog` is read from Progression-flavoured code
  and from `Delivery/Actions/SendAttendanceWarningAction`, so this one needs an
  ownership call before the move.

The rest (~25) is the Finance charge/obligation gateway, the AI academic
readers, and small one-offs, none of which form a vertical on their own.

Explicitly not phase-04 scope, tagged to later phases by the scanner:
`AcademicRecordGenerationServiceOptimized` (frozen service) and
`routes/api/v1/lecturer.php` (frozen route) belong to phase 9; the 18
`literal_frontend_urls` and 8 `legacy_filter_stacks` under Academic-adjacent
page owners belong to phase 8; 4 migration commands belong to phase 10; a
further 48 shared-model imports belong to phase 5.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Unit/curriculum import and export | Same validated rows and errors |
| Offering/session scheduling conflict | Same rejection and transaction rollback |
| Attendance and grade commit | Idempotent, authorized, auditable |
| Filter/sort/pagination | Whitelisted and query-retaining |
| Canvas retry | No duplicate remote/local state |
| Route snapshot | No public route loss or duplicate legacy route |

## Success Criteria

- [ ] Academic Catalog and Delivery (including assessment) imports are owner-internal only.
- [ ] Their frozen routes/controllers/services and local HTTP/frontend debt are zero.
- [ ] Issues 09 and 10 have accepted evidence and no unresolved parity item.

## Risks and Security

- Scheduling and grade changes are high-impact multi-write operations; use transactions,
  authorization policies, audit logs, and replay-safe integrations.
