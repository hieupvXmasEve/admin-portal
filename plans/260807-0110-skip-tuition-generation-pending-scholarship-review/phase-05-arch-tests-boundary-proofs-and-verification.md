---
phase: 5
title: "Arch tests, boundary proofs, and verification"
status: done
priority: P1
effort: "0.5d"
dependencies: [1, 2, 3, 4]
---

# Phase 5: Arch tests, boundary proofs, and verification

## Overview

Lock the module boundary for the two new cross-module seams and run the targeted
suites green. Placement/boundary bugs are otherwise silent — cross-import arch
tests do not cover HTTP-layer location, and a Finance→Academic import would only
surface at runtime.

## Requirements

- Functional:
  - Arch test: Finance imports no `App\Modules\Academic\*`; the only Academic touchpoint is `App\Shared\Contracts\Academic\PendingScholarshipAdjustmentReader` (+ notifier if Option A).
  - Arch test: Academic imports no `App\Modules\Finance\*`; the only Finance touchpoint is `App\Shared\Contracts\Finance\TuitionChargeExistenceReader`.
  - Behavior test: generate-side skip predicate and candidate-side exclusion predicate agree (both via the shared readers).
- Non-functional:
  - Targeted Finance + Academic + Notification suites green; known baseline flakes excluded.

## Architecture

Extend the existing proofs rather than invent new patterns:
- `tests/Feature/Architecture/AcademicFinanceBoundaryArchRedProofTest.php` — add the two new contracts to the allowed-touchpoint allowlist; assert nothing else crosses.
- `tests/Feature/Architecture/ScholarshipAdjustmentModulePlacementArchTest.php` — assert the new Academic query + (Option A) notifier live under `app/Modules/Academic/Progression/`, and the new Finance query under `app/Modules/Finance/Queries/`.

## Related Code Files

- Modify: `tests/Feature/Architecture/AcademicFinanceBoundaryArchRedProofTest.php`
- Modify: `tests/Feature/Architecture/ScholarshipAdjustmentModulePlacementArchTest.php`
- Create: behavior test asserting reader-predicate agreement (generate vs candidate)
- Reference: all phase test files land under `tests/Feature/Finance`, `tests/Feature/Academic` (Progression), `tests/Feature/Notification`.

## Implementation Steps

1. Update the two arch tests for the new allowed touchpoints + placements.
2. Add the predicate-agreement behavior test.
3. Run targeted suites via `./scripts/dev.sh artisan test`:
   - `tests/Feature/Finance` (batch generation + preview)
   - `tests/Feature/Academic` — run Progression subdir/files explicitly (whole Academic dir aborts on a known attendance CHECK-constraint flake, exit 255)
   - `tests/Feature/Notification`
   - `tests/Feature/Architecture`
4. Reconcile any failures against known baselines before calling green.

## Success Criteria

- [x] Both arch tests pass with the new contracts as the ONLY cross-module touchpoints.
- [x] Placement test confirms new files are in their owner modules; no stray global copies.
- [x] Predicate-agreement test green.
- [x] Targeted suites green excluding documented baseline flakes.

## Risk Assessment

- **Known baseline flakes (do NOT chase as regressions):**
  - `EvaluateScholarshipRestorationsCommandTest` — intermittent 1-fail in large combined runs (db_test transient); passes in isolation.
  - Academic attendance `class_sessions` CHECK-constraint failure aborts the whole `tests/Feature/Academic` dir (exit 255) — run Progression files/subdirs explicitly.
- **Risk:** `--env=testing` hits the dev `asia` DB (no `.env.testing`). **Mitigation:** run tests through `./scripts/dev.sh artisan test` (db_test container), never `--env=testing`; reset via `./scripts/reset-local-asia-db.sh --yes` if polluted.
- **Risk:** vue-tsc whole-project type-check OOMs in the dev container. **Mitigation:** per-file eslint / host / CI for any FE label change, not whole-project type-check.
