---
title: "Phase 4b: Retire SemesterEnrollmentController"
status: superseded
priority: P0
effort: "S (was L/16-21h — scope cut before any sub-slice started)"
dependencies: [4]
---

# Phase 4b: Retire SemesterEnrollmentController

## SUPERSEDED (2026-07-28)

Before any sub-slice below was started, the product owner cut scope directly:
the `/semesters/{id}/enrollment` page keeps only the campus/enrollment
overview and the `generateEnrollments` action. Everything else — Suggest
Courses, Registration Statistics, both open-course flows, bulk-register,
registrable-students — was deleted outright, not migrated. This answers
**Open Question 1 as "delete"** for 4 of the 6 non-`show`/`generateEnrollments`
methods (the plan's Path B already covered 2 of them as consumer-less; the
other 4 were live UI the owner chose to cut) and makes **Open Question 6/7
moot** (the code they concerned no longer exists).

What actually happened instead of S1-S5 below:
- `SemesterEnrollmentController` trimmed from 1252 lines / 8 methods to 227
  lines / 2 methods (`show`, `generateEnrollments`) — both **byte-identical**
  to before, only the other 6 methods and their private helpers were deleted.
- `routes/web/semester.php` trimmed from 8 routes to 2 (`show`, `generate`).
- Orphaned `OpenSingleCourseOfferingAction`, `OpenSingleCourseOfferingRequest`,
  and `resources/js/components/ui/sonner/HeadlessToastWithProps.vue` deleted
  (sole consumer was the removed `openSingleCourse` method/its FE modal).
  Dead route-name constants removed from `SemesterRoutes.php` and
  `semester-routes.ts`. `ziggy.js` regenerated.
- `inline_request_validation` ratchet lowered 59 → 50 (both inline validate()
  calls in this controller are gone).
- Fixed a real correctness bug surfaced by the trim: `generateEnrollments`
  opened `DB::beginTransaction()` before two early-return guards that never
  committed or rolled back it back — moved the transaction to start only
  after both guards, before the first write.

**What remains open**: `show()`/`generateEnrollments()` are still frozen in
`app/Http/Controllers/Web` (still on the `frozen_controllers`/`frozen_routes`
allowlists — unchanged paths, unchanged counts). If this file is ever retired
into `App\Modules\Academic\Delivery`, that is now a **small** slice (2 methods,
~230 lines) — closer in size to the Module slice than to the original 1252-line
estimate. Re-scout `OWNED_SHARED_MODELS` for `Enrollment` ownership (still
unowned per Open Question 5 below) before that future slice; everything else
in this document (S1, S2, S3, the full S5 migrate path, the Test Scenario
Matrix, and Open Questions 2/3/4) is now moot and kept below only as historical
record of what was evaluated.

## Overview (historical — pre-cut scope)

Retire the frozen split route file `routes/web/semester.php` and the 1252-line
`app/Http/Controllers/Web/SemesterEnrollmentController.php` into
`App\Modules\Academic\Delivery`, in five independently-shippable sub-slices.

This controller is an order of magnitude larger than the four completed slices
(Course Offerings, Course Statistics, Specializations, Modules), so it does not
move in one shot. Route paths, route names, permissions, and JSON payloads stay
byte-identical; only ownership moves.

### Verified starting state

Guard output on `dev` (`artisan migration-debt:inventory --check`, all pass):

| Rule | Current | Baseline |
|---|---:|---:|
| frozen_controllers | 44 | 44 |
| frozen_routes | 21 | 21 |
| shared_model_imports | 494 | **494 (zero headroom)** |
| direct_json_responses | 30 | 34 |
| inline_request_validation | 51 | 59 |

`shared_model_imports` has **zero headroom**. `MigrationDebtInventory::collectPathFindings()`
(`app/Support/MigrationDebt/MigrationDebtInventory.php:199`) only scans
`app/Modules/**` for shared-model imports, so the controller contributes **0**
findings today and every `use App\Models\X` it carries becomes **new** debt the
moment the file lands under `app/Modules/`. A naive move costs **+7**
(`AcademicHold`, `AcademicRecord`, `Campus`, `CurriculumUnit`, `Enrollment`,
`Semester`, `Student`) and fails the guard. Every sub-slice below is designed to
land at net **0**.

### Ownership: Delivery, verified

- `EnrollStudentInCourseOfferingAction` → `app/Modules/Academic/Delivery/Actions/EnrollStudentInCourseOfferingAction.php`
- `OpenSingleCourseOfferingAction` → `app/Modules/Academic/Delivery/Actions/OpenSingleCourseOfferingAction.php`
- `MigrationDebtContract::OWNED_SHARED_MODELS['Academic']['Delivery']` (`app/Support/MigrationDebt/MigrationDebtContract.php:117`)
  owns `CourseOffering` + `CourseRegistration` — the two models this controller
  writes most.

