---
title: "EGC Carry-Forward Pending Registration Consumption"
description: "Treat same-semester mapped EGC registrations as consumed for carry-forward even while block result stays pending."
status: pending
priority: P1
effort: 2h
branch: dev
tags: [finance, egc, carry-forward]
created: 2026-04-21
blockedBy: []
blocks: []
---

# EGC Carry-Forward Pending Registration Consumption

## Overview

Fix false-positive carry-forward eligibility when a mapped EGC block is still `pending` but the student already has the matching EGC registration in the same semester.

## Phases

| # | Phase | Status | File |
|---|---|---|---|
| 1 | Narrow backend logic + regression coverage | pending | [phase-01](phase-01-narrow-backend-logic-and-regression-coverage.md) |

## Touched Files

- `app/Modules/Finance/Actions/Egc/BuildEgcCarryForwardPlanAction.php`
- `app/Modules/Finance/Queries/Egc/ListEgcCarryForwardCandidatesQuery.php` if semester-scoped EGC registrations are eager-loaded to avoid N+1
- `tests/Feature/Finance/Egc/CarryForwardTest.php`

## Dependencies

- Matching rule must stay aligned with `app/Modules/Finance/Actions/Egc/SyncEgcBlockResultsAction.php`
- No schema or settlement flow changes

## Success Criteria

- Pending block + matching same-semester EGC registration is excluded from `unused_charges`
- Pending block without matching registration remains eligible if its charge is truly unused
- Existing pass/fail consumed behavior stays unchanged

## Rollback

- Revert builder/query changes only; no data rollback needed

## Unresolved Questions

- None
