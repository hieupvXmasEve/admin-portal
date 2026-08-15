---
phase: 5
title: "Correction campaign for the diverged GPA rows"
status: todo
priority: P1
effort: "2h + registrar time"
dependencies: [1, 2, 4, 6]
---

# Phase 5: Correction campaign for the diverged GPA rows

## Overview

Operational phase, not a code phase. Once phases 1, 2 and 4 have shipped, correct the measured backlog: 73 diverged (student, semester) pairs across 66 students on dev, plus the 5 pairs that have no GPA row at all. User decision (2026-08-14): correct everything immediately after ship, rather than deferring to a separate registrar decision.

**This lowers 62 students' published GPA**, by a median of 0.85 and up to 4.85 points. No student's `academic_standing` changes — the audit reports zero `academic_standing` divergences, so nobody crosses the 50.0 threshold in either direction. User decision: **no student notification required** — publishing the correct number is sufficient.

## Requirements

- Functional: every `GPA-001` exception reported by `academic:audit-progression-reconciliation` is resolved, per campus and semester, using the ordinary Finalize page (no ad-hoc SQL, no bypass).
- Functional: the 5 never-finalized pairs are created by the same runs.
- Functional: a before/after record exists for every corrected row — Phase 1's activity entry provides this; capture the audit output before and after as the campaign artifact.
- Constraint: run correction only after Phase 4 ships, otherwise the next nightly sync can re-diverge rows already corrected, making the before/after record misleading.
- Constraint: run correction only after Phase 6 ships. This phase recomputes `semester_credit_points_earned` / `cumulative_credit_points_earned` from the transcript, and until Phase 6 lands nine transcript rows still award credits for failed units — those values would be written straight into `gpa_calculations`.
- Non-goals: no direct database mutation; no correction of the 213 `student_hub_consumer_evidence_differs_from_transcript` exceptions or the SUMMER2026 rows (see plan.md Open Questions).

## The 5 never-finalized pairs

All four affected students are `status = deferred` and all have complete, final, credit-bearing records — they should have been finalized. Cause identified: the finalize action used to filter by current student status, which was removed only on 2026-08-13 in commit `5279ab348` ("fix(academic): finalize GPA by semester record, not current student status"). The 2026-05-12 run predates that fix, so deferred students were skipped.

| Semester | Student id | Code | Campus |
|---|---|---|---|
| FALL2025 | 461 | AUH15077 | 1 |
| FALL2025 | 590 | AUH15090 | 1 |
| FALL2025 | 615 | AUH115952 | 1 |
| SPRING2026 | 615 | AUH115952 | 1 |
| SPRING2026 | 617 | AUS115249 | 2 |

No new code needed: with the status filter already gone, a normal Finalize run for those campus+semester combinations creates their rows. They are picked up by the same campaign runs.

## Related Code Files

None — this phase changes data through existing UI, not source. Artifacts belong in `plans/reports/`.

## Implementation Steps

1. Capture the baseline: `./scripts/dev.sh artisan academic:audit-progression-reconciliation --all --format=json`, saved as the pre-campaign artifact.
2. Enumerate the (campus, semester) combinations to run, from the diverged keys. Known distribution: 61 of 68 GPA divergences in SPRING2026, 7 in FALL2025; campuses 1 and 2 both affected.
3. For each combination: open the Finalize page, confirm the Phase 2 divergence badge matches the audit's expectation for that scope, then Finalize.
4. Re-run the audit and diff against the baseline. Expect `GPA-001` and `missing_persisted_calculation` counts for closed semesters to reach zero; SUMMER2026 rows remain absent by design (semester in progress).
5. Record the before/after summary in `plans/reports/`, including the per-student delta list, so the change is answerable if a student disputes it later. No notification is sent (user decision).

## Todo

- [ ] Pre-campaign audit artifact captured
- [ ] Phase 4 confirmed shipped (otherwise stop — corrections can re-diverge overnight)
- [ ] Phase 6 confirmed shipped (otherwise stop — nine wrong earned-credit values would be written in)
- [ ] All (campus, semester) combinations re-finalized
- [ ] Post-campaign audit diff shows zero GPA-001 for closed semesters
- [ ] 5 never-finalized pairs now have rows
- [ ] Report written to `plans/reports/`

## Success Criteria

- [ ] `academic:audit-progression-reconciliation --all` reports zero `GPA-001` exceptions for FALL2025 and SPRING2026
- [ ] AUS19927 SPRING2026 reads 80.737 / 81.293
- [ ] Every corrected row has an activity entry carrying its prior GPA

## Risk Assessment

- Correcting before Phase 4 ships means the nightly sync can move grades again between the correction and the audit, producing rows that look corrected but are not. Mitigated by the ordering dependency above.
- 62 students' GPA drops and the change is externally visible if those numbers were already shown in the portal. Accepted by decision; the per-student delta list from step 5 is what makes it answerable if disputed.
- Measurements are from dev only, by decision. If this is ever run against production, re-measure with the same audit command first rather than assuming dev numbers transfer.
