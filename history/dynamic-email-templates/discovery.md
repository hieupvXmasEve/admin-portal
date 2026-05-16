# Discovery - Dynamic Email Templates

**Date:** 2026-05-16
**Source of truth for decisions:** [CONTEXT.md](CONTEXT.md)

## Architecture Snapshot

- **Hot path for finance email rendering:** `EmailContentRegistry::resolve($type_key)` is the
  single seam. Used by:
  - `HandleOutboxEventAction::buildRenderedEmail()` ([app/Modules/Notification/Actions/HandleOutboxEventAction.php:113](app/Modules/Notification/Actions/HandleOutboxEventAction.php)) -
    main outbox path. Already wrapped in try/catch with a logged fallback.
  - 4 finance actions:
    - [app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php](app/Modules/Finance/Actions/Operations/SendPaymentRemindersAction.php) (resolves
      `payment_reminder` once before the invoice loop).
    - [app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php](app/Modules/Finance/Actions/Operations/SendParentPaymentRemindersAction.php) (`payment_reminder`).
    - [app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php](app/Modules/Finance/Actions/Operations/SendDueItemRemindersAction.php) (`payment_reminder`).
    - [app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php](app/Modules/Finance/Actions/Operations/SendDueItemParentRemindersAction.php)
      (`parent_payment_reminder`).
- **Channel adapter:** [RenderedEmailChannelAdapter.php](app/Modules/Notification/Channels/RenderedEmailChannelAdapter.php) reads `rendered_subject` + `rendered_html` already
  stored on `NotificationDelivery` and dispatches via `EmailService::sendSingleEmail` with
  `campus_id` context. No change needed once rendering is DB-backed.
- **Variable enrichment:** `HandleOutboxEventAction::buildEmailData()`
  ([HandleOutboxEventAction.php:145](app/Modules/Notification/Actions/HandleOutboxEventAction.php)) hydrates `$data` from `DngPaymentRequest`:
  `student_name, student_code, semester_code, program_name, invoice_code,
  amount_formatted, due_date`. It does **not** currently include `campus_id` in `$data`.

## Variable Allow-list (Inferred From Existing Providers)

| Type key | Variables consumed |
|---|---|
| `payment_reminder` | `student_name, student_code, semester_code, invoice_code, balance_formatted, due_date` |
| `parent_payment_reminder` | `parent_name` (default "Quý Phụ Huynh"), `student_name, student_code, semester_code, invoice_code, balance_formatted, due_date` |
| `dng_payment_pushed` | `student_name, student_code, semester_code, program_name, invoice_code, amount_formatted` (fallback `amount`), `due_date` |
| `dng_payment_received` | `student_name, student_code, semester_code, amount_formatted, paid_at` |

Each variable carries a Vietnamese-formatted value already (balance in `1.234.567 VND`,
due_date in `d/m/Y`). The current providers HTML-escape all substitutions defensively.

## Stack Constraints

- **PHP:** ^8.3 (composer.json). CLAUDE.md mentions 8.4; planning targets 8.3 to stay safe.
- **Framework:** Laravel ^13.0, Inertia v3 (`inertiajs/inertia-laravel:3.x-dev`).
- **Vue 3.5** + TypeScript + Tailwind 4 + reka-ui (shadcn-vue primitives) + Pinia.
- **WYSIWYG already installed:** TipTap v3 (`@tiptap/vue-3`, `@tiptap/starter-kit`,
  extensions for table/link/color/highlight/text-align/text-style/list/blockquote/code-block).
  [resources/js/components/EditorContent.vue](resources/js/components/EditorContent.vue) is an existing email-aware editor
  with toolbar, `commonVariables` prop, `insertVariable()` chip behaviour, and an
  `emailMode` style preset matching email-client CSS. **Reuse as-is.**
- **Audit log:** [app/Models/AuditableModel.php](app/Models/AuditableModel.php) uses Spatie activitylog with
  campus-aware log name. Drop-in for D5.
