---
phase: 1
title: "Schema and storage"
status: completed
priority: P1
effort: "1.5d"
dependencies: []
---

# Phase 1: Schema and storage

## Overview

Add every column/table the CRM payload needs, relax the constraints that would make ordinary CRM records
permanently unsyncable, and prove nothing in the live code path breaks on a null campus.

## Requirements

- Functional: store all CRM fields decided in D3/D5/D6/D9-D14; store CRM value mappings including the target intake; store per-subject scores.
- Non-functional: no data loss on existing rows; a null campus must not leak across campus scope, must not make an application uneditable, and must not make it unrejectable.

## Architecture

### 1. `student_applications` — new columns

`crm_campus`, `crm_major`, `province`, `new_province`, `new_street`, `new_ward`, `permanent_address`,
`birth_place`, `nationality`, `religion`, `id_card_place_of_issue`, `school`, `graduation_year`,
`gpa` decimal(4,2), `gpa_type`, `scholarship`, `pathway_gateway`, `uu_dai_gc`,
`crm_paid_amount` decimal(15,2), `last_synced_at` timestamp.

Existing `address` keeps its meaning = current residence (`new_address ?? address` from CRM).

### 2. Constraint relaxations (each in its own migration)

- **`campus_code` NOT NULL → nullable.** There is **no foreign key** on this column today
  ([2025_08_04_195804_create_student_applications_table.php:31](../../database/migrations/2025_08_04_195804_create_student_applications_table.php)) —
  it is a bare `string(20)`. Nothing to preserve, and no referential safety net exists, which is exactly
  why the Phase 5 gate has to do that job in application code.
- **`email` → nullable, unique index dropped.** Created NOT NULL UNIQUE and never relaxed. CRM legitimately
  sends blank emails and siblings sharing a parent's address; with the unique index those records fail on
  every run forever. Identity is `student_code` (D1), not email.
  **Manual-entry FormRequests keep their `unique:student_applications,email` validation rule**
  (validation V3) — only the CRM path may create duplicates, so staff typo protection is unchanged.
  Still grep for code assuming uniqueness at read time (`firstWhere('email'`, `where('email')->first()`),
  which the dropped index no longer guarantees.

<!-- Updated: Validation Session 1 - manual-form unique rule retained -->


Rollback honesty: once a sync has run, `down()` cannot restore `NOT NULL` on `campus_code` (synced rows
hold NULL) and cannot restore the email unique index (duplicates may exist). Each `down()` must detect
that state and abort with a clear message rather than corrupt data by coercing NULL to `''`. The plan's
position: **rollback is safe only before the first sync run; after that, forward-fix only.**

### 3. `application_academic_scores`

`id`, `student_application_id` (FK cascade), `subject_code` varchar, `score` decimal(5,2),
`source` varchar (`school_report` | `national_exam`), timestamps,
`unique(student_application_id, subject_code)`.
Subject codes: `toan, ly, hoa, sinh, tin_hoc, van, lich_su, dia_ly, tieng_anh, giao_duc_cong_dan,
giao_duc_quoc_phong, cong_nghe, kt_pl` (`school_report`) and
`thithpt_toan, thithpt_van, thithpt_option1, thithpt_option2` (`national_exam`).
Null CRM value → no row (absence means "not reported", distinct from a 0 score).

### 4. `crm_value_mappings`

`id`, `kind` varchar, `crm_value` varchar, `local_code` varchar nullable, timestamps,
`unique(kind, crm_value)`. `kind` ∈ campus, major, specialization, intake, scholarship, pathway_gateway,
uu_dai_gc. Per repo convention `kind` and `source` are BE-validated strings, not DB enums.
A row with null `local_code` = discovered but not yet mapped.

**Target intake lives here** as the single row `kind='intake', crm_value='__default__'`, *not* in
`system_settings`. `SystemSetting` is Platform-owned behind a closed key registry whose writer throws on
an unregistered key ([SystemConfigurationStore.php:58](../../app/Modules/Platform/Support/SystemConfigurationStore.php),
[SystemConfigurationDefinition.php:18](../../app/Modules/Platform/Support/SystemConfigurationDefinition.php)),
and writing the model directly from Admissions trips the existing boundary arch tests. One row in a table
this phase already creates costs nothing and stays inside the module.

Seed `kind='major'` rows from the existing `IntendedProgramNormalizer::LABEL_TO_CODE`
([app/Services/Admissions/IntendedProgramNormalizer.php:36](../../app/Services/Admissions/IntendedProgramNormalizer.php))
so staff never re-map labels the system already knows (`Trí tuệ nhân tạo → AI`, …). Ambiguous labels are
deliberately absent there; leave them unmapped rather than guessing.

### 5. `application_guardians` — `unique(student_application_id, relationship)`

Phase 3 reconciles CRM guardians by relationship. That key must be enforced by the schema or two `father`
rows (legal today) silently break the upsert. Check existing rows for duplicates before adding the index;
if any exist, the migration must fail loudly rather than drop data.

### 6. Document type codes

