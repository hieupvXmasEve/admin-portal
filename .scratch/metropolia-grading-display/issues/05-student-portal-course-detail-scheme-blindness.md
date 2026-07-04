# 05 — Student portal course-detail view is scheme-blind (and can show a wrong grade)

Status: ready-for-agent

## Parent

`.scratch/metropolia-grading-display/PRD.md`

Filed from issue 04 verification per its instruction: "If a display gap is
found, do not fix it here — file a new issue."

## What was found

The single-course "Grades" tab a student opens from `/course/[id]` in
`FE/student-nuxt` (`CourseGrades.vue`, backed by
`app/Http/Controllers/Api/V1/Student/CourseRegistrationController@courseDetail`,
route `v1.student.course-registration.course-detail-legacy`, endpoint
`GET /api/v1/student/course/available/{courseOfferingId}`) never receives the
S-004 grade-display presenter output. `CourseRegistrationController::formatGradesTable()`
(lines ~289-333) builds `total_grade` straight from raw `AcademicRecord`
columns and never calls `GradeDisplayPresenter`, and `AssessmentDetail` in
`shared/types/course.ts` has no `grade_display`/`converted_grade`/
`requirement_status` field at all — the presenter's `components` array is
never wired into this endpoint at any level (total or per-component).

This is worse than a missing badge: for a gate-failed Metropolia student, the
endpoint actively renders a **wrong outcome**, because `CourseGrades.vue`
falls back to computing its own AU letter grade / pass-fail from
`final_percentage` whenever `grade_display` is absent.

### Reproduced live (dev DB, Metropolia seed data from issue 01)

Minted a Sanctum token directly for seeded student `M-SW1PROG-GATE`
(`student_id`) and called the real endpoint as the portal would:

- Stored breakdown (ground truth, `academic_records.grade_breakdown`):
  `ASSIGNMENT 30% (gate_met: false, required 40%)`, `EXAM 95% (gate_met: true)`,
  `gates_passed: false`, `final_grade: "0"`, i.e. **failed** despite a strong
  exam score — the exact "high score, gated fail" scenario the PRD calls the
  single most confusing outcome for staff/students.
- `GET /api/v1/student/course/available/189` response `total_grade`:
  ```json
  {
    "final_percentage": "100.00",
    "final_letter_grade": "0",
    "grade_points": "5.00",
    "grade_status": "final",
    "completion_status": "completed"
  }
  ```
  No `grade_display` key. `CourseGrades.vue` (no `grade_display` present) falls
  back to `getLetterGrade(100)` → **"A+"**, `getPassFailStatus(100)` →
  **"PASS"**, GPA badge **5.00** — i.e. a portal viewer of a failed,
  gate-blocked course sees top marks and a PASS badge.
- Reproduced the same shape of failure on a `metropolia_v2` (formula engine)
  scheme, `M-SW2WEB-GATE`, offering 194: stored breakdown says
  `gates_passed: false, final_grade: "0"`; API `total_grade.final_percentage`
  is `"69.00"` with no `grade_display` — same silent-wrong-grade pattern,
  confirming this is systemic across both engines, not one scheme's quirk.
- Confirmed the default-weighted control offering is unaffected (this
  endpoint never had `grade_display` in its shape, so there is no regression
  risk there — see also existing-behavior note below).
- Confirmed unfinalized scheme offerings correctly show `null` grade fields
  on this endpoint (nothing to regress).

### Separate, smaller finding: `GradeController@courseGrades` is dead and broken

`app/Http/Controllers/Api/V1/Student/GradeController.php:87` references
`new CourseGradeResource($courseGrades)`, but
`app/Http/Resources/Api/V1/Student/CourseGradeResource.php` does not exist in
the codebase — this class is missing. The route
(`GET /api/v1/student/grades/course/{courseOfferingId}`,
`v1.student.grades.course-grades`) would fatal with a class-not-found error
if ever called. `FE/student-nuxt` does not call this route (it uses
`course/available/{id}` instead, per `useCourseDetail` in
`app/composables/course.ts`), so it appears to be unreachable dead code
today — flagging it here since it surfaced during the same investigation, not
because it blocks this PRD.

### Also observed (seeder limitation, not a display bug)

