# Approach - Dynamic Email Templates

**Date:** 2026-05-16
**Mode:** `standard_feature`
**Decisions:** [CONTEXT.md](CONTEXT.md) D1-D9
**Facts:** [discovery.md](discovery.md)

## Why `standard_feature` (not smaller / larger)

- Smaller modes rejected:
  - `direct_task` / `small_change`: touches DB schema, registry binding, FE admin page,
    sanitizer dependency, seeder, and policy - well over 3 files, multi-layer.
  - `spike`: no single yes/no question gates the path; the integration seam is already
    proven by the existing try/catch in `HandleOutboxEventAction::buildRenderedEmail()`.
- Larger mode (`high_risk_feature`) rejected:
  - Hard-to-reverse blast radius is **localised** to one registry binding plus a feature
    flag on the admin page. Backwards path is "rebind PHP providers and remove the
    Admin link" - hours, not days.
  - No external/security-critical surface beyond the WYSIWYG HTML stored in the DB; that
    risk is bounded by HTML sanitization (one named dependency).
  - Bounded user surface (one admin screen, 4 types, super-admin only).

Phase plan beats an epic map here: only two observable milestones (parity swap, then
authoring surface). Epic-shaped capability/risk areas would be artificial.

## Recommended Approach

1. **Storage = new dedicated table `notification_email_templates` (NOT extending `EmailTemplate`).**
   - User revision 2026-05-16: `EmailTemplate` serves academic/identity (welcome,
     grade_notification, etc.) with versioning + delete + free-string type. Those
     semantics conflict with D4 (always 1 active, no delete), D5 (overwrite, no
     versioning), and D8 (closed enum of 4 type_keys).
   - Schema (`database/migrations/2026_05_*_create_notification_email_templates_table.php`):
     ```text
     notification_email_templates
     ----------------------------------------------
     id                 BIGINT PK
     campus_id          BIGINT FK campuses NOT NULL
     type_key           VARCHAR(64) NOT NULL  -- enum-checked at app layer:
                                              -- payment_reminder | parent_payment_reminder
                                              -- | dng_payment_pushed | dng_payment_received
     subject            VARCHAR(500) NOT NULL
     body_html          MEDIUMTEXT NOT NULL
     updated_by_user_id BIGINT FK users NULL
     created_at         TIMESTAMP
     updated_at         TIMESTAMP

     UNIQUE (campus_id, type_key)
     INDEX (type_key)
     ```
   - Model: `App\Modules\Notification\Models\NotificationEmailTemplate extends AuditableModel`.
   - Render: extract `EmailTemplate::renderContent()` (the 20-line `str_replace('{{key}}', val, ...)`
     loop) into a trait `App\Modules\Notification\Concerns\HasTemplateRendering` shared
     by both `NotificationEmailTemplate` and `EmailTemplate`. Alternative (copy 20
     lines into the new model) is acceptable if extracting the trait turns out to
     require touching `EmailTemplate` consumers - planning leaves the trait-vs-copy
     micro-decision to the bead author.
   - Type enum guard: `App\Modules\Notification\Enums\NotificationTemplateTypeKey`
     (php 8.1 backed enum, `cases()` returns the 4 registry keys). FormRequest +
     migration seeder iterate it.
   - Rejected alternative (extending `EmailTemplate`): would force `whereNotIn('type',
     [...])` filters on every existing consumer of `EmailTemplate`, pollute the
     `/systems/email-templates` admin UI with finance rows, and leave unused
     `version`/`parent_id`/`is_active` columns on the row. Separation of concerns
     wins over a 20-line render reuse.

