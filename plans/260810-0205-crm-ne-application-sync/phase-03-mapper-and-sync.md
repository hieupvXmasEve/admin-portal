---
phase: 3
title: "Mapper and sync command"
status: completed
priority: P1
effort: "2d"
dependencies: [1, 2]
---

# Phase 3: Mapper and sync command

## Overview

Turn each raw CRM record into an application payload, upsert it through the **live module write path**,
and run the whole batch from one artisan command with per-record failure isolation and visible failures.

## Requirements

- Functional: idempotent upsert of application + guardians + documents + academic scores; skip non-pending applications; record `last_synced_at`.
- Non-functional: one bad record never aborts the run; failures are attributable and non-silent; no applicant PII in logs; CRM strings never trusted as URLs or storage keys.

## Architecture

### Step 0 — write to the path that is actually live

`app/Services/Admissions/ApplicationIngestionService` is reachable only from `routes/api/v1/admissions.php`,
which no bootstrap file requires — the live ingest route is
[app/Modules/Admissions/routes/api.php:18](../../app/Modules/Admissions/routes/api.php) →
`Http/Api/IngestionController` → **`UpsertCrmApplicationAction`**. All Phase 3 changes target the module
action.

**Decision (validation V2): leave the unreachable trio untouched** — `routes/api/v1/admissions.php`,
`app/Http/Controllers/Api/V1/Admissions/IngestionController.php`, and the global ingestion service.
Do not modify, do not delete, do not register. Cleanup is a separate task; touching it here would either
duplicate the ingest endpoint or delete something that may just be missing a route registration.

<!-- Updated: Validation Session 1 - dead-code trio explicitly out of scope -->

### Run mode

**Manual only (validation V1) — superseded by the addendum below.** No scheduler entry: automating a
nightly overwrite before anyone has checked real CRM data against real applications is still how a mapping
mistake gets multiplied 300×. What changed is *how* a manual run is triggered — a UI button on top of the
artisan command, not instead of it.

Failure visibility (validation V6): the command's summary table plus the log file. No failure table, no
failure screen — the operator running it by hand sees the result immediately, so persisting failures would
be infrastructure for a consumer that does not exist yet. Revisit when scheduling lands.

### Addendum: UI-triggered sync (2026-08-10, post-implementation)

Follow-up request: staff must be able to run a sync from the mapping screen instead of a shell. Decisions
confirmed with the user:

- **Synchronous (blocking) request**, not a queued job. The button posts, the request blocks until
  `CrmApplicationSyncService::run(false, null)` returns, the response carries the summary. Matches the
  existing volume assumption (validation V5: a few hundred records, no pagination) — accepted risk: if CRM
  volume grows enough to approach PHP-FPM/nginx's request timeout, that is the same signal Phase 2 already
  named for adding batching, not a reason to add job-queue infrastructure now (ponytail).
- **Same permission** as mapping (`manage_crm_value_mapping`) — one screen, one org-wide-only gate, no new
  permission to seed.
- **Same route/controller** as the mapping screen (`CrmMappingController`), not a new console-triggering
  surface — keeps the arch-test boundary (`AdmissionsCrmSyncPlacementArchTest`) trivially satisfied.
- Result surfaces through a new Inertia shared flash key (`crm_sync_summary`), not a redirect to a
  different page — the operator stays on the mapping screen and sees counts + failures inline, same
  information shape as the artisan command's summary table.
- `CrmSyncException` (login/request/response failures) is caught in the controller and flashed as a plain
  `error` message — the exception messages already carry no credentials (Phase 2 assertion), so they are
  safe to show as-is.

Files: `app/Http/Middleware/HandleInertiaRequests.php` (new `crm_sync_summary` flash key), `CrmMappingController.php`
(+`syncNow`), `routes/web.php` (+ `POST /student-applications/crm-mappings/sync`), `CrmMappings.vue`
(Sync button + result panel), `tests/Feature/Admissions/CrmMappingSyncActionTest.php`.

