---
phase: 4
title: "Mapping config UI"
status: completed
priority: P1
effort: "1.5d"
dependencies: [1, 3]
---

# Phase 4: Mapping config UI

## Overview

An Admissions screen listing every CRM value discovered by sync that has no local code yet, plus the
target intake. Saving a mapping backfills every pending application carrying that value — which makes
this screen a cross-campus write surface, and therefore an authorization problem before it is a UI problem.

## Requirements

- Functional: discover unmapped values from synced data; assign a local code; set target intake; backfill on save and at the start of each sync run.
- Non-functional: adding a campus/major needs no deploy; a campus-scoped staff member cannot read or rewrite other campuses' data through this screen; backfill never races the sync command.

## Architecture

### Authorization (do this first)

Mapping rows and the target intake are **global**, and the resolver writes `campus_code` across every
pending application carrying a raw value. The existing admissions permissions are evaluated against
`session('current_campus_id')` ([IdentityServiceProvider.php:67](../../app/Modules/Identity/Providers/IdentityServiceProvider.php)),
so reusing one would let a campus-scoped officer remap `"Đà Nẵng" → HCM`, pull those applicants into their
own campus, and then approve them — the approve policy derives authority from
`$application->campus` ([StudentApplicationPolicy.php:34](../../app/Modules/Admissions/Policies/StudentApplicationPolicy.php)),
which they just changed.

Therefore:
- New permission `manage_crm_value_mapping`, granted only to org-wide roles.
- It **must** be registered in `config/permission.php` — gates are defined by iterating
  `config('permission.access')` ([IdentityServiceProvider.php:64](../../app/Modules/Identity/Providers/IdentityServiceProvider.php)),
  so a DB-only permission fails closed with no log line, and the "obvious" debug fix is to downgrade the
  route to a campus permission, landing straight back on the escalation.
- Grant it in the role seeder.
- Discovery query is scoped to the campuses the actor may see; an all-campus actor sees everything.

### Route placement

Register the routes **before** the `GET /{studentApplication}` wildcard at
[routes/web.php:14](../../app/Modules/Admissions/routes/web.php) — Laravel matches in registration order,
and an appended `crm-mappings` route resolves to the show route ("No query results for model
[StudentApplication] crm-mappings"). The existing static `create`/`export` routes are only safe because
they precede the wildcard.

### Discovery

A query, not a write: for each `kind`, select distinct raw values from `student_applications`
(`crm_campus`, `crm_major`, `scholarship`, `pathway_gateway`, `uu_dai_gc`) left-joined against
`crm_value_mappings`, with a count of affected applications, scoped to the actor's campus set. Rows with
no mapping, or a mapping whose `local_code` is null, are "unmapped". Nothing is inserted during discovery —
the mapping table only ever holds staff decisions (plus the Phase 1 seed).

### Local code options

From the live catalogs: `campuses.code`, `programs.code`, `specializations.code`. `scholarship`,
`pathway_gateway`, `uu_dai_gc` have no local catalog — listed for visibility, never blocking (D8).

### Target intake

The single `crm_value_mappings` row `kind='intake', crm_value='__default__'`, `local_code` = a
`semesters.code`. Read and written only through `CrmMappingSettings` so the "one global intake"
assumption stays a one-line change (plan open question 2). `system_settings` is deliberately not used —
see Phase 1 §4.

### Resolve / backfill

Runs at two moments through one service: on mapping save, and at the start of each sync run.

- Touches only `status = pending` applications.
- Fills only **null** derived columns (`campus_code`, `intended_program`, `intended_specialization`,
  `intake`); never overwrites a value a staff member set by hand.
- Acquires the same lock the sync command uses; skipped (with a user-visible notice) while a sync is
  running, so the two writers cannot interleave a full-row `save()` with a targeted column write.

## Related Code Files

- Create: `app/Modules/Admissions/Http/Web/CrmMappingController.php`
- Create: `app/Modules/Admissions/Http/Requests/Admissions/SaveCrmValueMappingRequest.php`
- Create: `app/Modules/Admissions/Queries/ListUnmappedCrmValuesQuery.php`
- Create: `app/Modules/Admissions/Services/CrmMappingResolver.php`
- Create: `app/Modules/Admissions/Support/Crm/CrmMappingSettings.php`
- Create: `resources/js/pages/StudentApplications/CrmMappings.vue`
- Modify: `app/Modules/Admissions/routes/web.php` (routes placed before the `{studentApplication}` wildcard)
- Modify: `config/permission.php` (register `manage_crm_value_mapping`)
- Modify: `database/seeders/InitialSetup/RoleAndPermissionSeeder.php` (grant to org-wide roles only)
- Modify: `app/Modules/Admissions/Services/CrmApplicationSyncService.php` (call the resolver before the record loop)
- Create: `tests/Feature/Admissions/CrmMappingConfigTest.php`

## Implementation Steps

1. Register the permission in `config/permission.php` + seeder. Write the authorization test first: a user without it gets 403 on index and store; a campus-scoped user cannot see another campus's unmapped values.
2. Write `ListUnmappedCrmValuesQuery` + test: three applications sharing one unmapped `crm_campus` produce one row with count 3; another campus's values are absent for a scoped actor.
3. Write `CrmMappingResolver` + test: saving `campus: "TP. Hồ Chí Minh" → HCM` fills `campus_code` on all pending applications with that raw value, leaves enrolled ones alone, and does not clobber a manually set different code.
4. Add `CrmMappingSettings` over the `kind='intake'` row; validate the code exists in `semesters`.
5. Controller + FormRequest + routes, positioned before the wildcard.
6. Vue page: unmapped values grouped by kind (kind, CRM value, affected count, local-code select) + target-intake select. Follow existing `StudentApplications/*.vue` conventions.
7. Wire the resolver into the sync service before the record loop, sharing the command's lock.

## Success Criteria

- [ ] A user without `manage_crm_value_mapping` gets 403 on both index and store.
- [ ] A campus-scoped actor sees only their campus's unmapped values and cannot backfill across campuses.
- [ ] `GET /student-applications/crm-mappings` resolves to the mapping screen, not the show route.
- [ ] A freshly synced batch with an unknown campus shows exactly one unmapped row with the correct affected count.
- [ ] Saving the mapping fills `campus_code` on all affected pending applications, with no re-sync.
- [ ] Enrolled/rejected applications are never modified by backfill; a manually set differing code is preserved.
- [ ] Seeded major labels (from `LABEL_TO_CODE`) do not appear as unmapped.
- [ ] Target intake is read/written through one class; a grep returns a single production call site.
- [ ] Backfill refuses to run while a sync holds the lock.

## Risk Assessment

- **Cross-campus privilege escalation** (critical): the reason the permission work is step 1 and not step 4.
- **Unregistered permission failing closed** (high): `config/permission.php` entry is part of the same step, with a test.
- **Backfill overwriting staff edits** (high): fills only null columns; explicitly tested.
- **Concurrent writers** (high): shared lock with the sync command.
- **Unmapped-values query cost** (low): distinct over a small table; index only if measured.