2. **Variable allow-list contract = `availableVariables()` on each provider.**
   - Extend `EmailContentProvider` interface (or add a sibling `EmailVariableSchema`
     interface implemented by the same classes) returning
     `array<string, array{label: string, sample: mixed}>`.
   - Single source of truth consumed by:
     - `StoreEmailTemplateRequest` validator -> rejects unknown `{{var}}` per D8.
     - FE Edit page -> "Insert variable" picker labels + Preview sample data.
   - Hard-coded provider classes are kept *as variable-schema providers*. Their
     `subject()` / `htmlBody()` / `textBody()` become unused for the finance types after
     parity is verified; renaming/extraction can happen post-launch.

3. **Replace registry binding with a single `DbEmailContentProvider`.**
   - Reads the active `NotificationEmailTemplate` row by `(type_key, campus_id)` from
     `$data['campus_id']` and runs the rendering helper from `HasTemplateRendering`.
   - Memoises lookups per `(type_key, campus_id)` in a request-scoped property so the
     existing "resolve once outside the loop" pattern in `SendPaymentRemindersAction`
     (and siblings) does not turn into N+1 when the loop iterates invoices.
   - **Feature flag (rollout safety net):** the provider binding is wrapped in a
     `config('notifications.use_db_templates', true)` check. Default `true` after P1
     parity test green. If the flag is flipped `false` (env override
     `NOTIFICATIONS_USE_DB_TEMPLATES=false`), the registry falls back to the legacy
     hard-coded providers in `EmailContent\Types\*EmailContent.php` for all 4 keys.
     Flag sunset condition: after **P2 ships AND one full billing cycle of reminders
     (~30 days) passes cleanly in production** the flag + the legacy classes are
     deleted together in a follow-up cleanup task. Until then, both code paths coexist.
   - `HandleOutboxEventAction::buildEmailData()` and the 4 finance Actions need a
     one-line change to put `campus_id` into the `$data` array passed to the provider.

