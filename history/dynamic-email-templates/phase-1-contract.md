# Phase Contract: Phase 1 - DB-Backed Render Parity

**Mode:** `standard_feature`
**Source:** [CONTEXT.md](CONTEXT.md) (D1-D9), [approach.md](approach.md), [phase-plan.md](phase-plan.md)
**Feasibility evidence:** [validation-report.md](validation-report.md) (READY w/ constraints Q1/Q3/Q5)
**Storage decision (locked 2026-05-16):** new dedicated table `notification_email_templates`, NOT extending `EmailTemplate`.

## Entry State

Observable truth before P1 starts:

- 4 finance email types still render from hard-coded HTML in
  [app/Modules/Notification/EmailContent/Types/](app/Modules/Notification/EmailContent/Types/).
- `EmailContentRegistry::resolve()` maps the 4 keys directly to those PHP classes
  ([EmailContentRegistry.php:14-31](app/Modules/Notification/EmailContent/EmailContentRegistry.php)).
- `email_templates` table exists for academic/identity templates only; no `campus_id`
  column, no finance type strings.
- `notification_email_templates` table does **not** exist.
- The 4 finance Actions (`SendPaymentRemindersAction`,
  `SendParentPaymentRemindersAction`, `SendDueItemRemindersAction`,
  `SendDueItemParentRemindersAction`) build `$contentData` arrays WITHOUT a
  `campus_id` key, though each Action does pass `campusId: $student->campus_id` to
  `EmailService::sendSingleEmail` separately.
- `HandleOutboxEventAction::buildEmailData()` does not inject `campus_id` into the
  `$data` passed to providers ([HandleOutboxEventAction.php:145-167](app/Modules/Notification/Actions/HandleOutboxEventAction.php)).
- No `config/notifications.php` feature-flag file.
- No `mews/purifier` in composer.json (deferred to P2).
- `.beads/` is empty.

## Exit State

Testable truth after P1 completes:

1. Table `notification_email_templates` exists with schema
   `(id, campus_id FK NOT NULL, type_key VARCHAR(64) NOT NULL, subject VARCHAR(500)
   NOT NULL, body_html MEDIUMTEXT NOT NULL, updated_by_user_id FK NULL, timestamps)`,
   UNIQUE `(campus_id, type_key)`, INDEX `(type_key)`.
2. For every row in `campuses`, exactly 4 `notification_email_templates` rows exist
   (one per `NotificationTemplateTypeKey` case), seeded from the current legacy HTML
   with heredoc `{$var}` rewritten to mustache `{{var}}`.
3. `App\Modules\Notification\Models\NotificationEmailTemplate` extends
   `AuditableModel`, uses `HasTemplateRendering` trait, has `render(array $data):
   array{subject:string, html:string, text:?string}`.
4. `App\Modules\Notification\Concerns\HasTemplateRendering` trait exists; both
   `NotificationEmailTemplate` and `EmailTemplate` use it (existing
   `EmailTemplate::renderContent()` private method replaced by trait method).
   `EmailTemplate::render()` public signature unchanged.
5. `App\Modules\Notification\Enums\NotificationTemplateTypeKey` backed enum with
   exactly 4 cases: `PaymentReminder = 'payment_reminder'`, `ParentPaymentReminder =
   'parent_payment_reminder'`, `DngPaymentPushed = 'dng_payment_pushed'`,
   `DngPaymentReceived = 'dng_payment_received'`. Values match the
   `EmailContentRegistry` keys exactly.
6. New `App\Modules\Notification\EmailContent\Contracts\EmailVariableSchema`
   interface with `availableVariables(): array<string, array{label: string, sample:
   mixed}>`. Each of the 4 legacy `*EmailContent.php` classes implements it.
7. New `App\Modules\Notification\EmailContent\Types\DbEmailContentProvider`
   implements `EmailContentProvider`, takes `NotificationTemplateTypeKey` in its
   constructor, reads `$data['campus_id']` per call, looks up
   `NotificationEmailTemplate::where('campus_id', $campusId)->where('type_key',
   $this->typeKey->value)->firstOrFail()`, renders via trait, memoises per
   `(typeKey, campusId)` in a class property for the request lifetime.
8. `EmailContentRegistry` binds the 4 keys to `DbEmailContentProvider` instances when
   `config('notifications.use_db_templates', true) === true`, and to the legacy
   classes when `false`. The registry caches resolved providers per type_key for the
   request.