**Superseded same day** by Phase 2's Addendum 2: login became an explicit, persisted, one-time action
(separate "Login" button + `POST /crm-mappings/login`), and `syncNow` no longer logs in at all — it fails
fast with `CrmAuthenticationException` if no token is stored. The summary-flash mechanics described above
are unchanged.

### Bug fix (2026-08-10, same day): false-positive "success" toasts

Reported: clicking Login showed "Logged in to CRM." but the token never persisted (`logged_in: false` in
the DB afterward). Root cause: Inertia's `onSuccess` callback fires for *any* successful HTTP visit
(302→200), including `back()->with(['error' => ...])` — a caught `CrmSyncException` is still an HTTP
success from Inertia's point of view; `onError` only fires for actual validation failures (422). Every
action in `CrmMappings.vue` (`loginNow`, `syncNow`, `saveMapping`, `saveIntake`, `saveIntegration`) had
`onSuccess` hardcode a positive toast message instead of reading the real flash — so a genuine server-side
failure always displayed as success. Same root cause as the original "toast hiện nhưng không có data"
report for `syncNow` (a caught exception has no `crm_sync_summary`, so it fell through to a hardcoded
"Sync finished." success message).

Fix: a shared `toastFromFlash()` helper reads `usePage().props.flash` (error > warning > success priority)
inside every `onSuccess`, replacing the hardcoded messages. No backend change — this was purely a client
assumption bug. No regression test added (no JS/Vue unit test harness exists for this page in the repo);
verified by re-reading Inertia's documented `onSuccess`/`onError` semantics against the controller's actual
response shapes for both the happy and caught-exception paths.

### Match key — a branch, not a fallback

`UpsertCrmApplicationAction` currently does
`where('crm_admission_id', $data['crm_admission_id'])`
([UpsertCrmApplicationAction.php:34](../../app/Modules/Admissions/Actions/UpsertCrmApplicationAction.php)).
Laravel rewrites `where($col, null)` into `whereNull($col)`, and D1 leaves `crm_admission_id` null for the
NE path — so a naive "fallback" never fires and the first CRM record would match, and overwrite, an
arbitrary manually created application. The resolution must be explicit and mutually exclusive:

```php
$existing = filled($data['crm_admission_id'] ?? null)
    ? StudentApplication::query()->where('crm_admission_id', $data['crm_admission_id'])->lockForUpdate()->first()
    : StudentApplication::query()->where('student_code', $data['student_code'])->lockForUpdate()->first();
```

A record with a blank `student_code` and no `crm_admission_id` is rejected before this point — never
allowed to reach a `where(..., null)`.

### Attribute whitelist

`attributes()` projects through a fixed `Arr::only()` list
([UpsertCrmApplicationAction.php:59](../../app/Modules/Admissions/Actions/UpsertCrmApplicationAction.php))
containing none of the ~20 Phase 1 columns. Extend both the whitelist and `StudentApplication::$fillable`,
or the entire feature silently writes NULLs while every unit test passes. Verification is a **persistence**
assertion, not a mapper-output assertion.

### Mapper

`CrmApplicationMapper` is pure: raw CRM array in, payload array out, no DB, no HTTP.

- `date_of_birth` `d/m/Y` → `birth_day`/`birth_month`/`birth_year`; unparseable → record fails validation, not a silent null.
- `gender` lowercased, must be male/female/other; anything else → null.
- `address` = `new_address ?? address`.
- Numeric strings (`gpa`, `ielts_*`) cast to float; non-numeric → null.
- `student_code` validated against `^[A-Za-z0-9_-]{1,20}$` — it becomes part of an identity lookup, so a malformed value fails the record rather than propagating.
- Every `file_*` URL validated: **`https` scheme only, no host allowlist** (validation V4 — CRM mixes Google Drive links with its own hosts, and a host list would silently drop real documents). A URL failing validation is dropped with a logged reason — `javascript:` and `data:` URLs are rendered as `<a :href>` in the staff UI ([Show.vue:518](../../resources/js/pages/StudentApplications/Show.vue)), so this is a trust-boundary control, not hygiene.
- `campus`, `major`, `scholarship`, `pathway_gateway`, `uu_dai_gc` copied verbatim into raw columns; **no code resolution here** (D12/D16).
- Ignored entirely: `registration_form`, `file_id_card_photo`.