4. **Sanitizer = `mews/purifier` (HTMLPurifier wrapper) with a small allow-list.**
   - Allow-list = the TipTap extension set we ship: `p, br, strong, em, u, table, thead,
     tbody, tr, th, td, ul, ol, li, blockquote, hr, a[href|target|rel], img[src|alt],
     h1..h3, style on `style=` for inline CSS used in current emails.
   - Sanitize on save (server) so any path that writes to the row is protected, not
     just the WYSIWYG flow.
   - Rejected alternative `voku/anti-xss`: lighter but its allow-list config is less
     ergonomic for inline-style emails.

5. **Admin surface = a NEW Inertia page family, fully isolated from
   `/systems/email-templates`.**
   - Path: `/admin/notification-templates`. Lists exactly the four type_keys per campus,
     edit-only. Backed by `notification_email_templates`, not `email_templates`, so the
     existing academic-template page (Concern C from validation) needs **zero** filter
     changes.
   - Reuses [resources/js/components/EditorContent.vue](resources/js/components/EditorContent.vue) for body, with
     `commonVariables` populated from the BE allow-list endpoint per type_key.
   - Subject = plain `<Input>` with the same variable-chip toolbar.
   - Preview pane = server-rendered via `POST /admin/notification-templates/preview`
     using the BE sample data; this guarantees parity with production rendering.
   - "Send test email" = `POST /admin/notification-templates/{id}/test-send` to the
     logged-in admin's email through `EmailService::sendSingleEmail` with a
     `[TEST]` subject prefix.
   - Gate: `notification-templates.manage` permission bound to Super Admin role
     via Policy + middleware.
   - Existing `/systems/email-templates` page family stays unchanged (it serves
     academic/identity templates with delete + versioning UI).

6. **Seeding = a data migration that backfills `notification_email_templates` rows.**
   - Source HTML = current strings copied verbatim from the four
     `EmailContent\Types\*EmailContent.php` `htmlBody()` blocks. The legacy code uses
     heredoc interpolation (`{$studentName}`); the seeder rewrites those to
     `{{student_name}}` mustache placeholders matching the variable allow-list.
   - For every row in `campuses`, insert one `notification_email_templates` row per
     `NotificationTemplateTypeKey::cases()` -> `existing_campuses x 4` rows total.
   - Defensive: `INSERT ... ON CONFLICT (campus_id, type_key) DO NOTHING` (or
     `firstOrCreate()`) so re-running the migration is a no-op.
   - Legacy `EmailContent\Types\*EmailContent.php` classes are retained as the
     variable-schema source (via `availableVariables()`) and as the feature-flag
     fallback target. Deletion deferred until the flag sunsets (P2 + 30 days).

## Risk Map

| Component | Level | Reason | Proof Needed |
|---|---|---|---|
| Render parity post-swap | **MEDIUM** | The seeded rows must render byte-identical HTML (whitespace-normalised) for in-flight reminders to look unchanged. Render mechanism = same `str_replace('{{key}}', val, ...)` loop, extracted into `HasTemplateRendering` trait. | Golden-file test: for each type_key, render the seeded `NotificationEmailTemplate` with a fixed `$data` fixture and `assertEquals($legacy->htmlBody($data), $db->render($data)['html'])` after whitespace collapse. |
| Campus lookup in finance loops | **MEDIUM** | The 4 finance Actions resolve the provider **once outside the per-invoice loop**. Different invoices can have different campus IDs (cross-campus admin actions). | Discovery story in Phase 1 confirms each call site passes campus context via `$data['campus_id']`; per-request memoizer caches per `(type, campus)` to avoid N+1. |
| HTML sanitizer choice + email-client compatibility | **MEDIUM** | Sanitizer must keep inline `style=` for table emails or layout collapses in Gmail/Outlook. | Add `mews/purifier`, configure allow-list, snapshot-test the four seeded templates round-trip through `purify()` and `assertEquals(before, after)`. |
| Variable allow-list reaching FE | **LOW** | Mismatch between BE allow-list and FE picker labels causes "unknown variable" rejects from save. | One JSON endpoint `GET /admin/notification-templates/variables/{type_key}` consumed by FE on page load; integration test asserts both halves agree. |
| Super-admin gating | **LOW** | Plain Policy + Gate per Swinx convention (`docs/rules/security.md`); existing patterns cover this. | Feature test: non-super-admin gets 403 on edit/test-send routes. |
| Pending outbox events during deploy | **LOW** | `NotificationDelivery` rows already store rendered_subject/rendered_html before send. Old in-flight rows keep their pre-rendered HTML. | Confirmed by reading `RenderedEmailChannelAdapter::send()` - it reads stored fields, does not re-resolve providers. No proof needed; spot-check in validation. |
| WYSIWYG output structural drift | **LOW** | TipTap may emit slightly different HTML (e.g. `<p><br></p>` vs `<br>`) than the hand-written legacy HTML. | Acceptable - first save by admin will normalise; seeded rows remain raw legacy HTML and only change when admin edits. |

## Likely File / Order Boundaries

Order = bottom-up; each layer's tests pass before the next layer starts.

1. **DB layer**
   - `database/migrations/2026_05_*_create_notification_email_templates_table.php` (CREATE TABLE).
   - `database/migrations/2026_05_*_seed_notification_email_templates.php` (data
     migration: backfill `existing_campuses x 4 type_keys` rows from legacy HTML).
2. **Domain layer**
   - New `app/Modules/Notification/Models/NotificationEmailTemplate.php`
     (extends `AuditableModel`, uses `HasTemplateRendering` trait).
   - New `app/Modules/Notification/Concerns/HasTemplateRendering.php`
     (trait extracted from `EmailTemplate::renderContent()`; `EmailTemplate` is
     updated to consume the same trait so the rendering logic has one home).
   - New `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php`
     (backed enum: `payment_reminder`, `parent_payment_reminder`, `dng_payment_pushed`,
     `dng_payment_received`).
   - New `app/Modules/Notification/EmailContent/Contracts/EmailVariableSchema.php`
     (sibling to `EmailContentProvider`) with
     `availableVariables(): array<string, array{label: string, sample: mixed}>`.
   - Update each of the 4 `EmailContent\Types\*EmailContent.php` to implement
     `EmailVariableSchema::availableVariables()`. They keep their existing
     `subject()`/`htmlBody()`/`textBody()` methods through the feature-flag sunset
     window so the legacy fallback path remains intact.
3. **Provider swap**
   - New `app/Modules/Notification/EmailContent/Types/DbEmailContentProvider.php`.
   - [app/Modules/Notification/EmailContent/EmailContentRegistry.php](app/Modules/Notification/EmailContent/EmailContentRegistry.php) - bind the 4 keys to
     the new provider (with feature-flag escape via env / config to fall back to legacy
     classes if needed during rollout).
   - [app/Modules/Notification/Actions/HandleOutboxEventAction.php](app/Modules/Notification/Actions/HandleOutboxEventAction.php) `buildEmailData()` -
     inject `campus_id`.
   - 4 finance Actions - inject `campus_id` per-iteration into `$data`.
4. **Sanitizer**
   - `composer require mews/purifier`
   - `config/purifier.php` definition for the email allow-list.
   - Wire into the new `Admin\NotificationTemplateController::update()` action.
5. **HTTP layer (Notification module, not the existing `EmailConfiguration` controller)**
   - `app/Modules/Notification/Http/Web/Admin/NotificationTemplateController.php`
     (Inertia index + edit).
   - `app/Modules/Notification/Http/Api/Admin/NotificationTemplateController.php`
     (variables endpoint + preview + test-send).
   - `app/Modules/Notification/Http/Requests/UpdateNotificationTemplateRequest.php`
     (FormRequest with unknown-variable rejection).
   - `app/Modules/Notification/Policies/EmailTemplatePolicy.php` (super-admin only).
   - New `routes/web/notification-templates.php` and additions to
     `routes/api/admin.php`.
6. **FE (Inertia + Vue)**
   - `resources/js/pages/Admin/NotificationTemplate/Index.vue`
   - `resources/js/pages/Admin/NotificationTemplate/Edit.vue` (uses `EditorContent.vue`)
   - `resources/js/pages/Admin/NotificationTemplate/components/PreviewPane.vue`
   - `resources/js/pages/Admin/NotificationTemplate/components/TestSendButton.vue`
   - `route(...)` Ziggy helpers regenerated by `php artisan ziggy:generate`.

## Validating Questions (For `khuym:validating`)

These need feasibility evidence before execution beads are created:

1. **Render parity test:** does the seeded HTML (with `{{var}}` placeholders restored
   from the legacy interpolated strings) render byte-equivalent to the current
   provider output for a fixed fixture, across all 4 type_keys? *Proof: golden-file
   tests on a small data fixture committed in `tests/Feature/Notification/`*.
2. **Campus context flow:** for each of the 4 finance Actions, can `campus_id` be
   added to `$data` before the provider call without changing the recipient resolution
   or transaction boundaries? *Proof: a code-walk doc enumerating the per-Action
   change.*
3. **Sanitizer compatibility:** does `mews/purifier` with the email allow-list keep all
   inline `style=` attributes used in the four legacy bodies after one round-trip?
   *Proof: snapshot test `assertEquals(beforePurify, afterPurify)` for each seeded
   body.*
4. **Variable endpoint shape:** can the FE EditorContent.vue `commonVariables` prop be
   populated from a single GET response per type without a refactor? *Proof: a 5-line
   composable demo in a sandbox page.*

## Relevant Learnings

- `history/learnings/critical-patterns.md` does not exist yet (first feature). No
  external constraints to import.

## Out Of Scope

- Anything in [CONTEXT.md](CONTEXT.md) `## Deferred Ideas`.
- Touching the existing `/systems/email-templates` page family or its consumers.
- Renaming or deleting the legacy `EmailContent\Types\*EmailContent.php` classes; that
  cleanup is its own follow-up after Phase 2 verification.