Target: `App\Modules\Academic\Delivery\Http\Web\Admin\SemesterEnrollment*`,
alongside the existing `Delivery\Http\Web\Admin\CourseRegistrationController`
(`app/Modules/Academic/Delivery/Http/Web/Admin/CourseRegistrationController.php:40`).
Routes land in `app/Modules/Academic/routes/web.php`.

**Not Catalog.** Catalog owns `Semester`/`Unit`/`CurriculumUnit`
(`MigrationDebtContract.php:101`), but the workflow writes offerings and
registrations, which are Delivery. Semester/CurriculumUnit reads go through
Catalog seams (below), matching the Course Statistics precedent.

### No frozen service behind it

Verified by reading the file: all DB work is inline plus two Delivery Actions.
`grep -rn "Service" app/Http/Controllers/Web/SemesterEnrollmentController.php`
returns nothing. Unlike the Module slice, there is no `ModuleService`-equivalent
to leave frozen. `frozen_services` is unaffected by this phase.

### Two endpoints have no live consumer

Grep across `resources/js`, `FE/student-nuxt`, `FE/lecturer-nuxt`, `app`, `tests`
(excluding generated `resources/js/ziggy.js`):

- `bulkRegisterStudents` — the only frontend call site is **commented out** at
  `resources/js/pages/Semesters/Enrollment.vue:220`. No other caller.
- `getRegistrableStudents` — **no** call site anywhere. Only a dead helper
  `apiEnrollmentRegistrableStudents` in `resources/js/constants/semester-routes.ts:69`
  and the unused constant `SemesterRoutes::API_ENROLLMENT_REGISTRABLE_STUDENTS`.

A modern replacement for semester-wide bulk registration already exists in
Delivery: `BulkRegisterCourseOfferingStudentsAction`
(`app/Modules/Academic/Delivery/Actions/BulkRegisterCourseOfferingStudentsAction.php:12`)
driven by `Academic\Http\Web\Admin\CourseOfferingRegistrationController`.

Both routes are still **registered and reachable** by an authenticated user with
`edit_semester` / `view_semester`, so they are not "unregistered dead shells"
like the Calendar retirement. Deleting them is a **product decision** — see Open
Question 1. The plan assumes migrate-verbatim and notes the cheaper delete path
per slice.

## Requirements

- [ ] Route URIs, route names, HTTP verbs, and `can:` middleware preserved verbatim.
- [ ] JSON payloads byte-identical (top-level keys such as `enrollments_created`,
      `offerings_created`, `registrations_created`, `errors`, `batch`, `results`
      are outside `data` today and must stay there).
- [ ] `show()` Inertia component name `Semesters/Enrollment` and the three props
      `semester`, `enrollmentStats`, `campusStats` unchanged.
- [ ] Unknown-semester 404 behaviour preserved after route-model binding changes.
- [ ] `shared_model_imports` stays exactly 494 after every sub-slice.
- [ ] No new `resources/js` change required (frontend calls `api.post('/api/...')`
      literal paths that do not match the `literal_frontend_urls` regex at
      `MigrationDebtInventory.php:248`; leaving them untouched keeps that rule flat).

## File Inventory

### New files

| Path | Purpose | Slice |
|---|---|---|
| `app/Modules/Academic/Delivery/Http/Web/Admin/SemesterEnrollmentStatsController.php` | `getRegistrationStats`, `openSingleCourse` | S1 |
| `app/Modules/Academic/Catalog/Queries/FindSemesterForEnrollmentQuery.php` | Catalog-owned `Semester::findOrFail()` seam, returns `object` | S1 |
| `app/Modules/Academic/Delivery/Http/Web/Admin/SemesterEnrollmentPageController.php` | `show` | S2 |
| `app/Modules/StudentRegistry/Queries/GetCampusEnrollmentEligibilityCountsQuery.php` | eligible / enrolled / not-enrolled student counts | S2 |
| `app/Modules/Academic/Delivery/Http/Web/Admin/SemesterCourseDemandController.php` | `getSuggestedCourses`, `getRegistrableStudents` | S3 |
| `app/Modules/Academic/Catalog/Queries/GetCurriculumSemesterUnitsQuery.php` | `CurriculumUnit` reads for a `(curriculum_version_id, semester_number)` pair | S3 |
| `app/Modules/Academic/Progression/Queries/GetSemesterProgramEnrollmentsQuery.php` | `Enrollment` reads (campus-scoped, `in_progress`) | S3 |
| `app/Modules/Academic/Delivery/Http/Web/Admin/SemesterEnrollmentGenerationController.php` | `generateEnrollments`, `bulkOpenCourses` | S4 |
| `app/Modules/Academic/Delivery/Http/Requests/BulkOpenCourseOfferingsRequest.php` | replaces inline validate in `bulkOpenCourses` | S4 |
| `app/Modules/Academic/Progression/Actions/GenerateSemesterProgramEnrollmentsAction.php` | `Enrollment` writes + `semester_number` derivation | S4 |
| `app/Modules/Academic/Delivery/Http/Web/Admin/SemesterBulkRegistrationController.php` | `bulkRegisterStudents` (only if Open Q1 = migrate) | S5 |
| `app/Modules/Academic/Delivery/Http/Requests/BulkRegisterSemesterStudentsRequest.php` | replaces inline validate in `bulkRegisterStudents` (only if migrate) | S5 |
| `app/Modules/Academic/Delivery/Support/CourseOfferingScheduleConflictDetector.php` | `hasScheduleConflict` / `normalizeDays` / `parseTime`, unit-testable (only if migrate) | S5 |
| `app/Modules/Academic/Progression/Queries/GetStudentRegistrationBlockersQuery.php` | `AcademicHold` + `AcademicRecord` reads (only if migrate) | S5 |

