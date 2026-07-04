# PRD: Metropolia Grading Display Completion

Status: ready-for-agent

> Source: `/to-prd` synthesis from a grill-with-docs session continuing story
> `S-007-metropolia-scheme-form-builder` (epic `E-academic-grading-rule-engine`).
> S-001–S-004 and S-007 shipped the grading rule engine, the scheme pack
> (17 schemes across Software 1/2 and Hardware 1/2), the syllabus-template
> form builder, and the portal-facing grade display presenter. This PRD covers
> the remaining display and verification surfaces plus demo/test data.
> Respect ADR 0014: Canvas is the source of truth for component scores;
> post-completion grade changes flow only through Recalculate. Manual score
> editing was explicitly dropped from scope during grilling.

## Problem Statement

Academic staff can now author Metropolia grading schemes on syllabus templates
through a form builder, and the grading engine computes correct scheme-based
final grades at finalization. But staff cannot *see* the scheme results
anywhere in the admin UI:

- The Course Offering Cockpit Scores tab shows only raw component scores,
  weighted percentages, and letter grades — it is blind to the grading scheme.
  A student who scored 90% on the exam but failed a 0%-weight assignment gate
  shows up as "Failed" with no visible reason, which reads as a bug.
- The student academic summary Scores page has the same blindness.
- The student portal display shipped in S-004 has never been verified
  end-to-end because there is no seeded data containing Metropolia schemes,
  finalized breakdowns, or edge-case score profiles.
- There is no seed data at all for grading schemes, so every manual test
  requires hand-authoring templates, offerings, enrollments, scores, and a
  finalization run.

## Solution

Complete the read-side of Metropolia grading:

1. A dedicated, idempotent, manually-run seeder that provisions every scheme
   in the Metropolia catalog (17 schemes + 1 default-weighted control), each
   with a course offering and a cast of students whose scores exercise every
   edge case (clear pass, clear fail, threshold boundaries, gate failure,
   zero, ungraded). Most offerings are finalized through the real
   finalization path so stored grade breakdowns are production-true; a few
   are left unfinalized to exercise the pre-finalize display.
2. The cockpit Scores tab becomes scheme-aware: after finalization it shows
   each student's per-component converted grade, per-component requirement
   status (the gate-failure explainer), the final scheme grade (0–5 or
   pass/fail), and pass status — all read from the stored grade breakdown.
   Before finalization it keeps today's raw-score grid and adds a scheme
   badge in the header plus an explicit empty state ("scheme grades appear
   after finalization"). No live recomputation.
3. The student academic summary Scores page gains the same stored-breakdown
   display through the same presenter.
4. The student portal display (already shipped in S-004) is verified
   end-to-end against the seeded data; code changes only if verification
   exposes a gap.

All display surfaces read through the existing grade display presenter — one
seam, no new calculation paths, no new storage.

## User Stories

1. As an academic admin, I want the cockpit Scores tab to show each student's final scheme grade (0–5 or Pass/Fail) after finalization, so that I can confirm finalization produced the expected outcome without opening the database.
2. As an academic admin, I want the Scores tab to show each component's converted grade next to its raw percentage, so that I can trace how a final grade was assembled.
3. As an academic admin, I want a visible requirement status on components that carry a passing requirement, so that I immediately understand why a high-scoring student failed a gated course.
4. As an academic admin, I want a scheme badge on the Scores tab header (engine + scale), so that I know an offering is graded by a Metropolia scheme before any grades exist.
5. As an academic admin, I want a clear empty state on scheme columns before finalization, so that I don't mistake missing scheme grades for a defect.
6. As an academic admin, I want offerings without a custom scheme to render exactly as they do today, so that the default weighted-percentage flow is undisturbed.
7. As an academic operations staff member, I want the student academic summary Scores page to show the same scheme breakdown per completed course, so that I can answer a student's "why did I fail?" question from one screen.
8. As an academic operations staff member, I want requirement failures visible on the academic summary, so that grade disputes can be triaged without engineering help.
9. As a student, I want my portal grade view to show my final scheme grade and per-component breakdown for Metropolia-graded courses, so that I understand my result.
10. As a student, I want to see which passing requirement I missed when I fail a gated course, so that the failure is explainable and actionable.
11. As a student in a default-weighted course, I want my grade display unchanged, so that the rollout does not disturb existing courses.
12. As a developer, I want a single seeder that provisions every catalog scheme with realistic enrollments and scores, so that I can manually test any grading surface in minutes.
13. As a developer, I want the seeder to cover threshold boundary values exactly (e.g. exactly 40%, exactly 55%, exactly 88%), so that off-by-one conversion bugs are visible in seeded data.
14. As a developer, I want the seeder to include gate-failure archetypes (high weighted score, failed requirement), so that the requirement-status display can be demonstrated.
15. As a developer, I want the seeder to include ungraded and zero-score students, so that null-handling in every display surface is exercised.
16. As a developer, I want most seeded offerings finalized through the real finalization action, so that stored breakdowns are identical in shape to production data.
17. As a developer, I want some seeded offerings left unfinalized, so that pre-finalize display states can be tested.
18. As a developer, I want one seeded default-weighted offering with no scheme, so that regression of the default path is testable side-by-side.
19. As a developer, I want the seeder to be idempotent, so that re-running it does not duplicate templates, offerings, or students.
20. As a developer, I want the seeder excluded from the default database seeding, so that ordinary environments are not polluted with demo data.
21. As a QA engineer, I want the seeder to reuse the canonical scheme catalog rather than redefining schemes, so that seeded data cannot drift from the documented Metropolia rules.
22. As an academic admin, I want the Recalculate flow to remain the only post-completion grade-change path (per ADR 0014), so that displayed scheme grades never contradict finalized results.

## Implementation Decisions

- **Manual score editing is out** — dropped during grilling. Canvas remains
  the source of truth for component scores (ADR 0014). No edit affordance is
  added to any scores surface.
- **Stored-breakdown display only (no live preview).** Scheme grades render
  exclusively from the grade breakdown persisted on academic records at
  finalization/recalculation. Before finalization the UI shows the existing
  raw-score grid, a scheme badge, and an empty state. No on-the-fly
  calculator invocation from display paths.
- **One presentation seam.** All three surfaces (cockpit Scores tab, academic
  summary Scores page, student portal) read through the existing grade
  display presenter introduced in S-004 (`scheme_engine`, `scale`,
  `final_label`, `final_numeric`, `pass_status`, per-component `code`,
  `label`, `raw_percentage`, `converted_grade`, `requirement_status`). The
  presenter contract is not changed; the cockpit scores query and the academic
  summary scores data path are extended to include its output per student.
- **Requirement status is part of the component cell.** Components carrying a
  passing requirement render met / not-met state; components without a
  requirement render nothing extra (null status = no noise).
- **Default path untouched.** Offerings with no custom scheme produce no
  scheme fields (or null), and the existing weighted-percentage display
  renders unchanged.
- **Seeder shape.** One dedicated seeder class, not registered in the default
  seeder chain, run manually. It reuses the Metropolia scheme catalog (all 17
  keys across Software 1, Software 2, Hardware 1, Hardware 2 — including the
  duplicated Maths & Physics and Project variants) plus one default-weighted
  control template. Per template: one course offering, ~8 enrolled students
  with score archetypes derived from that scheme's own thresholds (clear
  pass, clear fail, exact boundary per threshold, gate-fail where the scheme
  has requirements, zero score, ungraded). Most offerings are finalized via
  the real finalization action; a minority left unfinalized. Idempotent by
  stable natural keys.