9. `config/notifications.php` exists with `'use_db_templates' => env(
   'NOTIFICATIONS_USE_DB_TEMPLATES', true)`.
10. `HandleOutboxEventAction::buildEmailData()` injects
    `$data['campus_id'] = $envelope->campusId` before returning ([line 145-167](app/Modules/Notification/Actions/HandleOutboxEventAction.php)).
11. Each of the 4 finance Actions (`SendPaymentRemindersAction:49-56`,
    `SendParentPaymentRemindersAction`, `SendDueItemRemindersAction:67-74 + 132-139`,
    `SendDueItemParentRemindersAction`) adds `'campus_id' => $student->campus_id` to
    the `$contentData` array literal before the `subject()/htmlBody()` calls.
12. Golden-file test
    `tests/Feature/Notification/EmailContent/DbEmailContentParityTest.php` passes:
    for each `NotificationTemplateTypeKey` case, the seeded
    `NotificationEmailTemplate` rendered with a fixed `$data` fixture produces
    HTML+subject+text byte-equivalent to the legacy provider's output (whitespace
    collapse: `preg_replace('/\s+/', ' ', trim($html))`).
13. `./scripts/dev.sh test` is green; `./scripts/dev.sh artisan pint` is clean on
    all new/edited files.

## Demo (Observable Walkthrough)

Performed in staging by a developer (no admin UI yet):

1. Run `./scripts/dev.sh artisan migrate` -> `notification_email_templates` table
   appears with `campuses.count * 4` rows.
2. Run the parity test:
   `./scripts/dev.sh test tests/Feature/Notification/EmailContent/DbEmailContentParityTest.php`
   -> green for all 4 type_keys.
3. Trigger `SendPaymentRemindersAction` for one campus with one invoice fixture (via
   a manual `php artisan tinker` call). Capture the rendered HTML. Compare against
   the same call **before** P1 (captured pre-deploy). They match byte-for-byte after
   whitespace collapse.
4. Flip `NOTIFICATIONS_USE_DB_TEMPLATES=false` in `.env`, clear config cache, repeat
   step 3. Output is identical because legacy classes also still work.
5. Flip back to `true`. Confirm `php artisan db:seed --class=NotificationEmailTemplateSeeder`
   is idempotent (re-run = no-op, no exception).
6. Tail `storage/logs/laravel.log` -> no "Failed to render email content" warnings
   from `HandleOutboxEventAction::buildRenderedEmail()` during the test run.

## Stories

| Story | What Happens | Unlocks | Done When |
|---|---|---|---|
| **S1.1** | Migration creates `notification_email_templates` table with the exact schema in Exit #1. | S1.2, S1.3 | Migration runs cleanly; `\Schema::hasTable('notification_email_templates')` true; UNIQUE + INDEX present. |
| **S1.2** | Model `NotificationEmailTemplate` + trait `HasTemplateRendering` + enum `NotificationTemplateTypeKey`. Existing `EmailTemplate` refactored to use the trait (private `renderContent()` replaced; public `render()` unchanged). | S1.4, S1.6 | New unit test `NotificationEmailTemplateRenderTest` passes; existing `EmailTemplate` tests still pass; `vendor/bin/pint` clean. |
| **S1.3** | Variable schema: new `EmailVariableSchema` interface + the 4 legacy `*EmailContent.php` classes implement `availableVariables()` returning the allow-list inferred from current `$data['key']` reads (see [discovery.md](discovery.md) variable allow-list table). | S1.6, S1.7 | Unit test: for each type_key, the schema's keys match the actual `$data` keys touched in the corresponding `htmlBody()`. |
| **S1.4** | Seeder data migration: backfill `existing_campuses x 4 type_keys` rows; HTML copied verbatim from `*EmailContent.php::htmlBody()` with `{$var}` -> `{{var}}` rewrite. Idempotent (`firstOrCreate`). | S1.5, S1.6 | Migration runs from clean DB -> seeds N\*4 rows; second run -> zero new rows; subject contains `[Asia Việt Nam]` prefix for Asia campus. |
| **S1.5** | `DbEmailContentProvider` + rebind `EmailContentRegistry` behind feature flag `config('notifications.use_db_templates', true)`. Per-request memo by `(typeKey, campusId)`. New `config/notifications.php`. | S1.6 | Feature test: `EmailContentRegistry::resolve('payment_reminder')` returns `DbEmailContentProvider` when flag true, returns `PaymentReminderEmailContent` when false; `$provider->htmlBody(['campus_id'=>X,...])` produces correct rendered HTML; calling `htmlBody()` twice with same `campus_id` runs only 1 DB query. |
| **S1.6** | `campus_id` injection: `HandleOutboxEventAction::buildEmailData()` + 4 finance Actions (`Send*RemindersAction`) add `$contentData['campus_id'] = $student->campus_id` (or `$envelope->campusId` in the outbox case). | S1.7 | Code-walk doc in [validation-report.md](validation-report.md) Q2 evidence holds; no recipient-resolution or transaction-boundary changes; feature tests for each Action still pass. |
| **S1.7** | **Parity test:** `tests/Feature/Notification/EmailContent/DbEmailContentParityTest.php` with one frozen `$data` fixture per type_key; asserts `assertEquals(legacyHtml, dbHtml)` after whitespace normalisation. | Phase 1 exit | All 4 type_keys pass parity. CI runs the test. |