### Test files (characterization, written FIRST against current code)

| Path | Slice |
|---|---|
| `tests/Feature/Academic/SemesterEnrollmentStatsTest.php` | S1 |
| `tests/Feature/Academic/SemesterEnrollmentPageTest.php` | S2 |
| `tests/Feature/Academic/SemesterCourseDemandTest.php` | S3 |
| `tests/Feature/Academic/SemesterEnrollmentGenerationTest.php` | S4 |
| `tests/Feature/Academic/SemesterBulkRegistrationTest.php` | S5 |
| `tests/Unit/Academic/CourseOfferingScheduleConflictDetectorTest.php` | S5 (migrate path only) |

### Edited files

| Path | Change | Slice |
|---|---|---|
| `app/Modules/Academic/routes/web.php` | add moved routes | S1-S5 |
| `routes/web/semester.php` | delete moved route lines; delete file in S5 | S1-S5 |
| `app/Http/Controllers/Web/SemesterEnrollmentController.php` | delete moved methods; delete file in S5 | S1-S5 |
| `app/Support/MigrationDebt/MigrationDebtContract.php` | `OWNED_SHARED_MODELS['Academic']['Progression'][] = 'Enrollment'` (S3); `BASELINE_CEILINGS` frozen_routes 21→20, frozen_controllers 44→43, direct_json_responses 34→29, inline_request_validation 59→50 (S5) | S3, S5 |
| `config/migration_debt.php` | mirror both edits | S3, S5 |
| `config/migration_debt_paths.php` | drop `routes/web/semester.php:136` and `app/Http/Controllers/Web/SemesterEnrollmentController.php:117` | S5 |

### Deleted files

| Path | Slice |
|---|---|
| `app/Http/Controllers/Web/SemesterEnrollmentController.php` | S5 |
| `routes/web/semester.php` | S5 |

### Deleted dead symbols (S5 cleanup)

- `resources/js/constants/semester-routes.ts:69-70` — `apiEnrollmentRegistrableStudents`,
  `apiEnrollmentBulkRegister` helpers (never imported). Delete only if Open Q1 = delete.
- `resources/js/pages/Semesters/Enrollment.vue:215-240` — commented-out
  `bulkRegisterStudents` block. See Open Question 2.
- `use App\Models\Unit;` (`SemesterEnrollmentController.php:18`) — **verified unused**
  (`Unit::` appears nowhere in the file; `exists:units,id` is a string rule).
  Drop it during S4, never carry it into a module file.

## Interface Checklist

- Every moved endpoint returns `ApiResponse::compatible($body, $status)` rather
  than `response()->json(...)`. `compatible()` is documented as
  "return a pre-existing endpoint contract without changing its envelope"
  (`app/Http/Responses/ApiResponse.php:13`), emits the identical body, and is
  **not** matched by the `direct_json_responses` regex (`MigrationDebtInventory.php:216`
  matches literal `response()->json(`). This is the only way to move raw-JSON
  methods into a module without inflating that rule. See Open Question 3.
- `openSingleCourse` keeps `ApiResponse::success/error/validationError` as-is.
- Route-model binding: the Delivery controllers accept `string $semester`
  (raw route param) and resolve via `FindSemesterForEnrollmentQuery::handle(int): object`,
  which calls `Semester::findOrFail()` inside Catalog. Returning `object` (not
  `Semester`) is required to avoid the import and follows the existing precedent
  `BulkRegisterCourseOfferingStudentsAction::run(object $courseOffering, ...)`
  (`BulkRegisterCourseOfferingStudentsAction.php:15`). `findOrFail` preserves the
  implicit-binding 404.
