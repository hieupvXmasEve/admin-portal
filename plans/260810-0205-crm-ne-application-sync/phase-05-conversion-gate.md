---
phase: 5
title: "Conversion readiness gate"
status: completed
priority: P1
effort: "1d"
dependencies: [1, 4]
---

# Phase 5: Conversion readiness gate

## Overview

An application cannot become a Student until campus, program and intake resolve. The block must name the
exact missing mapping, the UI must show the same state the guard enforces, and a junk CRM record must
still be rejectable.

## Requirements

- Functional: one readiness query drives the approve guard and the list/detail badge.
- Non-functional: the error names the missing mapping and the raw CRM value, not a generic FK or policy failure; readiness must be reachable — a 403 from the policy would hide it.

## Architecture

`GetApplicationConversionReadinessQuery` returns
`{ ready: bool, missing: [{ field, crm_value, kind }], warnings: [...] }`.

Required: `campus_code`, `intended_program`, `intake`, then a unique curriculum version.
Not required: `scholarship`, `pathway_gateway`, `uu_dai_gc` → warnings only.

**Short-circuit order matters.** `curriculum_match_count` is derived from the *resolved* program/intake
([EloquentAdmissionsIntentReader.php:27](../../app/Modules/Academic/Catalog/Support/EloquentAdmissionsIntentReader.php)),
so a null program yields 0 matches — indistinguishable from a genuinely missing curriculum version. The
query must resolve campus/program/intake first and only consult the curriculum count once all three are
non-null. Otherwise a freshly synced application reports four blockers where one is real, and staff are
told to create a curriculum version they already have.

**Reachability.** `ApproveApplicationAction` is behind `can:approve,studentApplication`, and the policy
returns false when `$application->campus?->id` is null — which is every unmapped CRM record. Without the
Phase 1 policy change, the friendly message is dead code because the request 403s in middleware. Phase 1
already restores reject/revoke for null-campus records; this phase makes the *approve* denial informative
by surfacing readiness in the controller payload, so the UI explains the block before the user clicks.

`ApproveApplicationAction` gains the readiness guard ahead of its existing resolution
([ApproveApplicationAction.php:115](../../app/Modules/Admissions/Actions/ApproveApplicationAction.php)),
so `Chưa map campus CRM "TP. Hồ Chí Minh"` wins over `Check the intake code`.

`ponytail: one readiness query, two consumers (guard + UI). Duplicating the rule in Vue is how the badge
and the guard drift apart.`

## Related Code Files

- Create: `app/Modules/Admissions/Queries/GetApplicationConversionReadinessQuery.php`
- Modify: `app/Modules/Admissions/Actions/ApproveApplicationAction.php` (readiness guard before mapping resolution)
- Modify: `app/Modules/Admissions/Http/Web/StudentApplicationController.php` (expose readiness on index + show)
- Modify: `resources/js/pages/StudentApplications/Index.vue`, `Show.vue` (badge + blocking-values list)
- Create: `tests/Feature/Admissions/ApplicationConversionReadinessTest.php`
- Create: `tests/Feature/Architecture/AdmissionsCrmSyncPlacementArchTest.php`

## Implementation Steps

1. Write the readiness query + tests: fully mapped → ready; missing campus mapping → not ready naming the raw CRM value **and no curriculum reason**; missing intake row → not ready; resolved but ambiguous curriculum → not ready with a distinct reason.
2. Insert the guard at the top of `ApproveApplicationAction`, returning the readiness errors verbatim.
3. Expose readiness in the controller payload; render badge + reasons in the two Vue pages. No rule logic in Vue — it renders what the query returned.
4. Arch test (repo pattern: `tests/Feature/Architecture/AdmissionsOwnerContractsArchTest.php`): CRM sync classes, console command, HTTP controller and routes live under `app/Modules/Admissions/`, with no stray copy under global `app/Http` or `app/Console`.
5. Full-flow test: sync an unmapped record → approve blocked with the mapping error → reject still available → save the mapping → approve succeeds and creates the Student with the right campus, program, intake, and curriculum version.

## Success Criteria

- [ ] Approving an unmapped application fails with a message naming the field and the raw CRM value.
- [ ] An unmapped application reports exactly the real blockers — no phantom "no curriculum version exists".
- [ ] An unmapped application can still be rejected/revoked (Phase 1 policy change verified end-to-end here).
- [ ] After mapping, the same application approves and produces a Student with the correct campus, program, intake, and curriculum version.
- [ ] Index and detail badges match the guard's verdict in every tested case.
- [ ] Non-required unmapped values never block approval.
- [ ] Manually created applications (campus/program/intake already set) are unaffected — existing approval tests green.
- [ ] Arch test passes; no admissions CRM file outside the module.

## Risk Assessment

- **Guard/UI drift** (medium): one shared query plus a test asserting badge and guard agree.
- **Blocking previously working approvals** (medium): the guard fires only on genuinely missing values; covered by re-running the existing approval tests.
- **Readiness unreachable behind the policy** (medium): depends on the Phase 1 policy change; step 5 verifies the whole chain rather than the action in isolation.
