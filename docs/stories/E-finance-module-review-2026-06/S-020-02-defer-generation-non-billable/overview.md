# FIN-REV-020-02 - Defer-Aware Charge Generation (M2)

## Status

done (2026-06-21, TDD)

Implemented: charge generation/preview now key non-billability off
`registration_status = 'defer'` via `DeferChargeResolver::isSemesterEnrollmentDeferred`
(PRESERVE and FORFEIT alike), so an M1-voided obligation is never resurrected.
The old PRESERVE-skip branch (`findApplicableFullCase` / `markFullCaseApplied` /
`DeferChargePolicy::shouldSkipFullSemester` + its 3 unit tests) was removed; the
COURSES-scope per-item path is untouched. Touched: `GenerateBatchChargesAction`,
`PreviewChargeGenerationQuery`, `PreviewMajorChargeGenerationQuery` (Fee-Monitor
tuition). Tests: `GenerateChargeTransitionSameSemesterTest` (+2) and
`FeeMonitorViewTest` (+1), green; `finance:audit-invariants` at pre-existing
baseline (INV-6=28/INV-13=1, unchanged — no money mutated).

## Lane

high-risk

## Parent

`FIN-REV-020-defer-finance-settlement` (money slice / Phase 6). Child M2.

## Depends On

- `FIN-REV-020-01` (settlement action) — the void must not be undone by generation.

## Current Behavior

`GenerateBatchChargesAction` + `DeferChargeResolver`/`DeferChargePolicy` implement
"PRESERVE = skip future charge": charges are skipped only when a matching
`scope=FULL, policy=PRESERVE` defer case applies and is not yet applied. FORFEIT
defers do NOT skip, so generation can re-create a charge that M1 just voided.
`PreviewChargeGenerationQuery` and the Fee-Monitor tuition path also rely on this
defer-case skip rather than on `registration_status = 'defer'`.

## Target Behavior

Charge generation and its preview treat deferred enrollment as non-billable
directly:

- A registration with `registration_status = 'defer'` is excluded from
  generation/preview.
- A deferred student's deferred semester does not get its voided obligation
  re-created — regardless of fee policy (PRESERVE or FORFEIT).
- Remove/narrow the "preserve = skip future charge" branch so it no longer
  governs future re-enrollment (re-enrollment billing is M4).
- `PreviewChargeGenerationQuery` and Fee-Monitor expected/missing tuition rows
  reflect defer non-billability (the part deliberately deferred from slice-1
  Phase 3 because it was defer-case-entangled).

## Acceptance Criteria

- A batch generation run for a deferred student in the deferred semester creates
  NO charge for the deferred scope (PRESERVE and FORFEIT both), i.e. it never
  resurrects an obligation voided by M1.
- Regression tests added BEFORE removing the preserve-skip branch.
- Preview + Fee-Monitor tuition expected-fee no longer flag a deferred
  registration as missing/expected.
- `finance:audit-invariants` clean; existing generation tests still green.

## Non-Goals

- No re-enrollment charge/allocation behavior (that is M4).
- No money mutation here (this is generation/preview gating only).

## Test Plan

| Layer | Cases |
| --- | --- |
| Feature | Batch generation for deferred student + voided obligation → no re-created charge (PRESERVE). |
| Feature | Same for FORFEIT defer (previously not skipped). |
| Feature | `PreviewChargeGenerationQuery` excludes `registration_status='defer'`. |
| Unit/Feature | Removing preserve-skip does not change non-defer generation (regression guard). |
| Platform | `finance:audit-invariants`; Pint; `git diff --check`. |

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/GenerateChargeTransitionSameSemesterTest.php
./scripts/dev.sh test tests/Feature/Finance/Reporting/FeeMonitorViewTest.php
./scripts/dev.sh artisan finance:audit-invariants
```
