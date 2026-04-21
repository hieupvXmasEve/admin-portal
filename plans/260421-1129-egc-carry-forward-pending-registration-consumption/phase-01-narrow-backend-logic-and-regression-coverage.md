---
title: "Phase 01 - Narrow backend logic + regression coverage"
description: "Update consumed-charge detection to consider same-semester EGC registrations for pending mapped blocks."
status: pending
priority: P1
effort: 2h
branch: dev
tags: [finance, egc, carry-forward]
created: 2026-04-21
---

# Context Links

- [BuildEgcCarryForwardPlanAction.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Actions/Egc/BuildEgcCarryForwardPlanAction.php)
- [SyncEgcBlockResultsAction.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Actions/Egc/SyncEgcBlockResultsAction.php)
- [ListEgcCarryForwardCandidatesQuery.php](/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Finance/Queries/Egc/ListEgcCarryForwardCandidatesQuery.php)
- [CarryForwardTest.php](/Users/hunt2412/hieupvdev/project/swinx/tests/Feature/Finance/Egc/CarryForwardTest.php)

# Overview

Priority: P1  
Status: pending  
Goal: consume a mapped charge once the student has actually registered for the matching EGC attempt in that same semester, even if academic result sync still reports `pending`.

# Key Insights

- Current carry-forward logic only consumes blocks where `result !== pending`; this is the false-positive source.
- `SyncEgcBlockResultsAction` already defines the matching model: same student + semester, EGC registrations ordered by registration sequence and matched to blocks by block order.
- Scope should stay in read logic. No finance charge mutation, no schema work, no settlement changes.

# Requirements

- Functional:
  1. Treat a pending mapped block as consumed when a matching same-semester EGC registration exists.
  2. Keep a pending mapped block eligible when no matching registration exists.
  3. Preserve existing pass/fail consumption and release calculations.
- Non-functional:
  1. Avoid hidden behavior drift between candidate list and single-student builder calls.
  2. Keep file touch set minimal.

# Architecture

Data flow:
1. Input: student, target semester, active EGC charges, EGC blocks, completed payments.
2. Transform: determine consumed `finance_charge_id`s from:
   - finalized blocks (`pass` / `fail`)
   - pending mapped blocks that have a matched same-semester EGC registration
3. Output: `unused_charges`, `eligible_release_amount`, `status`, `reason`.

Planned change:
- Update `BuildEgcCarryForwardPlanAction` to compute an extra consumed set for pending mapped blocks with matched registrations.
- Prefer using preloaded semester-scoped `courseRegistrations.courseOffering.unit` from `ListEgcCarryForwardCandidatesQuery`; keep a direct-query fallback in the builder only if needed for standalone invocation.
- Reuse `SyncEgcBlockResultsAction` matching order, but do not widen this fix into a shared refactor unless duplication blocks correctness.

# Related Code Files

- Modify:
  - `app/Modules/Finance/Actions/Egc/BuildEgcCarryForwardPlanAction.php`
  - `app/Modules/Finance/Queries/Egc/ListEgcCarryForwardCandidatesQuery.php` only if eager loading is needed
  - `tests/Feature/Finance/Egc/CarryForwardTest.php`
- Read-only reference:
  - `app/Modules/Finance/Actions/Egc/SyncEgcBlockResultsAction.php`
- Create/Delete:
  - None

# Implementation Steps

1. Add a helper in the carry-forward builder that identifies registrations usable for block-consumption matching: same student, same semester, EGC unit type, deterministic order.
2. Merge two consumed buckets before filtering `unused_charges`:
   - finalized mapped blocks
   - pending mapped blocks with matched registrations
3. Keep returned `consumed_blocks` payload behavior stable unless a test proves caller expectations need the pending-consumed block surfaced.
4. Replace the current pending-is-unused regression test with the corrected expectation.
5. Add one positive regression test for a truly unused pending mapped charge with no registration.

# Todo List

- [ ] Lock matching rule to same ordering as result sync
- [ ] Update consumed-charge calculation
- [ ] Cover matching-registration pending case
- [ ] Cover no-registration pending case
- [ ] Run focused carry-forward feature tests

# Success Criteria

- Candidate list no longer shows students eligible just because block 2 result is pending after they already registered.
- Student with an actual unused EGC charge and no matching registration still appears eligible.
- No migration, backfill, or data repair script required.

# Risk Assessment

- High: matching by wrong order could consume the wrong charge when a semester has multiple EGC registrations. Mitigation: mirror `SyncEgcBlockResultsAction` ordering exactly.
- Medium: builder-only DB lookup can introduce N+1 in candidate lists. Mitigation: preload semester-scoped registrations in query if current relations make it cheap.
- Medium: canceled/withdrawn registrations could be counted as consumed incorrectly. Mitigation: confirm whether only active/real registrations should count; if unclear, guard in test and code with current business-accepted statuses.

# Security Considerations

- Read-only query change. No auth, permission, or financial-write path changes.

# Test Matrix

- Unit/logic via feature coverage in `CarryForwardTest.php`:
  - pending mapped block + matching registration => ineligible for release on that charge
  - pending mapped block + no matching registration => eligible if paid/unused
  - existing finalized consumed block case still passes
- Integration:
  - `ListEgcCarryForwardCandidatesQuery` end-to-end grouping remains correct

# Rollback Plan

- Revert builder/query/test changes in one patch; no persisted data affected.

# File Ownership

- Phase owns only:
  - `app/Modules/Finance/Actions/Egc/BuildEgcCarryForwardPlanAction.php`
  - `app/Modules/Finance/Queries/Egc/ListEgcCarryForwardCandidatesQuery.php`
  - `tests/Feature/Finance/Egc/CarryForwardTest.php`

# Next Steps

- After approval, implement Phase 01 and run `./scripts/dev.sh test --filter=CarryForwardTest`.

# Unresolved Questions

- Should `dropped` / `withdrawn` EGC registrations count as consumed, or only active/completed registrations?