### Documents

One row per valid URL, using the code/page_index table in the
[brainstorm report](../reports/brainstorm-260810-0032-crm-ne-application-sync.md).

- `crm_file_id` = `ne:{student_application_id}:{file_type_code}:{page_index}` — keyed on the **local**
  id, never on the CRM string. `crm_file_id` is globally unique and the writer sets
  `student_application_id` on match ([UpsertCrmApplicationAction.php:90](../../app/Modules/Admissions/Actions/UpsertCrmApplicationAction.php)),
  so a CRM-controlled key would let a crafted `student_code` re-point another applicant's documents.
  The `updateOrCreate` match must additionally be scoped to `student_application_id`.
- **Reconciliation**: after upserting, delete this application's `ne:`-prefixed document rows that were not
  in the just-written set. Without it, a CRM URL going null leaves a row pointing at a dead or recycled
  file forever — and `link` is NOT NULL so it cannot be blanked. This mirrors the scores rule.
- Reconciliation is scoped to `ne:` keys only: documents from the push path or the legacy backfill are never touched.

### Guardians (user decision)

- **NE sync**: reconcile father/mother only, in a deterministic three-step pass — (1) demote all guardians
  of this application, (2) upsert each supplied relationship with `is_primary = false`, (3) promote exactly
  one. Anything else violates the generated `primary_guardian_key` unique index on a father→mother primary
  flip ([2026_06_27_000003_create_application_guardians_table.php:47](../../database/migrations/2026_06_27_000003_create_application_guardians_table.php)).
  Guardians a staff member added by hand are left alone.
- **Push ingest**: keeps replace-wholesale exactly as today. Its documented contract is that the payload
  list replaces the stored set, and the push caller relies on that to *remove* a wrongly attached parent.
  Breaking it would let a stranger survive to approval, where `preserveGuardianRelationships` issues a
  parent-portal access grant ([ApproveApplicationAction.php:172](../../app/Modules/Admissions/Actions/ApproveApplicationAction.php)).
- The branch is on ingest source, chosen by the caller — not sniffed from payload shape.

### Scores

Upsert by `(application, subject_code)`; a CRM null deletes any existing row for that subject.

### Skip rule (D4)

Application exists and `status != pending` → skip entirely, log at info with the student code and status,
count as skipped. Not an error. The existing action throws `ApplicationFrozenException` for this; catch it
per record.

### Command

`admissions:sync-crm-ne [--dry-run] [--limit=]`, implementing `Illuminate\Contracts\Console\Isolatable`,
registered in `AdmissionsServiceProvider` under `runningInConsole()` (precedent:
[NotificationServiceProvider.php:71](../../app/Modules/Notification/Providers/NotificationServiceProvider.php)).

- Isolation matters even with manual-only runs: two operators (or one impatient re-run) overlapping
  produces duplicate-key noise indistinguishable from genuinely bad records, and Phase 4's backfill is a
  second writer to the same rows. Same lock for both. It also makes the future scheduled mode safe by
  default rather than by memory.
- Each record runs in its **own** transaction with `lockForUpdate()` on the lookup; an exception rolls back
  that record only and the loop continues.
- `--dry-run` is a **no-write mode**, not an outer rollback: the service computes and returns the per-record
  diff without invoking the write path. An outer transaction would swallow the doc-type seeder's own nested
  transaction, hold row locks across the whole HTTP fetch, and still not contain cache/log/queue side effects.
- Exit code: non-zero when any record failed. Manual runs today, scheduler alerting later — either way a
  permanently failing record must not look like a healthy run.
- The summary prints elapsed run time, so the batching threshold (Phase 2) is measured, not guessed.
- Failure log: `student_code` + error class + offending **field name** only. Never the value —
  `national_id`, `email`, `phone` in `storage/logs` contradicts Goal 6.

Before the loop, seed `id_card_back` and `scholarship_certificate` idempotently by `code` via
`ApplicationDocumentTypeSyncService`.

## Related Code Files

