---
phase: 1
title: "Retire duplicate document-type columns"
status: done
priority: P1
effort: "5h"
dependencies: []
---

# Phase 1: Retire duplicate document-type columns

<!-- Updated: Red Team Session 1 - required-flag consequence, checklist fallback, guard in code, test criteria rewritten -->

## Overview

Deactivate the three `application_document_types` catalog rows that duplicate a
base document concept, so the applications list renders one column per real
document type. Independent of every other phase; shippable alone.

**This is not a pure UI change.** `transcript_1` is a *required* document type,
so retiring it also changes document-completeness results. See below.

## Root Cause

`app/Modules/Admissions/Support/Crm/CrmApplicationMapper.php:34-47` maps CRM's
variant fields onto the **base** `file_type_code` and distinguishes them by
`page_index`:

```php
['crm_field' => 'file_transcript',    'file_type_code' => 'transcript', 'page_index' => 0],
['crm_field' => 'file_transcript_1',  'file_type_code' => 'transcript', 'page_index' => 1],
['crm_field' => 'file_english_certificate', 'file_type_code' => 'english_certificate', 'page_index' => 0],
['crm_field' => 'file_english_certificare', 'file_type_code' => 'english_certificate', 'page_index' => 1],
['crm_field' => 'file_other_achievements',   'file_type_code' => 'other_achievements', 'page_index' => 0],
['crm_field' => 'file_other_achievements_2', 'file_type_code' => 'other_achievements', 'page_index' => 1],
```

The catalog nonetheless still holds `transcript_1`, `english_certificare`, and
`other_achievements_2` as separate active rows (seeded by the one-shot legacy
import `App\Services\Admissions\ApplicationBackfillService` from
`Asia_File_Types.csv`). `ListApplicationsQuery::filters()` renders one column per
**active** catalog row, so every variant gets a column that CRM sync will never
fill again.

## Completeness consequence (D9)

Measured on dev, 2026-08-11:

| Code | `required` | `int_required` |
|---|---|---|
| `transcript` | 1 | 1 |
| **`transcript_1`** | **1** | **1** |
| `english_certificare` | 0 | 0 |
| `other_achievements_2` | 0 | 0 |

Active required set today: `id_card_front`, `id_card_back`, `transcript`,
`transcript_1`, `diploma`. After retirement: the same minus `transcript_1`.

| Effect | Applications |
|---|---|
| `missing_required` count changes | 299 |
| **Flip incomplete → complete** | **149** |

**D9 (accepted):** one transcript satisfies the transcript requirement.
Rationale — CRM sync only ever writes `transcript`, so requiring `transcript_1`
permanently flags every CRM-synced applicant as incomplete for a document that
can no longer arrive. `transcript` remains `required=1`, so transcripts are
still required.

This is an approval-gate change affecting 149 live applications. It must be
stated in the release note, not buried in a migration.

## Surface-by-surface effect (corrected)

The earlier claim that the 435 documents "become invisible" was wrong for two of
three surfaces. `GetApplicantDocumentChecklistQuery:36-45` and
`ApplicationDocumentService:52-64` both re-emit any `file_type_code` present on
the application but absent from the active catalog as an *uncatalogued* group.

| Surface | Effect on retired-type documents |
|---|---|
| List page columns | Column removed — documents no longer shown |
| Excel export | Column removed — documents no longer exported |
| Show.vue / Documents.vue checklist | **Still visible**, moved to an unordered tail, `required: false` |

That is acceptable and arguably desirable, but it must be a known outcome rather
than a surprise during verification.

## Namespace note

`app/Models/ApplicationDocumentType.php` is a deprecated one-line `class_alias`
shim pointing at `App\Modules\Upload\Models\ApplicationDocumentType` (produced by
the completed plan `260809-1557-legacy-model-module-migration`). All **new** code
in this plan uses the canonical `App\Modules\Upload\Models\...` namespace —
including verification snippets.

Pre-existing deprecated imports (e.g. `ListApplicationsQuery.php:7`) are **left
alone**. Sweeping them is a separate plan —
`plans/260811-0012-deprecated-model-shim-namespace-sweep/`.

## Requirements

- Functional: `transcript_1`, `english_certificare`, `other_achievements_2` no
  longer produce columns **on the list page and the export**. (Not the
  checklist — see the surface table above.)
- Functional: `other_achievements_1` is **not** touched — it is a distinct
  document ("Giấy xác nhận sinh viên của anh/chị/em ruột"), not a duplicate.
- Functional: the required-document set loses `transcript_1` deliberately (D9),
  and the 149-application delta is verified, not discovered.
- Non-functional: no `application_documents` rows deleted or rewritten (D1).
- Non-functional: reversible by flipping `active` back to `1`.
- Non-functional: a re-run of the legacy backfill must **not** silently
  reactivate the retired codes.

## Architecture

Deactivation, not deletion. `ListApplicationsQuery::filters()`,
`GetApplicantDocumentChecklistQuery`, and `StudentApplicationExport` all read
through `ApplicationDocumentType::query()->activeOrdered()`.