- **Sanitizer:** no HTML sanitizer in composer.json. `mews/purifier` or
  `voku/anti-xss` would have to be added; alternatively TipTap output is already
  constrained by its extension set.

## What Already Exists (Reusable)

- **`EmailTemplate` model** ([app/Models/EmailTemplate.php](app/Models/EmailTemplate.php)):
  - Columns: `name, type, subject, html_content, text_content, variables (JSON),
    is_active, version, parent_id, description`.
  - `render($vars)` does `{{var}}` substitution.
  - `extractVariables()` parses `{{var}}` from subject+html+text.
  - `validateVariables()` reports missing keys.
  - Extends `AuditableModel` -> audit log free.
  - Pre-existing types: `welcome, grade_notification, course_registration, academic_hold,
    enrollment_confirmation, assessment_deadline, system_announcement, reminder, custom`.
  - **Not campus-scoped today.**
- **API admin CRUD** ([routes/api/admin.php:57+](routes/api/admin.php)):
  `index, store, getTypes, validateTemplate, getByType, show, update, destroy,
  createVersion, preview`. Backed by `EmailTemplateController`.
- **Web admin pages** ([routes/web/systems.php:16+](routes/web/systems.php)) under
  `/systems/email-templates` -> `Web\EmailConfigurationController` rendering Inertia pages:
  [resources/js/pages/Admin/EmailTemplate/Index.vue](resources/js/pages/Admin/EmailTemplate/Index.vue),
  [Create.vue](resources/js/pages/Admin/EmailTemplate/Create.vue),
  [Edit.vue](resources/js/pages/Admin/EmailTemplate/Edit.vue),
  plus `components/TemplatePreviewModal.vue`.
- **Email send infra:** `EmailService::sendSingleEmail(to, subject, html, campusId)` is the
  test-send target; production path already uses it.

## Gaps vs Goal

1. `email_templates.campus_id` column does not exist (only `email_configurations.campus_id`
   was added on 2026-04-17).
2. `EmailTemplate::TYPE_*` does not include the four finance `type_key` values.
3. `EmailContentRegistry` resolves to PHP classes; no `DbEmailContentProvider`.
4. `HandleOutboxEventAction::buildEmailData()` does not inject `campus_id` into `$data`;
   nor do the 4 finance Actions explicitly pass `campus_id` to the provider call.
5. The existing admin UI allows delete + version + free type strings - not aligned with
   **D4** ("always exactly 1 active per (campus_id, type_key); no delete/disable").
6. No backend-owned variable allow-list contract per type_key; `EmailTemplateController::validateTemplate`
   exists but validates ad-hoc.
7. No "Send test email" admin action; the existing `EmailConfigurationController` does
   have a `test` action but it is for SMTP config, not for template preview.
8. No HTML sanitizer in composer; TipTap output goes straight to DB and email body
   today.
9. Super-admin gating not in place for these template rows (the existing CRUD is admin-area
   but does not differentiate roles per D7).

## Summary

- Integration seam (`EmailContentRegistry`) is clean and already covered by a try/catch
  fallback. Swapping its bound provider for a DB-backed one is local.
- `EmailTemplate` model has 80% of what is needed; planning will decide between extending
  it (preferred) versus a new `notification_templates` table.
- TipTap WYSIWYG with variable picker is already built in `EditorContent.vue`.
- AuditableModel + Spatie activitylog cover D5 for free.
- The biggest gaps: campus scoping on `email_templates`, a per-type variable allow-list
  contract, a "1 active per (campus, type), edit-only" lifecycle, HTML sanitization on
  save, and a Super-Admin-only Policy.
- 4 finance Actions resolve the registry **once outside the loop**, but per-invoice
  recipients can sit in different campuses -> the DB provider must look up by
  `$data['campus_id']` per call (cache by (type_key, campus_id) per request).
