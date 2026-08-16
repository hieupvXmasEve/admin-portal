---
phase: 5
title: "Phase 5: Migrate Academic progression and portals"
status: pending
priority: P0
effort: XL
dependencies: [3, 4]
---

# Phase 5: Migrate Academic progression and portals

<!-- Rescoped 2026-08-16 from measured inventory; supersedes the 2026-07-26 draft. -->

## Overview

Close the debt that now sits inside `Academic/Progression`, and cut the Student
and Lecturer APIs onto canonical owners. Compatibility data stays until phase 10
proves it can be retired.

The 2026-07-26 draft of this phase described progression, transcript, GPA,
standing, graduation, and decisions as the remaining work. Phase 4 moved most of
that into Progression already, and a scholarship-adjustment subsystem shipped in
August that did not exist when the draft was written. The measured remainder
below replaces the draft's scope.

## Measured scope (2026-08-16)

117 findings: 85 `shared_model_imports`, 14 `frozen_services`, 8 `frozen_controllers`,
6 `inline_request_validation`, 2 `direct_json_responses`, 2 `frozen_routes`.

The 93 module-internal findings all sit inside `app/Modules/Academic/Progression`.
The other 24 are the Student and Lecturer API shells described below.

| Cluster | Findings | Note |
|---|---:|---|
| `Progression/Http/Web` | 15 | Web controllers for lifecycle, decisions, audit |
| `Progression/Queries/Reporting` | 13 | Report projections |
| `Progression/Actions/ScholarshipAdjustment` | 7 | Shipped 2026-08-01, not in the original draft |
| `Progression/Models/ScholarshipAdjustmentDossier.php` | 5 | Same subsystem |
| `Progression/Queries/ScholarshipAdjustmentCandidateQuery.php` | 4 | Same subsystem |
| `Progression/Actions/Placement` | 4 | |
| `Progression/Models/ProgramEnrollment.php` | 4 | |
| `Progression/Support/*` | 11 | Lifecycle factories, timelines, Excel resolver |
| Remaining actions and queries | 30 | Warnings, EGC results, lifecycle timeline, restoration verdict |

Those 24 are live business workflows rather than dead residue: the eight frozen
`app/Http/Controllers/Api/V1/Student/*` controllers, the fourteen
`app/Services/V1/Student/*` frozen services, and the frozen
`routes/api/v1/student.php` and `routes/api/v1/lecturer.php`. The scanner used to
tag them to phase 9, which explicitly refuses business cutovers, so nobody owned
the cutover. `MigrationDebtInventory::frozenShellPhase()` was corrected on
2026-08-16 and now routes them here.

## Requirements

- [x] Correct the scanner owner map so Student/Lecturer API controllers, services,
  and route files tag to phase 5 rather than phase 9. Done 2026-08-16.
- [ ] Remove the 85 shared-model imports inside `Academic/Progression`.
- [ ] Retire the 24 Student and Lecturer API shells now tagged to this phase.
- [ ] Replace the 6 inline validations and 2 raw JSON responses in Progression HTTP.
- [ ] Bring the scholarship-adjustment subsystem under the same owner contracts as the
  rest of Progression; it was built after the boundary rules were set and has not been
  held to them.
- [ ] Complete Student API issue 16 and revalidate Lecturer API issue 17.
- [ ] Cut supported readers to canonical contracts while retaining the compatibility
  projection and dual-publish/sync writer until phase 10 evidence and removal approval.
- [ ] Preserve exact snake_case contracts, actor middleware, permissions, and portal behavior.
- [ ] Keep the approved override-pass transcript case as an explicit disposition.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Progression/Http/Web` | Replace inline validation, raw JSON, and model reads |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Progression/Queries/Reporting` | Move report projections onto owner readers |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Progression/Actions/ScholarshipAdjustment` | Bring the August subsystem onto owner contracts |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Progression/Models` | Resolve dossier and program-enrollment cross-model reads |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Progression/Support` | Reshape the Excel reference resolver that returns Eloquent models |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers/Api/V1/Student` | Migrate eight frozen Student API controllers |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services/V1/Student` | Retire fourteen frozen Student API services |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/api/v1/student.php` | Remove legacy Student API controllers |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/api/v1/lecturer.php` | Revalidate and retire the lecturer split file |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/console.php` | Retain and monitor compatibility schedules until phase 10 approval |
| `/Users/hunt2412/hieupvdev/project/swinx/FE/student-nuxt` | Align student contract and UI |
| `/Users/hunt2412/hieupvdev/project/swinx/FE/lecturer-nuxt` | Revalidate lecturer contract and UI |
| `/Users/hunt2412/hieupvdev/project/swinx/docs/api` | Update canonical portal contracts |

## Interface Checklist

- Transcript and standing decisions use canonical progression facts.
- Student and Lecturer endpoints retain URL, status, envelope, snake_case, and campus scope.
- Portal types, composables, stores, and pages change in their own Git repositories.
- Override-pass evidence is represented explicitly, not forced into default grade calculation.
- `StudentActionExcelReferenceResolver` stops returning Eloquent models to its row mapper;
  contract-ising it reshapes the consumer, so it is a slice, not a rename.

## Dependency Map

`Catalog/Delivery results → Progression → APIs → portals → compatibility consumer zero`

## Implementation Steps

1. Use phase 1's reconciled consumer manifest; recheck exact paths before work.
2. Characterize transcript, best-attempt, GPA, standing, graduation, decisions, and exports.
3. Close the scholarship-adjustment cluster first: it is the newest code, the least
   constrained by the boundary rules, and the most likely to grow further if left.
4. Make canonical progression the primary write and read path; retain the legacy projection
   publisher and sync as a monitored compatibility mechanism until phase 10 approves removal.
5. Migrate the Student API services and controllers, then update the student portal.
6. Re-run Lecturer API contract and portal coverage after shared interface changes.
7. Move AI, Finance, and report consumers only to stable progression contracts or projections.
8. Remove replaced API shells and lower the affected ratchets; retain projection writers,
   commands, and data columns until phase 10 reconciliation and separate removal approval.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Normal and override-pass attempt | Canonical transcript honors approved disposition |
| Repeated result commit | No duplicate transcript or progression event |
| Scholarship adjustment dossier lifecycle | Confirmation gate and carry-forward behavior unchanged |
| Guardian proxy, student, and lecturer actor | Correct scope and authorization |
| Student and Lecturer portal build | Contract types and routes compile |
| Scheduled compatibility publishing | Idempotent, monitored, removable only after approval |
| Consumer inventory | Supported compatibility consumers reach zero |

## Success Criteria

- [ ] `Academic/Progression` has zero shared-model imports, inline validation, and raw JSON.
- [ ] Student and Lecturer APIs have no legacy controller, service, or model dependency.
- [ ] Both portal repos pass affected lint, typecheck, build, and contract tests.
- [ ] Supported reader consumer count is zero; compatibility publishing remains intact
  until phase 10 confirms retirement eligibility.

## Risks and Security

- Academic outcomes are auditable records. Preserve source evidence, decision actors,
  timestamps, overrides, and deterministic recalculation across retries.
- Scholarship adjustments move money downstream. Treat the Finance handoff as a
  contract boundary, not an internal call, and keep the confirmation gate intact.