`id_card_back` and `scholarship_certificate` are seeded in Phase 3 via
`ApplicationDocumentTypeSyncService`. Note the catalog is CRM-owned by contract and has no repo seeder,
so its current contents cannot be confirmed from source — Phase 3 seeds idempotently by `code` instead of
asserting absence. Pin which `ApplicationDocumentType` model is canonical (two exist:
`app/Models/` and `app/Modules/Upload/Models/`) before writing to it.

### 7. Null-campus consequences (the live path, not the legacy one)

The live list screen is the module controller and query, not the global controller:
- Campus filter [ListApplicationsQuery.php:18](../../app/Modules/Admissions/Queries/ListApplicationsQuery.php) —
  `where('campus_code', $code)` excludes NULL, so a single-campus view is safe; the all-campus view must
  show null-campus rows so they are not invisible.
- Edit/store validation requires campus/program/intake
  ([UpdateApplicationRequest.php:20](../../app/Modules/Admissions/Http/Requests/Admissions/UpdateApplicationRequest.php)):
  without relaxing these, a staff member cannot save *any* edit to a synced application until Phase 4
  ships. Relax to `nullable` + `exists` (keep `exists` — it is the only thing stopping a bogus code) and
  let the Phase 5 gate enforce presence at approval time.
- Policy: `approve`/`reject`/`revoke` all return false when `$application->campus?->id` is null
  ([StudentApplicationPolicy.php:34](../../app/Modules/Admissions/Policies/StudentApplicationPolicy.php)).
  Per user decision: **reject/revoke authorize against the actor's current campus permissions when the
  application has no campus; approve stays denied** (Phase 5 turns that denial into a readable message).

## Related Code Files

- Create: `database/migrations/<ts>_add_crm_fields_to_student_applications_table.php`
- Create: `database/migrations/<ts>_make_campus_code_nullable_on_student_applications_table.php`
- Create: `database/migrations/<ts>_relax_email_constraints_on_student_applications_table.php`
- Create: `database/migrations/<ts>_create_application_academic_scores_table.php`
- Create: `database/migrations/<ts>_create_crm_value_mappings_table.php`
- Create: `database/migrations/<ts>_add_relationship_unique_to_application_guardians_table.php`
- Create: `app/Modules/Admissions/Models/ApplicationAcademicScore.php`
- Create: `app/Modules/Admissions/Models/CrmValueMapping.php`
- Modify: `app/Models/StudentApplication.php` (fillable, casts, `academicScores()`)
- Modify: `app/Modules/Admissions/Http/Requests/Admissions/UpdateApplicationRequest.php`, `StoreApplicationRequest.php` (campus/program/intake nullable)
- Modify: `app/Modules/Admissions/Policies/StudentApplicationPolicy.php` (null-campus reject/revoke)
- Modify: `app/Modules/Admissions/Queries/ListApplicationsQuery.php` (all-campus view includes null campus)
- Modify: `database/factories/StudentApplicationFactory.php` if it assumes non-null campus
- Create: `tests/Feature/Admissions/NullCampusApplicationTest.php`

## Implementation Steps

1. Write the column-additions migration.
2. Write the two constraint-relaxation migrations separately, each with a guarded `down()` that aborts when the data can no longer satisfy the old constraint.
3. Grep every site assuming email uniqueness at read time; keep the `unique` rule on manual-entry FormRequests.
4. Create the two new tables + the guardian relationship index (with a pre-flight duplicate check).
5. Add the two module models; extend `StudentApplication` fillable/casts/relation.
6. Relax the module FormRequests; adjust the policy for null-campus reject/revoke; make the all-campus list include null-campus rows.
7. Seed `kind='major'` mappings from `LABEL_TO_CODE`.
8. Run migrate + the Admissions test dir.

## Success Criteria

- [ ] Migrations run forward cleanly; each `down()` either restores the prior state or aborts with an explicit message — never silently coerces data.
- [ ] `campus_code` and `email` accept null; two applications may share an email.
- [ ] A null-campus application never appears inside a single-campus scoped list, and does appear in the all-campus list.
- [ ] A null-campus application can be edited and saved, and can be rejected/revoked by a staff member with the right permission at their current campus.
- [ ] A null-campus application still cannot be approved (message comes in Phase 5).
- [ ] `application_academic_scores` rejects a duplicate `(application, subject_code)`; `application_guardians` rejects a duplicate `(application, relationship)`.
- [ ] `crm_value_mappings` contains the seeded major labels after migration.
- [ ] `tests/Feature/Admissions/*` and the boundary arch tests still green.

## Risk Assessment

- **Campus scoping leak** (high): mitigated by an explicit test asserting a null-campus application is absent from a campus-scoped list and present in the all-campus list.
- **Dropping the email unique index** (high): removes a real guard against duplicate manual entries. Mitigated by keeping the `unique` *validation* rule on manual-entry FormRequests, so only the CRM path can create duplicates.
- **Guardian relationship index on existing data** (medium): pre-flight check; fail loudly rather than dedupe automatically.
- **Irreversible rollback after first sync** (medium): stated openly in the migration message and in the release runbook rather than pretended away.
- **Column sprawl** (low): ~20 new columns on an already wide table. Accepted — D3 chose queryable columns over a JSON blob.
