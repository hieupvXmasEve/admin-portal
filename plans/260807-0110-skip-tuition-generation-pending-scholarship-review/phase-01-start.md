---
phase: 1
title: "Shared contracts + IN_FLIGHT_STATUSES constant + implementations"
status: done
priority: P1
effort: "1d"
dependencies: []
---

# Phase 1: Shared contracts + IN_FLIGHT_STATUSES constant + implementations

## Overview

Establish the two cross-module read seams and the status constant every other
phase depends on. Both readers are set-based to keep batch generation and
candidate identification at O(1) queries. No behavior change yet — only new
contracts, one model constant, and their implementations + DI bindings.

## Requirements

- Functional:
  - `ScholarshipAdjustmentDossier::IN_FLIGHT_STATUSES` constant (array) per Decision 1.
  - Reader A (Academic-owned, Finance consumes): "which of these students have an in-flight dossier for target semester".
  - Reader B (Finance-owned, Academic consumes): "which of these students already have an active `tuition_term` charge for target semester".
- Non-functional:
  - Finance imports zero Academic classes; Academic imports zero Finance classes. Both cross only via `App\Shared\Contracts\*` (ADR-0026 boundary).
  - Set-based signatures (accept `int[] $studentIds`, return `array<int,bool>` or filtered `int[]`), no per-student calls.

## Architecture

**Reader A — pending dossier (Academic owns impl):** mirror
`App\Shared\Contracts\Academic\ScholarshipRestorationVerdictReader`.

```php
// app/Shared/Contracts/Academic/PendingScholarshipAdjustmentReader.php
interface PendingScholarshipAdjustmentReader
{
    /**
     * @param int[] $studentIds
     * @return array<int,bool> studentId => hasInFlightDossier(targetSemesterId)
     */
    public function inFlightByStudent(array $studentIds, int $targetSemesterId): array;
}
```

Impl in `app/Modules/Academic/Progression/Queries/` (e.g.
`PendingScholarshipAdjustmentQuery`), single query:
`ScholarshipAdjustmentDossier::whereIn('student_id',$ids)->where('target_semester_id',$sem)->whereIn('status', IN_FLIGHT_STATUSES)->pluck('student_id')` → hydrate the map.

**Reader B — already-charged (Finance owns impl):** neighbors checked —
`StudentFeeSummaryReader::execute()` and `HubStudentFinanceSummaryReader::summary()`
both return whole-student aggregates, NOT per-semester `tuition_term` existence,
so neither answers the question. Add a **new narrow** contract:

```php
// app/Shared/Contracts/Finance/TuitionChargeExistenceReader.php
interface TuitionChargeExistenceReader
{
    /**
     * @param int[] $studentIds
     * @return array<int,bool> studentId => hasActiveTuitionTermCharge(semesterId)
     */
    public function tuitionTermChargedByStudent(array $studentIds, int $semesterId): array;
}
```

Impl in `app/Modules/Finance/Queries/` reading `FinanceCharge` where
`charge_type = TYPE_TUITION_TERM`, `status = STATUS_ACTIVE`, `semester_id = $sem`,
`student_id IN $ids`. Match the exact "already generated" predicate the preview
already uses (`PreviewMajorChargeGenerationQuery` line ~165: active tuition_term
charge for the semester) so candidate-exclusion and preview agree.

**DI bindings:** register both interface→impl in the owning module service
providers (Academic + Finance), same place the existing scholarship contracts
are bound. Grep `ScholarshipRestorationVerdictReader::class` and
`StudentFeeSummaryReader::class` binding sites to copy the pattern.

## Related Code Files

- Create: `app/Shared/Contracts/Academic/PendingScholarshipAdjustmentReader.php`
- Create: `app/Shared/Contracts/Finance/TuitionChargeExistenceReader.php`
- Create: `app/Modules/Academic/Progression/Queries/PendingScholarshipAdjustmentQuery.php`
- Create: `app/Modules/Finance/Queries/TuitionChargeExistenceQuery.php`
- Modify: `app/Modules/Academic/Progression/Models/ScholarshipAdjustmentDossier.php` (add `IN_FLIGHT_STATUSES`)
- Modify: Academic module ServiceProvider (bind Reader A)
- Modify: Finance module ServiceProvider (bind Reader B)

## Implementation Steps

1. Add `IN_FLIGHT_STATUSES` const to `ScholarshipAdjustmentDossier` (reuse existing `STATUS_*` consts; do NOT hardcode strings).
2. Create the two Shared interfaces with set-based signatures + doc comments citing the boundary rule.
3. Implement `PendingScholarshipAdjustmentQuery` (single `whereIn` + `pluck`), returning a full map (every input id present, default `false`).
4. Implement `TuitionChargeExistenceQuery` (single `whereIn` + `pluck`), same map contract; predicate identical to preview's already-charged check.
5. Bind both in the owning module service providers.
6. Unit-test each query: mixed in-flight/terminal statuses; students with/without charges; empty id list returns empty map.

## Success Criteria

- [x] `IN_FLIGHT_STATUSES` contains exactly the 8 non-final statuses; a test asserts terminal statuses are excluded.
- [x] Reader A returns correct map for a mixed batch in ONE query (assert query count).
- [x] Reader B returns correct map for a mixed batch in ONE query; predicate matches `PreviewMajorChargeGenerationQuery` already-charged semantics.
- [x] `app(PendingScholarshipAdjustmentReader::class)` and `app(TuitionChargeExistenceReader::class)` resolve.

## Risk Assessment

- **Risk:** Reader B predicate drifts from preview's "already charged" check → candidate exclusion and preview disagree. **Mitigation:** extract/point both at the same predicate; Phase 5 arch/behavior test asserts agreement.
- **Risk:** map omits input ids with no rows → callers get `null` not `false`. **Mitigation:** contract requires a full map; unit test covers the "no rows" id.