- Create: `app/Modules/Admissions/Support/Crm/CrmApplicationMapper.php`
- Create: `app/Modules/Admissions/Support/Crm/CrmDocumentUrlValidator.php`
- Create: `app/Modules/Admissions/Services/CrmApplicationSyncService.php`
- Create: `app/Modules/Admissions/Console/SyncCrmApplicationsCommand.php`
- Modify: `app/Modules/Admissions/Actions/UpsertCrmApplicationAction.php` (match branch, whitelist, guardian source branch, document key + reconciliation, scores)
- Modify: `app/Modules/Admissions/Providers/AdmissionsServiceProvider.php` (register command)
- Modify: `app/Models/StudentApplication.php` (`$fillable` additions — with Phase 1)
- Create: `tests/Feature/Admissions/CrmApplicationMapperTest.php`
- Create: `tests/Feature/Admissions/CrmApplicationSyncTest.php`

## Implementation Steps

1. Target the module action (`UpsertCrmApplicationAction`). Do not touch the unreachable global trio — validation V2.
2. Write mapper tests first from the brainstorm mapping table: full record, all-null record, missing keys, bad date, non-numeric score, unknown gender, malformed `student_code`, `javascript:`/`data:`/`http:` URLs.
3. Write the mapper + URL validator until those pass. Keep the mapper pure.
4. Change the match resolution in `UpsertCrmApplicationAction` to the explicit branch, with `lockForUpdate()`. **Regression test first**: a pre-existing manual application with null `crm_admission_id` must be untouched by a full NE sync.
5. Extend the attribute whitelist and `$fillable`; add a persistence test asserting CRM columns are non-null after sync.
6. Implement guardian reconciliation for the NE source (demote → upsert → promote), leaving the push path untouched; add a push-path test asserting an omitted guardian is still removed.
7. Implement document keying, scoped `updateOrCreate`, and `ne:`-scoped reconciliation.
8. Write `CrmApplicationSyncService`: fetch → seed doc types → per-record transaction → result summary.
9. Write the command as a thin `Isolatable` wrapper: print the summary table, exit non-zero when `failed > 0`.
10. Sync tests: create, update, run-twice-no-duplicates, skip non-pending, partial failure, transcript priority (all four cases), URL rejected, stale document removed, guardian primary father→mother flip on a second run, staff-added guardian survives, blank/duplicate email accepted, `--dry-run` writes nothing.

## Success Criteria

- [ ] A pre-existing manual application (null `crm_admission_id`) is byte-identical after a full sync run.
- [ ] After sync, re-reading the row shows `crm_campus`, `crm_major`, `gpa`, `crm_paid_amount`, `last_synced_at` populated.
- [ ] Two consecutive runs produce identical row counts **and identical row contents** for applications, guardians, documents, and scores.
- [ ] A staff-added guardian on a pending synced application survives a sync; an omitted guardian on the push path is still removed.
- [ ] A father→mother primary flip on the second run succeeds without hitting the one-primary index.
- [ ] A document whose CRM URL becomes null is deleted; push-path and legacy documents are untouched.
- [ ] A `javascript:` or `data:` document URL never reaches the database.
- [ ] A crafted `student_code` cannot re-point another application's document rows.
- [ ] A record with missing `student_code` is skipped with a logged reason containing no PII; the batch continues; the command exits non-zero.
- [ ] Records with blank or duplicated email are ingested.
- [ ] `--dry-run` performs zero writes and holds no long transaction.
- [ ] Existing push-ingest tests green.

## Risk Assessment

- **Writing to the wrong class** (critical, was live in the previous draft): mitigated by step 1 and by targeting the module action everywhere.
- **Silent NULL columns** (critical): mitigated by a persistence assertion rather than a mapper-output assertion.
- **Match-key hijack** (critical): mitigated by the explicit branch plus a dedicated regression test.
- **Changing shared write path** (high): the guardian branch and match branch both touch the push path's class. Every change is gated behind an explicit source/key condition, and the existing push tests must stay green untouched.
- **Unique constraint collisions** (medium after Phase 1): `student_code` remains unique; a genuine duplicate is a real CRM data error and should fail that record loudly with a field-name-only message.