- `Campus` reads go through `App\Shared\Contracts\Institution\CampusReferenceReader`
  (`find(int): ?CampusReference`), the precedent already used by
  `Catalog\Http\Web\AcademicPeriodController` and the Module slice.
- `Student` aggregate counts go through a new StudentRegistry query, not
  `StudentReferenceReader` (its DTO shape does not carry the eligibility filters).
- Campus context: `session('current_campus_id')` is used by 7 of 8 methods but
  `bulkOpenCourses` uses `app('campus')->id` (`SemesterEnrollmentController.php:381,394`).
  **Preserve both verbatim.** Do not unify — see Open Question 4.

## Dependency Map

```
S1 stats+single-course (pilot: namespace, Semester seam, compatible envelope, route mechanics)
   |
   +-> S2 show          (Campus + Student seams)
   |
   +-> S3 demand reads  (CurriculumUnit + Enrollment seams; Enrollment ownership config)
   |        |
   |        v
   +-> S4 writes        (needs S3's Enrollment seam)
            |
            v
        S5 bulk-register + teardown (deletes both files, lowers all four ratchets)
```

S2, S3 are parallel-safe after S1 (disjoint new files; both touch
`routes/web/semester.php` and the old controller, so serialize the merge or
rebase — **do not run them as concurrent worktrees**).

## Implementation Steps

Each sub-slice follows the identical shape proven by commits `b1ab7bcd`,
`95e6e447`, `d0c2ba05`, `58f30396`:

1. Write characterization tests against the **current** code; confirm green.
2. Create the owner-scoped seams (queries/actions) in the module that owns the model.
3. Move the controller method(s) into the Delivery controller; swap raw JSON to
   `ApiResponse::compatible`; swap inline validate to a FormRequest.
4. Register the moved routes in `app/Modules/Academic/routes/web.php`; delete the
   corresponding lines from `routes/web/semester.php`.
5. `./scripts/dev.sh composer dump-autoload` + `./scripts/dev.sh artisan optimize:clear`.
6. `./scripts/dev.sh artisan migration-debt:inventory --check` — **must still show
   shared_model_imports 494**.
7. Re-run the characterization tests unchanged; they must stay green.
8. `./scripts/dev.sh artisan route:list --name=semesters.enrollment` snapshot diff
   against the pre-slice snapshot: identical URIs, names, methods, middleware.
9. Spawn a `code-reviewer` subagent on the diff. Ask before committing.

### S1 — Registration stats + single-course open (pilot)

Moves `getRegistrationStats` (`:482`) and `openSingleCourse` (`:444`).
Cheapest possible pilot: `getRegistrationStats` touches only `CourseOffering`
(Delivery-owned, free) plus `Semester`; `openSingleCourse` is already
FormRequest + Action + `ApiResponse` and touches only `Semester`.

Establishes the `FindSemesterForEnrollmentQuery` seam and the
`ApiResponse::compatible` convention that every later slice reuses.