## Stories - Order & Dependency Check

- S1.1 -> S1.2 -> (S1.3 || S1.4) -> S1.5 -> S1.6 -> S1.7
- S1.3 and S1.4 are parallelisable (no file overlap: enum + schema interface +
  legacy class touches vs migration + seeder).
- S1.5 needs S1.2 (model) AND S1.4 (rows) to wire its lookup.
- S1.6 needs S1.5 (provider already swapped) so the new `$contentData['campus_id']`
  reaches the new provider.
- S1.7 needs everything; it is the exit-state proof.

Full story-to-bead mapping and file ownership map live in
[story-map-phase-1.md](story-map-phase-1.md).

## Out of Scope (P1)

- Admin UI for editing templates (`/admin/notification-templates`) - P2.
- `mews/purifier` install + HTML sanitization on save - P2 (no save surface in P1).
- Super-admin Policy + Gate - P2 (no write endpoints in P1).
- Send-test email endpoint - P2.
- Preview pane endpoint - P2.
- Deletion of legacy `*EmailContent.php` classes - deferred to feature-flag sunset
  cleanup (after P2 ships + 30 days production-clean reminders).
- TipTap toolbar customisation, "Insert variable" wiring - P2 (uses existing
  `EditorContent.vue`).
- Anything else in [CONTEXT.md](CONTEXT.md) `## Deferred Ideas`.

## Success Criteria

P1 is done when **all 13 Exit State assertions hold simultaneously** in a clean
checkout after `./scripts/dev.sh artisan migrate --fresh --seed && ./scripts/dev.sh test`.

## Pivot Signals (Revise Phase Plan)

Stop P1 and return to planning if any of these surface during execution:

- Render parity test fails for a type_key after best-effort whitespace
  normalisation AND the diff is semantic (not just whitespace). Implies the heredoc
  -> mustache rewrite missed an interpolation; planner reviews seeding rules.
- Memoising the provider lookup is insufficient (e.g. an Action iterates across
  campuses inside one batch). Planner revises the provider cache key or moves
  resolution into the loop.
- Trait extraction touches `EmailTemplate` consumers in ways that break academic
  template behaviour. Planner falls back to "copy 20 lines into the new model" and
  records the duplication debt.
- `HandleOutboxEventAction::buildEmailData()` requires more than a 1-line addition
  to inject `campus_id` (e.g. envelope shape mismatch). Planner re-scopes S1.6.

## Resolved Concerns (Carried From Validation)

- **Concern A - legacy class deletion timing:** legacy `*EmailContent.php` classes
  stay through P1 + P2. They are deleted in a **follow-up cleanup task** triggered
  by `config('notifications.use_db_templates')` being `true` for 30 days post-P2
  with zero parity-test failures and zero "rendering failed" log entries.
- **Concern B - feature-flag key + sunset:** key = `notifications.use_db_templates`,
  env var `NOTIFICATIONS_USE_DB_TEMPLATES`, default `true` after P1 parity green.
  Sunset condition: 30 days post-P2 production-clean. The cleanup task removes the
  flag check + the `config/notifications.php` entry + the 4 legacy classes + their
  registry fallback binding in one commit.
- **Concern C - filter on `/systems/email-templates`:** N/A. Finance templates live
  in `notification_email_templates`; the academic UI is untouched.
