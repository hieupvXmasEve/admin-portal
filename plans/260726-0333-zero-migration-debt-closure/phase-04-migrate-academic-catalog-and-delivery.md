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
   - [x] Retake Course assigned to Delivery: claimed `CourseRetakeRegistration`
     for `owned_shared_models.Academic.Delivery` — it had no owner, and outside
     the retake cluster it is read by
     `Delivery/Support/EloquentStudentLifecycleCourseRegistrationGateway`
     (exempted by the claim) and the three finance Support files that stay
     behind. Moved 12 classes into Delivery: 5 retake actions, 2 list queries,
     the 3 `RetakeCourse` FormRequests, `RetakeCourseRegistrationController`,
     and `CourseRegistrationObserver` (`Academic/Observers` was left empty and
     removed; its `CourseRegistration::observe()` registration in
     `AcademicServiceProvider` still points at the moved class). Applied the
     same dropdown seams as the exam-resit controller —
     `GetSemesterReferenceOptionsQuery` and `CampusReferenceReader`. One
     same-namespace reference broke and was re-imported:
     `CancelRetakeCourseRegistrationAction` calls
     `RecordAcademicFinanceCancellationHandoffAction`, the same finance-handoff
     class the exam-resit cancel action needed. Ratchet:
     `shared_model_imports` 389 → 371; `cross_context_concrete_imports` held at
     0; 155 retake and exam-resit tests green.
     Left behind: 5 domain-logic reads — `AcademicRecord` (2), `Student` (2),
     and `Unit`, all inside retake eligibility and registration logic. These
     join the exam-resit residue in the pending seam follow-up.
   - [x] Course offering and grading assigned to Delivery: no ownership claim
     was needed — `CourseOffering` was already Delivery-owned, so 14 of the
     cluster's findings cleared on the move alone. Moved 34 classes: 5
     course-offering/grading actions, 3 queries, the whole
     `Http/Requests/CourseDelivery` directory (12 FormRequests) plus
     `RecalculateCourseOfferingRequest`, all 8 `Http/Web/Admin/CourseOffering*`
     controllers, `Finalize`/`RecalculateApply`/`RecalculatePreview`
     controllers, and the whole `Support/Grading` tree (10 files, of which only
     2 carried findings — the rest had to move to keep the namespace coherent).
     `Academic/Http/Web/Admin` and `Academic/Support/Grading` were left empty
     and removed. Deleted a deprecated duplicate found on the way:
     `Academic\Queries\GetCourseOfferingOperationalStateQuery` was an 18-line
     `@deprecated` static shim delegating to the live Delivery query of the
     same name, with one caller (a test), now pointed at the real query.
     Extended `FacultyWorkforce\Queries\GetLecturerReferenceOptionsQuery` with
     an `availableForAssignment()` method so the catalog-form query drops its
     `Lecture` import. Ratchet: `shared_model_imports` 371 → 354;
     `cross_context_concrete_imports` held at 0.
     Left behind: 6 domain reads — `AcademicRecord` (2, in the grading
     calculator and presenter), `Semester`/`Student` (notification recipients),
     `SyllabusTemplate`, and `FormTarget` (Engagement-owned, read by the
     offering survey query).
     Process note: a whole-directory move must rewrite the *namespace prefix*,
     not a hand-listed set of class names. Listing classes missed 7 sibling
     FormRequests in `Http/Requests/CourseDelivery` and produced 500s until the
     prefix sweep ran. Equally, `composer dump-autoload` must run before test
     results are trusted after any move — two apparent regressions in this
     slice were stale-classmap artifacts that vanished once the autoloader was
     regenerated.
   - [x] Warnings assigned to Progression: `StudentWarningLog` and
     `AcademicWarningSetting` claimed for Progression. The ownership call was
     between Progression and Delivery — Progression wins on weight (3 warning
     actions, `ListWarningCenterQuery`, `WarningCenterController`, and the
     academic-standing threshold logic) and on meaning (academic standing is a
     progression concept). `Delivery/Actions/SendAttendanceWarningAction` keeps
     reading both models and therefore keeps 2 findings; that is the correct
     residue — Delivery *emits* an attendance warning into a record Progression
     owns. Moved 8 classes: `Actions/Warnings/*` (3),
     `Queries/ListWarningCenterQuery`, `Http/Web/WarningCenterController`, and
     `Http/Requests/Warnings/*` (3). `WarningDedupe` stays in
     `Academic/Support` on purpose: it is shared by the Progression query and
     the Delivery action and carries no model imports.
     New coverage first: `tests/Feature/Academic/Progression/WarningCenterWebRoutesTest.php`
     (8 tests) — the warning centre had no HTTP coverage at all. Green against
     the old code before the move. Two fixture notes worth keeping: there is no
     `GpaCalculationFactory` (build via `GpaCalculation::query()->create()` with
     the decimal columns filled), and the controller/FormRequest gate on
     `send_manual_notification` via in-body `abort_unless`/`authorize()`, which
     `withoutMiddleware(Authorize::class)` does not bypass — the test uses
     `Gate::before`.
     Ratchet: `shared_model_imports` 354 → 344; `cross_context_concrete_imports`
     held at 0.
     Process note: the namespace-prefix sweep rewrites *references* but not the
     `namespace` line of a single-file move, because the declaration
     (`namespace App\Modules\Academic\Queries;`) does not contain the class
     name. Both single-file moves in this slice needed their namespace fixed by
     hand; whole-directory moves did not.
   - [x] GPA history, performance dashboard, progression audit, and placement
     assigned to Progression. No new ownership claim was needed: the models
     these classes read are already owned elsewhere, so the win came from
     seams, not exemptions. Moved 14 classes: `GpaHistoryController`,
     `PerformanceDashboardController`, `AcademicProgressionAuditController`,
     `AcademicPlacementController`, `Http/Requests/Placement/*` (4),
     `ListGpaHistoryQuery`, `GetPerformanceDashboardQuery`,
     `Queries/Reporting/GetStudentStatusBySemesterQuery`,
     `Queries/Reporting/GetAcademicReportQuery`, and `Exports/GpaHistoryExport`.
     Replaced the Campus/Semester/Program reads in the three reporting
     controllers with owner-side seams: the existing
     `Institution\CampusReferenceReader`, a new
     `Catalog\Queries\GetProgramReferenceOptionsQuery`, and two new methods on
     `Catalog\Queries\GetSemesterReferenceOptionsQuery` — `records()` (full
     model rows, for the pickers that render more than the reference triple)
     and `auditOptions()` (adds `end_date`). The audit CSV filename now uses
     the existing `codeOrFail()`. Those three controllers now import zero
     shared models.
     Accepted payload delta: the campus dropdowns on the GPA-history and
     performance pages are now name-ordered and carry a `code` key, because
     `CampusReferenceReader::all()` is the shared shape. Both were previously
     `Campus::select('id','name')->get()` in id order. The progression-audit
     picker already matched the shared shape exactly.
     Fixed two defects found on the way, both pre-existing and both proven by
     `git stash` on a clean tree:
     `DomainBoundaryArchitectureTest` was red on dev — the Academic route file
     still referenced `StudentRegistry\Http\Web\StudentController` after the
     student directory moved. The student CRUD and photo-capture routes now
     live in a new `app/Modules/StudentRegistry/routes/web.php` loaded by
     `StudentRegistryServiceProvider::boot()`; all 7 route names, URIs, and
     middleware stacks verified unchanged via `route:list --json`.
     `Progression\Support\LifecycleFormOptions` referenced
     `StudentStatusTransitionPolicy` unqualified from a namespace with no such
     class, so that branch would fatal at runtime; added the import.
     New coverage first: `tests/Feature/Academic/Progression/ProgressionReportingWebRoutesTest.php`
     (13 tests) — none of these four surfaces had HTTP coverage. Green against
     the old code before the move. Recorded behaviour worth keeping: the
     placement `show` route is an ADR-0049 redirect to the Hub lifecycle tab,
     not a page; GPA history lists only `is_finalized` calculations; filter
     state echoed from the query string stays a string, not an int.
     Ratchet: `shared_model_imports` 344 -> 331; `cross_context_concrete_imports`
     held at 0.
     Process note: the FQN sweep only rewrites unescaped namespaces. A
     class-name string literal in PHP source is double-escaped
     (`'App\\Modules\\...'`) and is missed — `AI\Support\MetricCatalog` pins two
     source-query FQNs that way, caught by `AiMetricCatalogQueryPlanTest`.
   - [x] Delivery reference reads, first pass. Claimed `CanvasCourseMapping`
     and `CanvasIntegration` for Delivery: both models exist only for the
     Canvas integration Delivery owns, and outside Delivery only
     `SyncAcademicRecordsCommand` reads one. That claim alone cleared 16
     findings with no code change.
     Then replaced the clean reference reads around it with owner seams:
     `Canvas/CanvasCourseController` takes its semester and unit pickers from
     Catalog (`GetSemesterFilterOptionsQuery::semesters()`, new, with `handle()`
     refactored to call it, and the existing `GetUnitReferenceOptionsQuery`);
     `ListAvailableCanvasCourseOfferingsRequest` validates the semester through
     `AcademicPeriodReader`; `PublishCourseStageChangedNotificationAction` takes
     its semester label from the same reader. Also removed an unused `Student`
     import in `AssessmentExportService` and gave
     `StoreAssessmentDetailRequest` a normal import for the
     `AssessmentComponent` it was reaching by inline FQN.
     Coverage: extended `tests/Feature/Canvas/CanvasCourseIndexTest.php` with
     the picker payloads the seams must reproduce (archived semesters excluded,
     both lists keeping column selection and ordering). Green before the change.
     Ratchet: `shared_model_imports` 331 -> 309; `cross_context_concrete_imports`
     held at 0.
     Deliberately left for a follow-up, because each needs a contract rather
     than a seam call:
     `AcademicRecord` (15) and `Student` (13) are the real block — they are
     domain reads inside eligibility, gradebook, transcript, and attendance
     logic, not reference lookups, so they need Progression/StudentRegistry
     reader contracts with DTOs, not a picker query.
     `Room` (2) needs `SpaceReferenceReader` to grow a bookable-for-campus and
     an excluding-ids method with the same eager-loaded building payload.
     `Lecture` (3) and `Unit` (2) are type dependencies (`instanceof Lecture`,
     `?Unit` return type), so they need DTOs from FacultyWorkforce and Catalog
     rather than a different call.
     `Semester` (3) inside `LecturerDashboardService`, `StudentAttendanceService`,
     and the lecturer `DashboardController` passes the Eloquent model deep into
     private query helpers; converting to `AcademicPeriodReference` is a real
     refactor of those services.
   - [x] AcademicRecord block cleared in two moves.
     First, extracted the policy the model was carrying. `AcademicRecord` held
     the percentage-to-grade scale (`calculateLetterGrade`,
     `calculateGradePoints`) and the `FAILURE_*` vocabulary as statics, so a
     weighted-percentage calculator or a pass/fail classifier had to import the
     record model to reach a rule that never touches a record. Both now live in
     `App\Shared\Support\Academic\CourseGradeScale`, with `AcademicRecord`
     delegating so persisted values cannot drift.
     `SaveLecturerGradebookScoresAction`, `Delivery\Support\Grading\DefaultWeightedPercentageCalculator`,
     and `Academic\Support\FailureReasonClassifier` now depend on the scale
     alone. Covered by `tests/Unit/Academic/CourseGradeScaleTest.php`, including
     the band boundaries and the model's delegation.
     Second, moved ownership of `AcademicRecord` from Progression to Delivery,
     with the user's agreement. Delivery writes the course result — Canvas grade
     sync creates records, exam-resit completion, the lecturer gradebook, and
     EGC remediation update them — while Progression only reads, and reads
     through `CourseResultProgressionReader`, which `Delivery/Support` already
     implements. The map had the direction backwards, which is why Delivery
     carried 13 findings against a model it owns in practice.
     Two classes moved back with it: `GetUnitAcademicOutcomesQuery` (grade
     aggregates per unit and offering, feeding Delivery's course statistics) and
     `EloquentCourseOfferingAttemptWriter` (moves attempts between offerings).
     Both were written as Progression-owned seams in the CourseStatistics slice
     *only because* the model was mapped to Progression; with the map corrected,
     that seam pointed the wrong way. This reverses that earlier slice's
     boundary call deliberately, on new evidence.
     Still findings, deliberately: `Progression\Queries\Reporting\GetAcademicReportQuery`
     and `Progression\Queries\GetCourseRankingQuery` read the model directly and
     stay red until they take a Delivery reader contract.
     Ratchet: `shared_model_imports` 309 -> 295; `cross_context_concrete_imports`
     held at 0.
   - [x] Student block, first pass, plus the architecture tests that had gone
     quiet. Three Delivery classes came off the model:
     `GetStudentAcademicAttendanceSummaryQuery` only ever used
     `$student->id`, so it takes an `int` — no DTO needed where a scalar is the
     real dependency. `RemediateEgcAttendanceFailuresAction` resolves its
     hand-typed `--student` argument (a code or a raw id) through
     `StudentReferenceReader`; `RemediateEgcAttendanceFailuresIdentifierTest`
     covers it and passes against the old query too, so the parity is proven
     rather than assumed. `PublishCourseStageChangedNotificationAction` moved to
     Progression: a course-stage change is a progression event, both callers are
     Progression actions, and it only forwarded the student to a Progression
     event factory.
     Ratchet: `shared_model_imports` 295 -> 293 (phase-04 scope drops by 3; the
     moved action re-tags to phase-05).
     Also repointed six architecture tests that still named pre-move paths.
     `file_get_contents` on a moved file raises, so those boundary assertions had
     not actually run since phase-04 started moving classes:
     `StudentLifecycleProgressionFinanceBoundaryArchTest`,
     `CourseDeliveryAssessmentBoundaryArchTest`,
     `CourseOfferingCatalogBoundaryArchTest`, `CourseRosterDeliveryBoundaryArchTest`,
     `FacilitiesDeliveryBoundaryArchTest`, and
     `TeachingEligibilityAssignmentBoundaryTest`. Worth remembering: a path-based
     arch test fails open when the path moves, so a move can silence the very
     guard meant to police it.
     Two of them are now legitimately red and left that way:
     `CourseRosterDeliveryBoundaryArchTest` scans all of `Delivery/` for
     `Student`/`Unit`/`Semester` persistence, and
     `TeachingEligibilityAssignmentBoundaryTest` scans it for `Lecture`. They
     were green only because those classes used to sit in generic `Academic/`
     directories the recursive scan never reached. They now assert exactly the
     remaining Delivery work, so they are the acceptance signal for it — do not
     weaken them to get green.
   - [x] Student attendance chain, second Student pass. `route:list` showed only
     two of `Delivery/Http/Api/Student/AttendanceController`'s four methods are
     registered, so the unrouted `index` and `statistics` went, along with their
     query methods, the FormRequest only `index` used, and the 20
     `StudentAttendanceService` methods left with no caller once those were gone
     — 993 lines down to about 500. The dead-code cascade was done by repeatedly
     removing any method with zero remaining `$this->` references, never by eye.
     The surviving chain (`course`, `report`) takes an `int`. The one non-id use
     of the model was `$student->courseRegistrations()`, and `CourseRegistration`
     is Delivery's own model, so the service queries it directly.
     Coverage: `tests/Feature/Api/V1/Student/AttendanceControllerTest.php` over
     both live endpoints, green before the change.
     Defect found and recorded, not fixed here: the enrolled path of
     `v1.student.attendance.course-attendance` filters
     `attendances.course_offering_id` and orders by `attendances.session_date`,
     and neither is a column on `attendances` — both live on `class_sessions`.
     Every enrolled request returns a 422 carrying a SQL error, so that endpoint
     has never worked. `formatAttendanceRecords` reads `$record->session_date`
     too, so a fix has to reshape the read, not just the where clause. The test
     pins the current behaviour explicitly as current, not intended.
     Ratchet: `shared_model_imports` 293 -> 290.
   - [x] Retake registration resolved through StudentRegistry.
     `CreateRetakeCourseRegistrationAction` needed three fields off the student:
     `status`, program id, and curriculum version id. `StudentReference` already
     carried the first two, so it gained one nullable `curriculumVersionId`
     rather than the action keeping the whole model for a single column — the
     DTO is the right place for a field the registry owns and other consumers
     will want.
     `PrerequisiteValidationService` now takes a student id. It only ever used
     `$student->academicRecords()`, and `AcademicRecord` is Delivery's own model
     since the ownership fix, so it queries that directly; both call sites pass
     the id. Behaviour delta, deliberate: a student the registry cannot resolve
     fails validation on `student_id` instead of raising model-not-found, which
     matches how the action reports every other bad input.
     Ratchet: `shared_model_imports` 290 -> 289. The frozen service swapped a
     `Student` import for an `AcademicRecord` one, so the count moves by the
     action alone; the boundary still improved, since the service now reads a
     model its own context owns.
     The `course-attendance` defect found in the previous slice is recorded in
     the plan index under Deferred defects, at the user's direction.
   - [x] Remaining `direct_json_responses`/`inline_request_validation` mechanical
     cleanup, 2026-08-16. `Catalog/Http/Web/SpecializationController` moved
     `apiDestroy`/`apiUpdateCurriculumVersion` to `ApiResponse::success()`/`error()`
     (test-verified compatible: both only assert `success`/`message`/`data.*`).
     `bulkDelete` kept its flat `deleted`/`failed` envelope via
     `ApiResponse::compatible()`, documented inline, because
     `SpecializationManagementTest` pins that exact raw shape and the frontend
     caller never parses the response body. `Delivery/Http/Api/Lecturer/StudentController::bulkActions`
     and `TimetableController::cancelSession`/`bulkUpdateSessions` moved inline
     `$request->validate()` to three new FormRequests
     (`StudentBulkActionRequest`, `CancelSessionRequest`, `BulkUpdateSessionsRequest`)
     under `App\Http\Requests\Api\V1\Lecturer`, matching the existing sibling
     FormRequests in that namespace. Added first-time characterization coverage
     (`tests/Feature/Api/V1/Lecturer/LecturerBulkAndSessionActionsFormRequestTest.php`)
     since none of the three endpoints had a test before. Coverage exposed a
     pre-existing bug unrelated to the extraction: `bulkActions` passed its
     message string positionally as `ApiResponse::success()`'s `$meta` array
     argument, 500ing on every call; fixed to a named `message:` argument.
     Ratchet: `direct_json_responses` 21 -> 20, `inline_request_validation` 40 -> 38.
   - [x] Remaining `shared_model_imports` one-offs, 2026-08-16 (8 findings across
     8 files, all in generic `Academic/*` dirs not owned by any submodule):
     - `Catalog/Models/CampusPeriodSchedule`: deleted an unused `campus()`
       belongsTo (no caller anywhere; every consumer already filters by
       `campus_id` directly). Dead code, not a seam.
     - `Providers/AcademicServiceProvider`: its one `CourseRegistration::observe()`
       call moved into a new `Delivery/Support/CourseRegistrationObserverRegistrar`,
       since Delivery already owns that model. The provider now only calls the
       registrar — it stays the single cross-cutting provider for the whole
       Academic module (one provider per top-level module in
       `bootstrap/providers.php`) without importing a model it doesn't own.
       Rejected an `OWNED_SHARED_MODELS` allowlist grant for this first — that's
       exactly the "allowlist increase to hide findings" the plan's non-goals
       forbid; reverted once the registrar seam proved it wasn't needed.
     - `Queries/GetCampusDetailQuery` + `Http/Web/CampusDetailController` +
       `Http/Requests/Campus/ShowCampusRequest`: this whole slice was Institution's
       domain wearing an Academic path — pure Campus/Building read, no Academic
       model or logic anywhere. Moved into `Institution/Http/Web/CampusController::show`
       (a `forShow()` method added next to the existing `forEdit()` on
       `Institution/Queries/GetCampusQuery`), same route name `campuses.show` and
       `can:view_campus` gate preserved. Route re-registered after `edit`/`update`
       in `Institution/routes/web.php`, not before `create` — a `{campus}` wildcard
       ahead of the literal `/create` segment would have swallowed it. First-time
       coverage added to `InstitutionBoundaryTest`'s existing campus-URL-parity test.
     - `Http/Web/StudentCompletedUnitsController` (3 findings: `Campus`, `Program`,
       `Semester`): all three were `::find($id)?->name` label lookups for the
       export filename, not queries. Routed through contracts already injected
       or available — `CampusReferenceReader`, `ProgramReferenceReader` (added to
       `export()`'s params), and a new `find()` method on the already-injected
       `Catalog/Queries/GetSemesterFilterOptionsQuery` (its docblock already
       stated the seam's purpose: "so consumers reach Semester through a
       Catalog-owned seam instead of importing the shared model directly").
     - `Http/Requests/StoreStudentActionRequest`: its one `Semester::query()->find()`
       (inside the from-semester-not-before-active-semester rule) now goes through
       the same new `GetSemesterFilterOptionsQuery::find()` seam.
     - `Http/Requests/UpdateStudentActionRequest`: its `use App\Models\StudentActionLog`
       was docblock-only (`@var StudentActionLog $actionLog` on a route-bound
       value, no runtime use). Pint's `fully_qualified_strict_types` fixer
       reintroduces the import for any FQCN reference in a docblock, including a
       leading-backslash one — tried that first, watched Pint revert it — so the
       fix is dropping the annotation, not qualifying it.
     Ratchet: `shared_model_imports` 398 -> 379. Only 8 of those 19 are this
     slice; the other 11 were already earned by the `DeferCase`/`DeferCaseItem`
     move in `c78b66f6e`, which shipped without lowering its own baseline. Caught
     here by re-measuring instead of trusting the previous run, and re-pinned in
     the same change, so every rule sits at its ceiling again.
   - [x] Delivery FacultyWorkforce cluster, 2026-08-16 (3 findings, all `Lecture`):
     `AssignExamResitInvigilatorAction` swapped its `Lecture::query()->findOrFail()`
     lookup for the already-bound `LecturerReferenceReader::find()`; a miss now
     throws `ValidationException` on `lecture_id`, consistent with the action's
     other two failure modes, rather than the `ModelNotFoundException` /
     `NotFoundHttpException` 404 `findOrFail` gave (same effective outcome for
     the one real caller, `ExamScheduleController::assignInvigilator`, which had
     no dedicated 404 handling either way; code review flagged the domain
     Action's `abort()` HTTP dependency as avoidable, so this closes that too).
     `LecturerAssessmentRequest::canAccessBoundCourseOffering()` swapped
     `$lecturer instanceof Lecture` for `$lecturer instanceof LecturerTeachingActor`
     — the model already implements that contract, so the authenticated-actor
     check is unchanged at runtime. `GetCourseOfferingCatalogFormQuery` dropped
     `GetLecturerReferenceOptionsQuery` (which still queries `Lecture` directly
     for its remaining caller, `ExamScheduleController::forCampus`) for the
     already-bound `AvailableLecturerReader`, the same precedent
     `CourseOfferingSplitController` uses. Its now-unused
     `availableForAssignment()` method (byte-for-byte duplicate of
     `EloquentAvailableLecturerReader::all()`'s query) was deleted in the same
     slice so the two don't drift out of sync. Selected columns are identical
     (`id`, `first_name`, `last_name`, `email`, `academic_rank`); the four
     appended accessors Eloquent used to serialize (`full_name`, `display_name`,
     `years_of_service`, `is_contract_active`) are gone from this payload, same
     as `CourseOfferingSplitController`'s existing `lectures` prop — the
     `LectureCombobox` consumer already tolerates this via its `display_name`
     fallback chain, so it's a frontend TS type-vs-runtime gap that predates
     this slice, not a new one; not fixed here.
     `tests/Feature/Architecture/TeachingEligibilityAssignmentBoundaryTest` was
     red before this slice and is now green as a side effect; the other 3
     pre-existing `Architecture` failures (`AcademicPeriodBoundaryArchTest`,
     `CourseDeliveryAssessmentBoundaryArchTest`, `CourseRosterDeliveryBoundaryArchTest`)
     are unchanged, confirmed by running the suite with and without this slice
     stashed. Ratchet: `shared_model_imports` 379 -> 376.
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

Re-measured 2026-08-16 from `migration-debt:inventory --format=json`, filtered to
work packages tagged `phase-04`. Route retirement is complete: Academic owns zero
frozen routes and zero frozen controllers.

| Rule | 2026-07-28 | 2026-08-16 (session start) | 2026-08-16 (after Lecture cluster) | Concentration at the July measurement |
|---|---:|---:|---:|---|
| `shared_model_imports` | 53 | 55 | 44 | 26 in unassigned generic dirs, 26 Delivery, 1 Catalog |
| `inline_request_validation` | 2 | 2 | 0 | `Delivery/Http/Api/Lecturer/{Student,Timetable}Controller` — cleared |
| `direct_json_responses` | 1 | 1 | 0 | `Catalog/Http/Web/SpecializationController` — cleared |

Total 44 remaining (was 58 at session start; 55 after the mechanical slice; 47
after the one-off slice; 44 after the Delivery `Lecture` cluster, 2026-08-16 —
see step 5 above). The cluster analysis below is from July and still describes
the shape of the remainder; re-derive exact paths before starting a slice.

Requirement 1 (assign every generic Academic class to one logical context) is the
dominant remainder. What was unassigned now reduces to two clusters that both
need an architecture decision rather than a move — all 8 one-off findings that
didn't need one are cleared (see step 5 above):

- **Finance charge/obligation gateway** (12 at the 2026-08-16 live measurement,
  10 at the July measurement): `AcademicFinanceChargeSourceGateway` (6),
  `AcademicFinanceObligationSource` (2), `AcademicObligationSettlement` (2),
  plus `CompleteFinanceCancellationOperationAction` (2). This is the
  Academic-Finance boundary and is governed by ADR-0026.
- **AI academic readers** (9): `AiAcademicStudentProfileReader` (5) and
  `AiAcademicEntitySearchReader` (4). Both read across Catalog, Delivery, and
  Progression, so they belong to no single submodule; the likely answer is a
  contract rather than an owner.

The larger remaining block is not unassigned at all: 23 shared imports (26
before the `Lecture` cluster above) sit inside Delivery, live-recounted
2026-08-16 by model rather than from the July snapshot: `Student` 8 (across
`LecturerStudentService`, `AssessmentReportService`, `AssessmentGradeExcelService`,
the three `ListExamResit*`/`ListRetakeCourseEligibleStudentsQuery` eligibility
queries, `BulkCreateExamResitAttemptsAction`, and one of
`SendAttendanceWarningAction`'s four), `Semester` 4, `SyllabusTemplate` 3,
`User` 3, `Unit` 2, `AcademicWarningSetting`/`StudentWarningLog`/`StudentNote`
1 each. `SendAttendanceWarningAction` needs an attendance-warning fixture
before its remaining three (`AcademicWarningSetting`, `StudentWarningLog`,
`User`) can move — those two models are Progression-owned, so the fix is a
Progression-owned warning reader/writer contract, not a Delivery-local swap.
`Unit` (`LecturerCourseService`, `CreateRetakeCourseRegistrationAction`) is a
genuine type dependency needing a Catalog-owned DTO, same shape as the
`Lecture` cluster just closed; `Semester` and `SyllabusTemplate` are used as
full Eloquent query subjects (`Semester::where(...)`, `SyllabusTemplate::query()`),
not just type hints, so each needs a new Catalog-owned query/contract method,
not a drop-in reader swap.

Explicitly not phase-04 scope, tagged to later phases by the scanner:
`AcademicRecordGenerationServiceOptimized` (frozen service) belongs to phase 9;
`routes/api/v1/lecturer.php` carries a live portal workflow and was retagged from
phase 9 to phase 5 on 2026-08-16; the `literal_frontend_urls` and
`legacy_filter_stacks` under Academic-adjacent page owners belong to phase 8
(18 and 8 respectively at the July measurement, against phase-8 totals of 40 and
24 on 2026-08-16); 4 migration commands belong to phase 10; and the shared-model
imports plus inline validation now inside `Academic/Progression` re-tag to
phase 5, which is why phase-04's counts drop faster than the global ratchet does.

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