Leave `OpenSingleCourseOfferingRequest` where it is
(`App\Modules\Academic\Http\Requests\CourseDelivery\`) — same top-level module,
no `cross_context_concrete_imports` finding, and moving it buys zero debt
reduction. YAGNI.

Debt delta: all rules flat (old file still holds 6 methods).

### S2 — Enrollment page (`show`)

Moves `show` (`:35`). Seams: `CampusReferenceReader` for campus name/code;
`GetCampusEnrollmentEligibilityCountsQuery` (StudentRegistry) for the three
`Student` counts at `:82`, `:90`, `:101`.

`$semester->load(['enrollments' => ...])` and `$semester->enrollments()` are
relation strings — **no `Enrollment` import needed** for this slice.

Debt delta: all rules flat.

### S3 — Course demand reads

Moves `getSuggestedCourses` (`:243`) and `getRegistrableStudents` (`:1133`).

Config change first: add `'Enrollment'` to
`OWNED_SHARED_MODELS['Academic']['Progression']` in both
`MigrationDebtContract.php` and `config/migration_debt.php`. `Enrollment` is
currently owned by **no** module (verified: absent from the contract), and
Progression is the right owner — `ProgramEnrollmentReader`'s own docblock calls
it "the Progression-owned enrollment projection"
(`app/Shared/Contracts/Academic/ProgramEnrollmentReader.php:12`). This is an
ownership assignment required by Phase 4's first requirement, not an allowlist
inflation: the only existing in-module `Enrollment` import is
`app/Modules/Academic/Http/Web/StudentStatusController.php` (un-nested `Academic/Http`
path, unaffected by nested-owner resolution at `MigrationDebtInventory.php:445`),
so the count moves by **0**. See Open Question 5.

Seams: `GetCurriculumSemesterUnitsQuery` (Catalog),
`GetSemesterProgramEnrollmentsQuery` (Progression). `CourseOffering` /
`CourseRegistration` stay direct (Delivery-owned).

Debt delta: all rules flat.

### S4 — Writes (`generateEnrollments`, `bulkOpenCourses`)

Moves `generateEnrollments` (`:136`) and `bulkOpenCourses` (`:359`).

- `generateEnrollments`: `Enrollment` writes + `semester_number` derivation move
  into `GenerateSemesterProgramEnrollmentsAction` (Progression). The
  `DB::beginTransaction/commit/rollBack` shape, per-student try/catch, the
  `errors[]` string format, and the `semesterNumber > 8` skip message
  (`"Student {code} has exceeded maximum semester limit"`) move verbatim.
- `bulkOpenCourses`: inline validate (`:361`) becomes
  `BulkOpenCourseOfferingsRequest` with identical rules. `app('campus')->id`
  preserved verbatim. Defaults `30` / `'in_person'` / `waitlist_capacity 10` /
  `notes 'Unified offering for all curriculum versions'` preserved verbatim.

Drop the unused `use App\Models\Unit;` here.

Debt delta: `inline_request_validation` still counts the old file (S5's method
still has `$request->validate(`) — flat until S5.

### S5 — Bulk registration + teardown

**Path A (Open Q1 = migrate).** Moves `bulkRegisterStudents` (`:562`) and the
three private helpers (`:1060`, `:1094`, `:1112`).

- Extract `hasScheduleConflict`/`normalizeDays`/`parseTime` into
  `CourseOfferingScheduleConflictDetector` so the day-overlap and
  `startA < endB && startB < endA` logic gets a real unit test instead of only
  end-to-end coverage.
- `AcademicHold` + `AcademicRecord` reads move into
  `GetStudentRegistrationBlockersQuery` (Progression). Note the hold query
  selects an explicit column list (`:691`) that is echoed verbatim into the
  `holds` payload — preserve the column list exactly.
- Inline validate (`:565`) becomes `BulkRegisterSemesterStudentsRequest`.
- `DB::transaction(..., 5)` deadlock-retry, `lockForUpdate()` (`:908`), the
  offering sort weights `1_000_000 / 10_000 / available` (`:875`), and the
  pagination/`next_page` maths (`:1018`) move verbatim.

**Path B (Open Q1 = delete).** Delete the method, its two routes, the
`SemesterRoutes::API_ENROLLMENT_BULK_REGISTER` / `API_ENROLLMENT_REGISTRABLE_STUDENTS`
constants, and the two dead TS helpers. Skip S3's `getRegistrableStudents` move
too (fold that deletion into S5). Saves ~5h and removes ~500 lines of unexercised
locking/prerequisite code.

**Teardown (both paths).**

1. Delete `app/Http/Controllers/Web/SemesterEnrollmentController.php`.
2. Delete `routes/web/semester.php`.
3. `config/migration_debt_paths.php`: remove line 117 and line 136.
4. Lower ratchets in `config/migration_debt.php` **and**
   `MigrationDebtContract::BASELINE_CEILINGS` in lockstep:
   `frozen_controllers 44 → 43`, `frozen_routes 21 → 20`,
   `direct_json_responses 34 → 29`, `inline_request_validation 59 → 50`.
   (`direct_json_responses` and `inline_request_validation` currently sit at
   30/51 with pre-existing slack; lowering the ceiling to the new *current* is
   the established practice from the prior four slices.)
5. `grep -rn "SemesterEnrollmentController\|routes/web/semester" app tests config routes`
   must return nothing.

## Test Scenario Matrix

Characterization tests are written against the **current** controller and must
pass unchanged after the move. Use `RefreshDatabase`, seed permissions, and set
`session(['current_campus_id' => $campus->id])`. Note CSRF is active in feature
tests in this repo — include `_token` on POSTs.

### S1 — `tests/Feature/Academic/SemesterEnrollmentStatsTest.php`

| Scenario | Expected |
|---|---|
| stats, no offerings | `success:true`, `total_offerings:0`, `enrollment_rate:0` |
| stats, offerings with capacity | per-offering `enrollment_rate` rounded to 2dp; overall rate from summed capacity |
| stats, `?unit_id=` / `?lecture_id=` / `?enrollment_status=` filters | each filter narrows the set; combined filters AND together |
| stats without `view_semester` | 403 |
| stats on unknown semester id | 404 (must survive the binding change) |
| `offerings[].instructor_name` | reads `$offering->lecturer?->display_name` while eager-loading `lecture` — record whatever the current code actually emits (likely `null`); do **not** "fix" it, see Open Question 6 |
| open-single-course, happy path | 200, `data.course_offering` with `unit`/`lecture`/`semester` loaded |
| open-single-course, duplicate unit+section | `ApiResponse::error(...)` at HTTP **200** (not 4xx) |
| open-single-course, instructor conflict | `InstructorAssignmentException` → 422 validation envelope keyed by `$exception->field` |
| open-single-course without `edit_semester` | 403 |

### S2 — `tests/Feature/Academic/SemesterEnrollmentPageTest.php`

| Scenario | Expected |
|---|---|
| page render | `assertInertia` component `Semesters/Enrollment`, props `semester`, `enrollmentStats`, `campusStats` |
| campus selected | `enrollmentStats.by_status` / `by_semester_number` count only students of that campus |
| **no campus in session** | `campusStats` is `[]` (empty array, not object) and enrollment stats are unfiltered — this is the current behaviour and a real divergence from the JSON endpoints' 400 |
| campus with 0 eligible students | `enrollment_rate: 0` (guarded division) |
| student on an active `registration` hold | excluded from `not_enrolled_students`, still counted in `total_eligible_students` |
| campus row missing | `campus_name: 'Unknown Campus'`, `campus_code: 'N/A'` |

### S3 — `tests/Feature/Academic/SemesterCourseDemandTest.php`

| Scenario | Expected |
|---|---|
| suggested-courses, no campus in session | **400** `'No campus selected. Please select a campus first.'` |
| suggested-courses, no enrollments | **HTTP 200** with `success:false` and the "generate enrollments first" message (note: 200, not 4xx) |
| suggested-courses, two curriculum versions sharing a unit | `estimated_students` is the **sum**; `curriculum_details` has one row per version |
| suggested-courses ordering | sorted by `estimated_students` desc, re-indexed via `->values()` |
| suggested-courses, existing offerings | `existing_offerings` counts offerings matched by `unit_id` for the current campus |
| registrable-students, no campus | 400 |
| registrable-students, inactive student | skipped via `$student->isActive()` |
| registrable-students, already registered (`registered`/`confirmed`) | course omitted; note `pending` is **not** excluded here although `bulkRegisterStudents` does exclude it |
| registrable-students, offering at capacity | still listed, `has_capacity:false`, excluded from `total_available_registrations` |
| registrable-students, student with zero available courses | omitted from `students[]` entirely |
| `avg_courses_per_student` | rounded to 2dp; `0` when list empty |

### S4 — `tests/Feature/Academic/SemesterEnrollmentGenerationTest.php`

| Scenario | Expected |
|---|---|
| generate, no campus in session | **400** |
| generate, no eligible students | **HTTP 200** with `success:false` — deliberately not a 4xx |
| generate, happy path | one `Enrollment` per eligible student, `status:'in_progress'`, `semester_number:1`, response `enrollments_created` matches |
| generate, student with prior enrollment | `semester_number` = previous max + 1 |
| generate, student already at semester 8 | no row created, `errors[]` contains `"Student {code} has exceeded maximum semester limit"`, `success` still `true` |
| generate, student on active `registration` hold | excluded from eligibility |
| generate, re-run (idempotency) | second call creates 0 rows (`whereDoesntHave('enrollments')`) |
| generate, students of another campus | untouched |
| bulk-open, validation | empty `unit_ids` → 422; non-existent unit id → 422; `default_capacity` 0 or 10001 → 422; bad `delivery_mode` → 422 |
| bulk-open, happy path | `CourseOffering` rows with `max_capacity` default 30, `delivery_mode` default `in_person`, `waitlist_capacity` 10, `enrollment_status` 'open', `notes` 'Unified offering for all curriculum versions' |
| bulk-open, offering already exists for that unit | no new row, `errors[]` gets `"Course offering already exists for unit ID {id}"`, `success` still `true` |
| bulk-open, campus source | created row's `campus_id` comes from `app('campus')->id`, **not** the session key |

### S5 — `tests/Feature/Academic/SemesterBulkRegistrationTest.php` (Path A only)

| Scenario | Expected |
|---|---|
| no campus in session | 400 |
| no enrollments on the requested page | HTTP 200, `success:false`, `data.{page,per_page,total}` |
| happy path | `CourseRegistration` created with `registration_status:'confirmed'`, `credit_hours` from `unit.credit_points` (default 3) |
| inactive student | `results.skipped[]` reason `'Student not active'` |
| student with an active `registration` or `all` hold | `results.failed[]` with `holds` array carrying the exact selected columns |
| enrollment with no curriculum units | `results.skipped[]` reason `'No curriculum units in this semester'` |
| unit already credited (`completion_status:'completed'`) | skipped, reason `'Completed or credit already earned'` |
| unit credited via `credit_hours_earned > 0` only | same skip (the OR branch) |
| prerequisite group AND, all met | registers |
| prerequisite group AND, one missing | `results.failed[]` `'Prerequisites not satisfied'` with `missing_prerequisites[].group_operator` |
| prerequisite group OR, one of two met | registers |
| prerequisite group OR, none met | fails |
| `co_requisite` / `concurrent_prerequisite` satisfied by a unit in the same plan | passes |
| non-unit condition (credits / free_text) | treated as satisfied (`evals[] = true`) |
| empty condition group | skipped, does not block |
| no open offering for the unit | `results.failed[]` `'No available course offering or registration closed'` |
| schedule conflict between two candidate offerings | conflict-free offering selected (sort weight 1,000,000) |
| preferred instructor tiebreak | preferred `lecture_id` wins between two conflict-free offerings |
| capacity exceeded, `force_registration:false` | `results.skipped[]` `'Offering at capacity'` |
| capacity exceeded, `force_registration:true` | registers anyway |
| duplicate registration (re-run) | `results.skipped[]` `'Already registered'`, no second row |
| pagination | `per_page:50` over 120 enrollments → `batch.remaining:70`, `next_page:2`; last page → `next_page:null` |
| validation | `per_page:10` → 422 (min 50); `per_page:501` → 422; bad `registration_method` → 422 |

**Accepted coverage gap: the `DB::transaction(..., 5)` deadlock retry is not
practically testable.** Reproducing a real deadlock requires two concurrent DB
connections racing the same `lockForUpdate()` row inside the test process;
Laravel's `RefreshDatabase` wraps each test in a transaction, which makes a
genuine second-connection deadlock either impossible or non-deterministic. The
retry count moves verbatim and is verified by code review only. Same for
`lockForUpdate()` itself: its presence is asserted structurally (the query is
built in one place), not behaviourally.

**Unit test** `tests/Unit/Academic/CourseOfferingScheduleConflictDetectorTest.php`:
JSON-array `schedule_days`, comma-string `schedule_days`, no day overlap,
touching-but-not-overlapping times (`09:00-10:00` vs `10:00-11:00` → no
conflict), overlapping times, null start/end (→ no conflict), malformed time
string.

## Success Criteria

- [ ] `routes/web/semester.php` and `app/Http/Controllers/Web/SemesterEnrollmentController.php` deleted.
- [ ] `artisan route:list --path=semesters` before/after diff is empty (same URIs,
      names, methods, `can:` middleware).
- [ ] `migration-debt:inventory --check` passes with `shared_model_imports` at
      exactly **494** after every sub-slice, and after S5:
      `frozen_controllers 43`, `frozen_routes 20`, `direct_json_responses 29`,
      `inline_request_validation 50`, with all three config surfaces
      (`config/migration_debt.php`, `config/migration_debt_paths.php`,
      `MigrationDebtContract::BASELINE_CEILINGS`) numerically consistent.
- [ ] All characterization tests written in S1-S5 pass **unmodified** post-move.
- [ ] `resources/js/pages/Semesters/Enrollment.vue` requires **zero** edits
      (except the optional dead-code deletion from Open Question 2).
- [ ] `code-reviewer` subagent reports no CRITICAL/HIGH on each slice diff.

## Risks and Security

| Risk | L x I | Mitigation |
|---|---|---|
| `shared_model_imports` has zero headroom; one stray `use App\Models\X` fails the guard | High x Med | Seam-first: create the owner query **before** moving the method; run the guard at step 6 of every slice, not at the end |
| Dropping route-model binding silently turns 404 into 500 or 200 | Med x High | `FindSemesterForEnrollmentQuery` uses `findOrFail`; unknown-semester 404 is an explicit characterization scenario in S1 |
| `ApiResponse::compatible` drifts from the raw body | Low x High | Characterization tests assert exact JSON keys including the top-level non-`data` keys |
| Two slices editing `routes/web/semester.php` and the old controller concurrently | Med x Med | Serialize the merge; no two sub-slices run as parallel worktrees |
| `bulkRegisterStudents` moved verbatim carries un-exercised locking/prereq code into a module, where it looks blessed | Med x Med | Resolve Open Question 1 before starting S5; if migrating, add the S5 matrix in full plus the unit test for the conflict detector |
| Deleting a reachable endpoint (Path B) breaks an undiscovered API consumer | Low x High | Both portals and the SPA were grepped clean; still requires explicit user sign-off (Open Q1). Rollback is `git revert` of one commit |
| Permission gates lost in the move | Low x High | Unlike the Module slice, **all 8 routes already carry `can:edit_semester` / `can:view_semester`** — copy verbatim and assert middleware in the route-list diff |
| Ownership config edit (Enrollment → Progression) mis-mirrored across the two config surfaces | Med x Med | Edit both files in the same commit; the guard fails loudly if they diverge |

**Rollback:** every sub-slice is one commit that adds module files and removes
the equivalent lines from two legacy files. `git revert <sha>` restores the
legacy route registration and controller method together; no schema, no data
migration, no queue payload, no cached-route dependency. Run
`artisan optimize:clear` after any revert.

## Effort

| Slice | Scope | Effort |
|---|---|---:|
| S1 | stats + single-course pilot, Semester seam, envelope convention | 3h |
| S2 | `show`, Campus + Student seams | 3h |
| S3 | demand reads, CurriculumUnit + Enrollment seams, ownership config | 4h |
| S4 | writes, FormRequest, generation action | 4h |
| S5a | `bulkRegisterStudents` migrate + conflict detector + teardown | 7h |
| S5b | *(alternative)* delete dead endpoints + teardown | 2h |
| **Total** | Path A (migrate everything) | **21h** |
| **Total** | Path B (delete the two dead endpoints) | **~15h** |

## Open Questions

1. **`bulkRegisterStudents` and `getRegistrableStudents` have no live consumer**
   (frontend call commented out at `Enrollment.vue:220`; no reference anywhere
   else including both Nuxt portals). A modern per-offering replacement already
   exists (`BulkRegisterCourseOfferingStudentsAction`). Migrate them verbatim
   (~500 lines of locking/prerequisite code into Delivery, +7h) or **delete**
   the routes, the constants, and the dead TS helpers? Both routes are still
   reachable by an authenticated staff user, so this is your call, not an
   autonomous cleanup.

2. **Commented-out frontend block** at `resources/js/pages/Semesters/Enrollment.vue:215-240`
   (the `bulkRegisterStudents` caller) and the commented-out **enrollment-window
   check** at `SemesterEnrollmentController.php:598-617`. Delete both as dead
   code during this retirement, or leave verbatim because the window check is a
   deliberately-parked feature someone intends to re-enable?

3. **Raw-JSON envelope.** 6 of 8 methods return `response()->json(['success'=>..., 'message'=>..., ...])`.
   Plan default is `ApiResponse::compatible()` — byte-identical payload, zero
   frontend risk, and drops the `direct_json_responses` finding. The alternative
   (`ApiResponse::success/error`, the canonical envelope) would add `timestamp`,
   nest top-level keys under `data`, and require rewriting `Enrollment.vue`'s six
   call sites. Confirm `compatible()` is acceptable here, or should full envelope
   conversion be in scope? Note the plan's own success criterion says "no
   *undocumented* `ApiResponse::compatible()`" — a docblock on each usage
   satisfies it.

4. **Two different campus sources.** `bulkOpenCourses` uses `app('campus')->id`
   (`:381`, `:394`) while every other method uses `session('current_campus_id')`.
   Plan preserves both verbatim (parity-first). Confirm, or should this be
   unified — and if so, which one wins?

5. **`Enrollment` model has no owner** in `MigrationDebtContract::OWNED_SHARED_MODELS`.
   Plan assigns it to `Academic.Progression` (justified by
   `ProgramEnrollmentReader`'s "Progression-owned enrollment projection"
   docblock). Net count change is 0. Confirm Progression is the right owner
   rather than Delivery — `Enrollment` is a student-program-semester record, not
   a course registration, so Progression reads correct, but you own the boundary.

6. **Pre-existing `student_id` key collision.** In `bulkRegisterStudents`,
   **13 separate array literals** write `'student_id' => $student->id` and then
   `'student_id' => $student->student_id` two lines later (`:678-679`, `:696-697`,
   `:720-721`, `:763-764`, `:827-828`, `:857-858`, `:883-884`, `:896-897`,
   `:915-916`, `:935-936`, `:949-950`, `:982-983`, `:994-995`, `:1003-1004`).
   PHP silently keeps the **last** value, so every `results.*[].student_id` is
   the human-readable student code and the numeric PK is lost. Precedent from
   the specialization slice says a discovered live bug gets fixed with the move;
   but here the endpoint has no consumer, so nothing observes the loss. Options:
   (a) preserve verbatim including the collision (pure parity), (b) fix to
   `student_id` + `student_code`, (c) moot if Open Q1 = delete. Which?

7. **`getRegistrationStats.instructor_name` is always `null` — verified.**
   Line `:520` reads `$offering->lecturer?->display_name`, but `CourseOffering`
   defines only `lecture()` (`app/Models/CourseOffering.php:150`); there is no
   `lecturer()` relation. The `->with(['lecture'])` eager load at `:486` is
   therefore wasted and every `offerings[].instructor_name` in the live payload
   is `null`. This is a live (cosmetic) bug on a *consumed* endpoint —
   `Enrollment.vue:200` calls it. Fix to `$offering->lecture?->display_name`
   during the move (specialization-slice precedent for discovered live bugs), or
   preserve the `null` verbatim? Note fixing it changes a rendered value in the
   UI, so it is a visible behaviour change either way.