The semester/overview grades page (`/grade`, `GET /api/v1/student/grades`,
which does correctly attach `grade_display` via `GradeService::getStudentGrades`
→ `CurriculumUnitCard.vue` renders `final_label`/`scale` there) returns empty
`grades_by_semester` for every Metropolia-seeded student, because
`MetropoliaGradingScenarioSeeder` provisions a bare `CurriculumVersion`
(`MET-SEED-V1`) with no `CurriculumUnit` rows linking the seeded units to it.
`GradeService` reads via `$student->curriculumVersion->curriculumUnits()`, so
this summary surface can't be exercised with the current seed data. This is a
seeder gap (arguably belongs to issue 01, already closed), not a portal-code
gap — noted here for whoever picks this up, since it affects how the fix
below gets manually verified.

### Also observed: seeded students cannot log in through the normal flow

`MetropoliaGradingScenarioSeeder::seedStudent()` creates `Student` rows only
— no linked `User` row. `StudentLoginAction` requires `User::where('email',
...)` then `$user->student`, so none of the seeded Metropolia students
(`M-*`) can complete a real portal login. Verification above was done by
minting Sanctum tokens directly against the seeded `Student` models (which
have `HasApiTokens`) and calling the API as a client would — this is a valid
way to verify API-level behavior but does not exercise the actual login UI
with these students. Whoever fixes issue 05 should decide whether the seeder
also needs a `User` row per seeded student (separate follow-up) or whether
token-minting remains the accepted verification method for this feature.

## Root cause

Two parallel code paths both call themselves "the course detail/grades
view," and only one (`GradeController@index`, semester overview) was wired to
the S-004 presenter. The other (`CourseRegistrationController@courseDetail`,
the actual single-course tab a student uses) was never touched — S-004's own
scope apparently only covered the overview endpoint, and this PRD's issue 04
assumption that "the student portal display... has never been verified
end-to-end" undersold the gap: the single-course display was never wired at
all, not just unverified.

## What to build

- Extend `CourseRegistrationController::formatGradesTable()` (or wherever the
  fix belongs after investigation) to attach the `GradeDisplayPresenter`
  output to `total_grade`, matching the same `scheme_engine`/`scale`/
  `final_label`/`final_numeric`/`pass_status` shape used by the cockpit and
  academic summary surfaces (issues 02/03) and by `GradeController@index`.
- Attach the presenter's per-component `components` array so
  `CourseGrades.vue`'s assessment-group table can show `converted_grade` and
  `requirement_status` next to each raw percentage — extend
  `AssessmentDetail`/`shared/types/course.ts` and the Vue table accordingly
  (portal impact: student — follow `docs/portal-repos.md` workflow, update
  `FE/student-nuxt` in lockstep, run `pnpm lint && pnpm typecheck && pnpm build`
  there).
- Default-weighted offerings must keep rendering exactly as today (no
  `grade_display`/`components`, or null — same regression bar as issues
  02/03).
- Decide and fix (or explicitly defer with a linked follow-up) the dead
  `CourseGradeResource` reference in `GradeController@courseGrades` — at
  minimum confirm it's genuinely unreachable and not silently broken for some
  other untested caller.
- Update `docs/api/student/` per the cross-repo portal harness workflow if
  the response shape changes.

## Acceptance criteria

- [ ] `GET /api/v1/student/course/available/{id}` includes `grade_display`
      (`scheme_engine`, `scale`, `final_label`, `final_numeric`,
      `pass_status`, per-component `components[]`) for finalized scheme
      offerings, sourced from the stored grade breakdown (no live
      recomputation)
- [ ] The gate-fail archetype (`M-SW1PROG-GATE` or equivalent) renders FAIL /
      the correct final label on the student portal course-detail view, not a
      derived PASS/A+ from raw percentage
- [ ] Per-component `converted_grade` and `requirement_status` render on the
      course-detail Grades tab, matching the cockpit/academic-summary
      component-cell behavior (requirement indicator only on gated
      components)
- [ ] Default-weighted offerings render byte-identical to current behavior
      (regression check)
- [ ] Unfinalized scheme offerings keep showing null/absent scheme fields
- [ ] `FE/student-nuxt` types/components updated in lockstep; `pnpm lint`,
      `pnpm typecheck`, `pnpm build` pass in that repo
- [ ] Backend feature test(s) at the HTTP seam asserting the new
      `grade_display` shape on this endpoint for a scheme offering, a
      gate-fail case, a default-weighted offering, and an unfinalized
      offering (prior art: `tests/Feature/Api/V1/Student/GradeBreakdownApiTest.php`)
- [ ] Disposition recorded for the dead `CourseGradeResource` reference
      (fixed, removed, or explicitly deferred with rationale)

## Blocked by

None — the presenter, stored breakdowns, and seeded data (issue 01) already
exist. Can start immediately.

## Comments
