---
title: "Phase 5: Migrate Academic progression and portals"
status: todo
priority: P0
effort: XL
dependencies: [3, 4]
---

# Phase 5: Migrate Academic progression and portals

## Overview

Cut progression, transcript, GPA, standing, graduation, decisions, Student Hub,
Student API, and Lecturer API onto canonical owners while retaining compatibility
data until phase 10 proves it can be retired.

## Requirements

- [ ] Complete Student API issue 16 and revalidate Lecturer API issue 17.
- [ ] Cut supported readers to canonical contracts while retaining the compatibility
  projection and dual-publish/sync writer until phase 10 evidence and removal approval.
- [ ] Preserve exact snake_case contracts, actor middleware, permissions, and portal behavior.
- [ ] Keep the approved override-pass transcript case as an explicit disposition.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Progression` | Own transcript, GPA, standing, graduation |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Http/Api` | Complete student/lecturer controllers |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/api/v1/student.php` | Remove legacy Student API controllers |
| `/Users/hunt2412/hieupvdev/project/swinx/routes/console.php` | Retain and monitor compatibility schedules until phase 10 approval |
| `/Users/hunt2412/hieupvdev/project/swinx/FE/student-nuxt` | Align student contract and UI |
| `/Users/hunt2412/hieupvdev/project/swinx/FE/lecturer-nuxt` | Revalidate lecturer contract and UI |
| `/Users/hunt2412/hieupvdev/project/swinx/docs/api` | Update canonical portal contracts |

## Interface Checklist

- Transcript/standing decisions use canonical progression facts.
- Student and Lecturer endpoints retain URL, status, envelope, snake_case, and campus scope.
- Portal types/composables/stores/pages change in their own Git repositories.
- Override-pass evidence is represented explicitly, not forced into default grade calculation.

## Dependency Map

`Catalog/Delivery results → Progression → APIs → portals → compatibility consumer zero`

## Implementation Steps

1. Use phase 1's reconciled 15-versus-18 manifest; recheck exact paths before work.
2. Characterize transcript, best-attempt, GPA, standing, graduation, decisions, and exports.
3. Make canonical progression the primary write/read path; retain the legacy projection
   publisher/sync as a monitored compatibility mechanism until phase 10 approves removal.
4. Migrate remaining Student API services/controllers, then update the student portal.
5. Re-run Lecturer API contract and portal coverage after shared interface changes.
6. Move AI/Finance/report consumers only to stable progression contracts/projections.
7. Remove replaced API shells and shrink inventory; retain projection writers, commands,
   and data columns until phase 10 reconciliation and separate removal approval.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Normal and override-pass attempt | Canonical transcript honors approved disposition |
| Repeated result commit | No duplicate transcript/progression event |
| Guardian proxy/student/lecturer actor | Correct scope and authorization |
| Student/Lecturer portal build | Contract types and routes compile |
| Scheduled compatibility publishing | Idempotent, monitored, and removable only after approval |
| Consumer inventory | Supported compatibility consumers reach zero |

## Success Criteria

- [ ] Student and Lecturer APIs have no legacy controller/service/model dependency.
- [ ] Both portal repos pass affected lint, typecheck, build, and contract tests.
- [ ] Supported reader consumer count is zero; compatibility publishing remains intact
  until phase 10 confirms retirement eligibility.

## Risks and Security

- Academic outcomes are auditable records. Preserve source evidence, decision actors,
  timestamps, overrides, and deterministic recalculation across retries.
