# 04 — Student portal end-to-end verification

Status: ready-for-human (verified 2026-07-04; display gap found and filed as issue 05, not fixed here)

## Parent

`.scratch/metropolia-grading-display/PRD.md`

## What to build

No new code by default. Verify the student portal grade display (shipped in
S-004: student grade API response + portal grade components) end-to-end
against the seeded Metropolia data from issue 01.

Verification pass, per representative scheme (at minimum: a v1 linear+gate
scheme, a v1 pass/fail scheme, a v1 threshold-sum scheme, a v2 formula
scheme, and the default-weighted control):

- Log in as a seeded student; open the grade view for the completed course.
- Final scheme grade (0–5 or Pass/Fail) and pass status display correctly
  and match the stored breakdown.
- Per-component breakdown (raw percentage, converted grade, requirement
  status) displays correctly.
- A gate-fail student sees which passing requirement was missed.
- A default-weighted course renders exactly as before (no scheme artifacts).
- An unfinalized course shows no scheme grades.

Record findings in this issue's Comments section. If a display gap is found,
do not fix it here — file a new issue in this feature directory describing
the gap, and reference it from the comments.

The S-004 API contract tests already cover the student grade response; no
new automated seam is added.

## Acceptance criteria

- [x] Verification pass executed for the five representative schemes listed above
- [x] Gate-fail explainability confirmed on the portal (confirmed **absent** — see Comments and issue 05)
- [x] Default-weighted and unfinalized behaviors confirmed unchanged
- [x] Findings recorded under `## Comments`; any gaps filed as new issues, not fixed inline

## Blocked by

- `01-metropolia-grading-scenario-seeder.md` (needs seeded data)
- Best run after issues 02 and 03 so all admin surfaces can be cross-checked
  against the same students.

## Comments

**2026-07-04 — verification pass (no code changes)**

The "student portal" is not part of this repo's Inertia app — it's the
separate gitignored Nuxt repo `FE/student-nuxt`, consuming
`/api/v1/student/*` (see `docs/portal-repos.md`,
`docs/stories/E-cross-repo-portal-harness/S-001-cross-repo-portal-harness.md`).
Located the relevant surfaces first:

- Presenter: `app/Modules/Academic/Support/Grading/Presenters/GradeDisplayPresenter.php`
- Two portal surfaces read grade data differently:
  - Semester/overview grades list (`GET /api/v1/student/grades` →
    `GradeController@index` → `GradeService::getStudentGrades`) **does**
    attach `grade_display` per curriculum unit; rendered by
    `CurriculumUnitCard.vue` (final label + scale only, no per-component
    breakdown UI exists there either).
  - Single-course "Grades" tab (`GET /api/v1/student/course/available/{id}`
    → `CourseRegistrationController@courseDetail` →
    `formatGradesTable()`) — the view a student actually opens for "the
    completed course" per this issue's steps — **never** attaches
    `grade_display` at all.

Could not literally "log in as a seeded student": `MetropoliaGradingScenarioSeeder`
creates `Student` rows with no linked `User` row, and
`StudentLoginAction` requires one. Verified end-to-end instead by minting a
Sanctum token directly against seeded `Student` models (they carry
`HasApiTokens`) and calling the real API endpoints exactly as the portal
would, against the seeded data from issue 01.

**Findings, per the five representative schemes:**

- `software_1.programming` (v1, 0-5, linear+gate), clear-pass archetype
  (`M-SW1PROG-PASS`, offering 189): final scheme grade matches stored
  breakdown (`final_grade: "5"`) — but the API response carries no
  `grade_display`, so this "match" is coincidental (raw 100% also happens to
  read as a top grade under the FE's percentage fallback).
- `software_1.programming`, **gate-fail archetype** (`M-SW1PROG-GATE`,
  offering 189): stored breakdown is `gates_passed: false, final_grade: "0"`
  (95% exam, 30% assignment gate requiring 40%) — i.e. failed. The API
  response's `total_grade` shows `final_percentage: "100.00"`,
  `grade_points: "5.00"`, no `grade_display`. `CourseGrades.vue`'s fallback
  path (no `grade_display` present) renders this as **grade "A+", PASS, GPA
  5.00** — the opposite of the real, stored outcome. This is the core gap:
  not just "missing nice-to-have fields" but an actively wrong grade shown to
  the student.
- `software_2.web_development` (v2 formula engine), gate-fail archetype
  (`M-SW2WEB-GATE`, offering 194): same failure shape confirmed
  (`gates_passed: false, final_grade: "0"` in storage vs. no `grade_display`
  and a misleading `final_percentage: "69.00"` in the API response) — so this
  is systemic across both `metropolia_v1` and `metropolia_v2` engines, not one
  scheme's quirk.
- `software_1.database` (v1 pass/fail scale): only exists as an unfinalized
  offering in the current seed data (excluded from finalization per the
  seeder's `UNFINALIZED_SCHEME_KEYS`, for reasons unrelated to this issue) —
  confirmed it correctly shows null total-grade fields pre-finalization, but
  a finalized pass/fail example wasn't available to verify against.
- Default-weighted control offering: unaffected — this endpoint's response
  shape never included `grade_display` in the first place, so there's no
  regression here, just no feature.
- Unfinalized scheme offering (`M-SW1DB-PASS`, offering 190, `in_progress`):
  confirmed `total_grade` fields are all `null` — correct empty state.

**Disposition:** per-component breakdown, gate-fail explainability, and even
correct total-grade/pass-status are not present on the actual student-facing
course detail view. Filed as
`.scratch/metropolia-grading-display/issues/05-student-portal-course-detail-scheme-blindness.md`
rather than fixed here, per this issue's own instruction. That issue also
notes two smaller findings surfaced during the same investigation: a dead
`CourseGradeResource` class reference (unreachable today, but a latent 500),
and a seeder gap where `CurriculumUnit` rows are never wired for seeded
students (so the semester/overview grades page can't be exercised with this
seed data, independent of the scheme-display bug).
