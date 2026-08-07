---
phase: 3
title: "Candidate-side exclusion when tuition already generated"
status: done
priority: P1
effort: "0.5d"
dependencies: [1]
---

# Phase 3: Candidate-side exclusion when tuition already generated

## Overview

Close the other half of the invariant: once a `tuition_term` charge exists for
the target semester, the student can no longer be opened for a scholarship
adjustment. Enforce it in candidate identification using Reader B (Finance-owned),
so Academic never joins Finance tables directly.

## Requirements

- Functional:
  - `ScholarshipAdjustmentCandidateQuery::handle()` excludes any student who already has an active `tuition_term` charge for `targetSemesterId`.
  - Exclusion count is surfaced alongside the existing `excluded_null_is_passed` so staff see why a failed student is absent.
- Non-functional:
  - Reader B queried ONCE for the candidate set (set-based), not per student.
  - Academic imports only `App\Shared\Contracts\Finance\TuitionChargeExistenceReader`, never a Finance class.

## Architecture

In `app/Modules/Academic/Progression/Queries/ScholarshipAdjustmentCandidateQuery.php`,
after the existing `$continuingStudentIds` filter and before grouping, apply the
exclusion:

```php
$alreadyCharged = app(TuitionChargeExistenceReader::class)
    ->tuitionTermChargedByStudent($continuingStudentIds->all(), $targetSemesterId);

$excludedAlreadyCharged = $continuingStudentIds
    ->filter(fn (int $id) => $alreadyCharged[$id] ?? false)->count();

$eligibleFailures = $eligibleFailures->reject(
    fn (AcademicRecord $r) => $alreadyCharged[$r->student_id] ?? false
);
```

Return an extra key `excluded_already_charged` in the result array (additive;
existing `candidates` + `excluded_null_is_passed` keys unchanged).

Note: this makes `ScholarshipAdjustmentTimingGuard`'s "invoice already paid /
open" branch largely unreachable via the normal flow, but it is **kept** as the
post-approval safety net (a dossier approved just as a charge lands still routes
to `finance_review_required` instead of corrupting a paid invoice).

## Related Code Files

- Modify: `app/Modules/Academic/Progression/Queries/ScholarshipAdjustmentCandidateQuery.php`
- Reference (no change): callers of `handle()` — verify they tolerate the new result key (`IdentifyCandidatesAction`, controller, FE candidates preview). Add the count to any staff-facing summary that already shows `excluded_null_is_passed`.

## Implementation Steps

1. Inject/resolve Reader B; preload the map from `$continuingStudentIds`.
2. Reject already-charged students from `$eligibleFailures`; compute `excluded_already_charged`.
3. Add the new key to the return array; thread it into the candidates-preview response + FE label if that surface shows exclusion counts.
4. Test: failed student WITH an active tuition_term charge for target semester → absent from candidates, counted; WITHOUT → present.

## Success Criteria

- [x] Candidate with an existing active `tuition_term` charge for target semester is excluded and counted.
- [x] Reader B hit exactly once per candidate run.
- [x] Existing candidate predicates (fail/appeal/award/continuation) unchanged for non-charged students.
- [x] No Finance class imported into Academic (boundary test, Phase 5).

## Risk Assessment

- **Risk:** exclusion predicate differs from Phase 2's generate/preview predicate → a student blocked from candidacy but still generated, or vice-versa. **Mitigation:** both go through Reader B (single source); Phase 5 asserts agreement.
- **Risk:** result-shape consumers break on the new key. **Mitigation:** additive key; grep every `handle()` caller in step 3.
- **Risk:** cancelled/void tuition charges counted as "charged". **Mitigation:** Reader B filters `status = STATUS_ACTIVE` only (same as preview's already-charged check).