- **Seeder runs against the dev database.** No `--env=testing` invocation
  (this project has no `.env.testing`; that flag targets the dev database
  with destructive effect).
- **Student portal work is verification, not construction.** S-004 already
  shipped the backend contract and portal components; this PRD only mandates
  an end-to-end pass against seeded data. New portal code only if a gap is
  found, and then as a follow-up issue.
- **Cloud Computing exactness deferred.** The `custom_affine` exact-formula
  engine (grading backlog) stays out; the shipped affine approximation stands.

## Testing Decisions

- Tests assert external behavior at the highest existing seams; no FE
  component-render tests — props-level assertions at the HTTP/Inertia seam
  follow existing repo convention.
- **Seeder**: a feature test runs the seeder and asserts the catalog
  templates/offerings/students exist, finalized offerings carry a grade
  breakdown that passes the real scheme validator, idempotency on double run,
  and the default-weighted control stays scheme-free. Prior art: the scheme
  pack apply command tests.
- **Cockpit Scores tab**: feature tests hit the course offering show endpoint
  and assert the scores props include scheme display fields for finalized
  scheme offerings, null/absent scheme fields for default offerings, and
  pre-finalize shape for unfinalized scheme offerings. Prior art: the
  existing cockpit scores-tab feature tests and the course-statistics
  retirement tests.
- **Academic summary Scores page**: feature tests hit the academic summary
  scores route and assert the same presenter-shaped fields per completed
  course. Prior art: the existing student academic summary scores tests.
- **Student portal**: no new automated seam — the S-004 API contract tests
  already cover the student grade response. Verification is a manual
  end-to-end pass with seeded data.
- **Regression**: default-weighted offerings must produce byte-identical
  scores props to today's output.
- Known tooling constraints apply: whole-project type-check OOMs in the dev
  container (use per-file lint + host/CI); the Academic test directory has a
  known pre-existing red test — run target files/subdirectories explicitly.

## Out of Scope

- Manual score editing anywhere in the admin UI (Canvas is source of truth,
  ADR 0014).
- Live scheme-grade preview from raw scores on any display surface.
- Changes to grading calculators, the scheme JSON contract, the presenter
  contract, or finalization/recalculation behavior.
- New engine types, including `custom_affine` (Cloud Computing exact formula
  remains an approximation pending academic sign-off).
- Canvas sync behavior changes (issues 10/11 of the course-offering cockpit
  feature own that surface).
- Historical recalculation operations (deferred S-005 territory).
- Student portal redesign or new portal components (verification only).
- Registering the seeder in default seeding or running it in CI environments.

## Further Notes

- Supersedes nothing; extends the S-007 line of work. The S-00x story packets
  under the old harness-story architecture are historical context — new work
  tracks here in `.scratch/metropolia-grading-display/`.
- The gate-failure explainer (requirement status per component) is the
  highest-leverage piece of the display work: Metropolia's 0%-weight gated
  components are the single most confusing outcome for staff, and the data
  already exists in the stored breakdown.
- Suggested implementation order: seeder first (01), then cockpit Scores tab
  (02), then academic summary (03), then portal verification (04) — each
  later step consumes the seeded data.
