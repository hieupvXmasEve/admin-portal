# 02 — Cockpit Scores tab scheme display

Status: ready-for-human (implemented 2026-07-04; all acceptance criteria met, see CockpitScoresTabSchemeDisplayTest)

## Parent

`.scratch/metropolia-grading-display/PRD.md`

## What to build

Make the Course Offering Cockpit Scores tab scheme-aware, reading exclusively
from the stored grade breakdown (no live recomputation — ADR 0014 territory
stays untouched; Canvas remains the source of truth; no score editing).

End-to-end behavior:

- For a finalized offering with a custom grading scheme, each student row
  additionally shows: per-component converted grade next to the raw
  percentage, per-component requirement status (met / not met) on components
  that carry a passing requirement (components without a requirement show
  nothing extra), the final scheme grade (0–5 numeric or Pass/Fail), and pass
  status. All fields come from the existing grade display presenter
  (`scheme_engine`, `scale`, `final_label`, `final_numeric`, `pass_status`,
  per-component `code`, `label`, `raw_percentage`, `converted_grade`,
  `requirement_status`).
- The tab header shows a scheme badge (engine + scale) whenever the offering's
  syllabus template carries a custom scheme, regardless of finalization state.
- Before finalization, the existing raw-score grid renders unchanged, plus an
  explicit empty state on the scheme columns ("scheme grades appear after
  finalization").
- Offerings without a custom scheme render byte-identically to today — no
  scheme fields in props (or null), no visual change.

The gate-failure explainer is the point: a student with a high weighted score
but a failed 0%-weight requirement must be visibly explained (requirement
"not met" on the gating component, final grade 0/Failed).

## Acceptance criteria

- [x] Finalized scheme offering: scores props include presenter-shaped scheme fields per student; UI renders converted grades, requirement statuses, final scheme grade, pass status
- [x] Gate-fail student renders requirement "not met" on the gating component with failed final grade
- [x] Components without a passing requirement render no requirement indicator
- [x] Scheme badge (engine + scale) visible on the tab header for scheme offerings, pre- and post-finalization
- [x] Unfinalized scheme offering: raw grid unchanged + empty state for scheme grades
- [x] Default-weighted offering: scores props and rendering unchanged (regression assertion)
- [x] No score-editing affordance introduced
- [x] Feature tests at the HTTP/Inertia seam with self-contained fixtures (prior art: existing cockpit scores-tab tests, course-statistics retirement tests)

## Blocked by

None — can start immediately. Tests are self-contained; manual demo uses the
seeder from issue 01.
