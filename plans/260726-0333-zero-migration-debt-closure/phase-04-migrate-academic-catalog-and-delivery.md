---
title: "Phase 4: Migrate Academic catalog and delivery"
status: in-progress
priority: P0
effort: XL
dependencies: [3]
---

# Phase 4: Migrate Academic catalog and delivery

## Overview

Complete Academic Catalog, Calendar, Delivery, attendance, scheduling,
assessment, roster, and Canvas ownership as vertical slices. Remove legacy
controllers/services/routes and frontend debt with each migrated workflow.

## Requirements

- [ ] Assign every generic Academic action/query/support class to one logical context.
- [ ] Eliminate non-owner CourseOffering, Semester, Lecture, curriculum, and result imports.
- [ ] Preserve route, permission, filter, export/import, schedule, and Canvas behavior.
- [ ] Resolve human review gates for repository issues 09 and 10.

## File Inventory

| Path | Action |
|---|---|
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Catalog` | Own units, curriculum, programs, syllabus |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Delivery` | Own offerings, sessions, roster, attendance |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Modules/Academic/Delivery` | Also own assessment/gradebook/result commits per accepted boundary |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Http/Controllers` | Retire migrated Academic shells |
| `/Users/hunt2412/hieupvdev/project/swinx/app/Services` | Retire migrated Academic/Canvas services |
| `/Users/hunt2412/hieupvdev/project/swinx/resources/js/pages/Academic` | Canonical pages, filters, named routes |
| `/Users/hunt2412/hieupvdev/project/swinx/routes` | Remove superseded split routes |

## Interface Checklist

- Cross-context calls use Academic-internal contracts/events or Shared contracts.
- Controllers use FormRequests and Actions/Queries; API payloads use Resources/ApiResponse.
- Filtered pages accept whitelisted filters/sorts and echo sanitized state.
- Canvas sync is idempotent and retries without duplicate enrollments/content.

## Dependency Map

`reference foundations + relevant page batch → Catalog → Delivery including assessment → shell retirement`

## Implementation Steps

1. Freeze route/component/payload behavior with targeted characterization tests.
2. Migrate Catalog: units, curriculum versions, programs, specializations, syllabus.
3. Migrate Calendar/Delivery: semesters, offerings, registrations, lectures, sessions.
4. [x] Migrate attendance, roster, assessment/gradebook, course result, and all Canvas
   mapping/sync/provider behavior within Academic Delivery.
5. In each slice replace inline validation, raw responses, legacy filters, and literal URLs.
   - [x] Unit Catalog: migrated index filtering to `useDataTable`, sanitized
     filter state, and named-route HTTP/frontend interactions; preserved filtered
     exports and added bulk-delete authorization coverage.
   - [x] Curriculum Versions and Curriculum Units: replaced inline HTTP/action
     validation with Catalog FormRequests, aligned supported filter contracts,
     removed unsupported Unit controls, and migrated active frontend interactions
     to `useDataTable`, named routes, and the API wrapper.
   - [x] Syllabus Templates: replaced page-index inline validation and the
     duplicate POST route, migrated template filtering to `useDataTable`, and
     moved active template navigation and mutations to named routes while
     preserving API response envelopes and edit-lock behavior.
   - [x] Lectures and Teaching Hours: replaced inline validation and raw JSON
     responses on lecture helpers and exports, preserved campus and `all`
     filter compatibility, and migrated teaching-hours detail filtering to
     `useDataTable`, named routes, and DatePicker controls.
   - [x] Canvas Course Mapping: moved mapping-list and offering lookup filters
     into Delivery FormRequests, preserved sanitized sorting and campus-scoped
     `all` lookup behavior, and migrated mapping interactions to the API
     wrapper and named routes.
   - [x] Canvas Syllabus, Assignment, and Grade Sync: standardized summary and
     grade-sync API envelopes, added assignment selection validation, restored
     the registered syllabus-summary route, and preserved Canvas sync dialogs
     and course-offering grade preview/apply behavior.
   - [x] Lecturer Assessment: moved bulk-grade and report query validation into
     Delivery lecturer FormRequests, preserved authorization-before-validation
     and export-format behavior, and standardized validation failures with the
     existing API envelope.
   - [x] Calendar/Course Registration retirement: removed the unregistered
     legacy registration service and API controller shells after confirming
     live Delivery actions/controllers own the active flows; lowered the
     frozen-service, frozen-controller, and direct-JSON ratchets accordingly.
   - [x] Calendar service retirement: removed the unreferenced semester
     management and automated-enrollment services after confirming no live
     routes, container bindings, or callers remained; lowered the
     frozen-service ratchet accordingly.
   - [x] Course Offering route retirement (issues 09, 10, 11): moved the
     finalize, Canvas grade-sync preview/apply (issue 10), and recalculate
     preview/apply (issue 11) live routes from the frozen
     `routes/web/course-offerings.php` split file into the owned
     `app/Modules/Academic/routes/web.php`, removed the now-empty frozen
     file, updated the retired-path assertion in
     `AttendanceDeliveryMigrationArchTest`, and lowered the frozen-route
     ratchet in both `config/migration_debt.php` and
     `MigrationDebtContract::BASELINE_CEILINGS`. Route names, permissions,
     and the EGC recalculate-demotion fix (issue 09) are covered by green
     tests; no orphan reference to the deleted file remains.
6. Remove replaced routes/controllers/services immediately and lower all affected ratchets.
7. Review import/export parity, scheduler entries, permissions, and campus scoping.

## Test Scenario Matrix

| Scenario | Expected |
|---|---|
| Unit/curriculum import and export | Same validated rows and errors |
| Offering/session scheduling conflict | Same rejection and transaction rollback |
| Attendance and grade commit | Idempotent, authorized, auditable |
| Filter/sort/pagination | Whitelisted and query-retaining |
| Canvas retry | No duplicate remote/local state |
| Route snapshot | No public route loss or duplicate legacy route |

## Success Criteria

- [ ] Academic Catalog and Delivery (including assessment) imports are owner-internal only.
- [ ] Their frozen routes/controllers/services and local HTTP/frontend debt are zero.
- [ ] Issues 09 and 10 have accepted evidence and no unresolved parity item.

## Risks and Security

- Scheduling and grade changes are high-impact multi-write operations; use transactions,
  authorization policies, audit logs, and replay-safe integrations.