**The migration alone is not a sufficient guard.** `application_document_types`
is runtime-synced state: `ApplicationDocumentTypeSyncService::sync()` upserts by
`code` with `active` taken straight from the payload, and
`ApplicationBackfillService::catalogEntries()` always emits
`'active' => (bool)(int)($row['active'] ?? 1)`. A backfill re-run flips all three
rows back on, inside a transaction, with no failing test.

So the invariant lives in **code**, not only in a one-shot migration: a
`RETIRED_CODES` constant honored by `ApplicationDocumentTypeSyncService` so a
sync can never reactivate them. The migration remains the one-time state fix.

Sync-safety verified: `CrmApplicationSyncService::run()` calls
`documentTypeSync->sync()` with only two hardcoded codes (`id_card_back`,
`scholarship_certificate`) and omits `active` entirely, so a routine CRM sync
does not touch these rows.

## Related Code Files

- Create: `database/migrations/<timestamp>_deactivate_duplicate_application_document_types.php`
- Modify: `app/Services/ApplicationDocumentTypeSyncService.php` — honor `RETIRED_CODES`
- Modify: `app/Modules/Upload/Models/ApplicationDocumentType.php` — hold the `RETIRED_CODES` constant
- Modify: `app/Modules/Admissions/Support/Crm/CrmApplicationMapper.php` (docblock only — why the variants fold)
- Test: `tests/Feature/Admissions/DuplicateDocumentTypeRetirementTest.php` (create)

## Implementation Steps

1. Add `RETIRED_CODES = ['transcript_1', 'english_certificare', 'other_achievements_2']`
   to `ApplicationDocumentType` and make `ApplicationDocumentTypeSyncService::sync()`
   force `active = false` for any code in that list, regardless of payload.

2. Write the migration, using the **canonical namespace**:

   ```php
   use App\Modules\Upload\Models\ApplicationDocumentType;

   ApplicationDocumentType::query()
       ->whereIn('code', ApplicationDocumentType::RETIRED_CODES)
       ->update(['active' => false]);
   ```

   `down()` restores `active = true` for the same codes. Verified safe: all
   three are currently `active = 1`, so `down()` reproduces the pre-migration
   state exactly.

3. Note in the migration docblock that a fresh environment may run this before
   the catalog rows exist (they arrive later from CSV/CRM) — which is why step 1
   exists: the sync-level guard, not the migration, is what makes this durable.

4. Write the tests (see Success Criteria) before running the migration on dev.

5. Run the migration on dev, then verify.

## Validation

```bash
./scripts/dev.sh artisan migrate
./scripts/dev.sh artisan test tests/Feature/Admissions/DuplicateDocumentTypeRetirementTest.php
./scripts/dev.sh artisan test tests/Feature/Admissions
```

Active catalog must drop from 11 rows to 8 (canonical namespace):

```bash
./scripts/dev.sh artisan tinker --execute="foreach(\App\Modules\Upload\Models\ApplicationDocumentType::where('active',true)->orderBy('order')->get() as \$t){ echo \$t->code.' | '.\$t->name.PHP_EOL; }"
```

Then load `/student-applications` and confirm one transcript column, one
english-certificate column, one other-achievements column, and that
`other_achievements_1` is still present.

## Success Criteria

- [ ] Active codes are exactly: `scholarship_certificate`, `id_card_front`,
      `id_card_back`, `diploma`, `transcript`, `english_certificate`,
      `other_achievements`, `other_achievements_1` — asserted as an explicit
      literal list, not a suffix heuristic
- [ ] `ListApplicationsQuery::filters()['document_types']` excludes the three
      retired codes and still includes `other_achievements_1`
- [ ] Running `ApplicationDocumentTypeSyncService::sync()` with a catalog payload
      containing `['code' => 'transcript_1', 'active' => true]` leaves the row
      **inactive** (guards against a backfill re-run)
- [ ] `GetApplicantDocumentChecklistQuery` places a retired-type document in the
      uncatalogued tail with `required => false` and `is_missing => false`
- [ ] An application holding `transcript` but not `transcript_1` reports **no**
      missing required document after the change, and did report one before
- [ ] `down()` restores all three rows to active
- [ ] `tests/Feature/Admissions` green

## Risk Assessment

| Risk | Mitigation |
|---|---|
| **149 applications silently become "complete"** | D9 is an explicit accepted decision; asserted by test; must appear in the release note so admissions staff know the rule changed |
| Backfill re-run reactivates the retired rows | Guard moved into `ApplicationDocumentTypeSyncService` (step 1) with a test — not a docblock |
| Fresh environment migrates before catalog rows exist, leaving duplicates active | Same sync-level guard covers it; the migration is only the one-time fix |
| A user needs a retired legacy document | Data intact in `application_documents`, and still visible on the detail/documents checklist |
| Export column count changes break a downstream consumer | `StudentApplicationExport` builds headers dynamically from `activeOrdered()`; verify the export renders during validation |
