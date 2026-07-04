# 01 — Metropolia grading scenario seeder

Status: done

## Parent

`.scratch/metropolia-grading-display/PRD.md`

## What to build

A dedicated, manually-run, idempotent seeder that provisions demo/test data
for every Metropolia grading scheme so any grading display surface can be
exercised in minutes.

- Reuse the canonical Metropolia scheme catalog (all 17 keys across
  Software 1, Software 2, Hardware 1, Hardware 2 — including the duplicated
  Maths & Physics and Project variants). Do not redefine scheme JSON.
- Plus one default-weighted control template with no custom scheme.
- Per template: one syllabus template with assessment components carrying
  correct component codes, one course offering, ~8 enrolled students whose
  component scores are archetypes derived from that scheme's own thresholds:
  clear pass, clear fail, exact boundary per threshold (e.g. exactly 40%,
  55%, 70%, 85%, 88% where the scheme uses them), gate-fail (high weighted
  score but a failed passing requirement) where the scheme has requirements,
  zero score, and ungraded.
- Finalize the majority of offerings through the real finalization action so
  academic records carry production-true grade breakdowns. Leave a minority
  of scheme offerings unfinalized to exercise pre-finalize display states.
- Idempotent via stable natural keys — re-running must not duplicate
  templates, offerings, students, or academic records.
- Not registered in the default seeder chain; run manually with
  `db:seed --class=...`.
- Runs against the dev database. Never invoke with `--env=testing` (no
  `.env.testing` exists; that flag would target the dev database
  destructively).

## Acceptance criteria

- [x] Seeder provisions 17 catalog scheme templates + 1 default-weighted control, each with offering and enrolled students
- [x] Score archetypes cover: clear pass, clear fail, exact threshold boundaries, gate-fail (where scheme has requirements), zero, ungraded
- [x] Majority of scheme offerings finalized via the real finalization action; finalized academic records carry a grade breakdown that passes the real scheme validator
- [x] At least two scheme offerings left unfinalized
- [x] Default-weighted control offering has no grading scheme and finalizes through the default weighted path
- [x] Running the seeder twice produces no duplicate rows
- [x] Seeder is not part of the default `DatabaseSeeder` chain
- [x] Feature test proves provisioning, breakdown validity, idempotency, and the scheme-free control (prior art: scheme pack apply command tests)

## Blocked by

None — can start immediately.
