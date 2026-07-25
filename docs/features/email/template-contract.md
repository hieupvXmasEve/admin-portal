---
title: Email Template Contract
status: current
type: runbook
scope: Database-backed email template contracts
last_verified: "2026-07-25"
owner: Notification Module
audience:
  - email administrators
  - developers
---

# Email Template Contract

## Purpose

Swinx has two active database-backed email template surfaces. Choose the
surface from the sending path; they are not interchangeable.

## Template surfaces

### General email templates

`App\Models\EmailTemplate` owns reusable and bulk-email templates in the
`email_templates` table. This surface supports named templates, a fixed list of
general template types, active versions, parent/child version history, optional
plain text, and arbitrary placeholders discovered from template content.

Current owners:

- `app/Models/EmailTemplate.php`
- `app/Services/EmailTemplateService.php`
- `app/Services/EmailTemplateVersioningService.php`
- `routes/web/systems.php`
- `routes/api/admin.php`

Use this surface for general email and bulk-email workflows that already
resolve `EmailTemplate`. Do not use it for Notification V2 finance messages.

### Notification email templates

`App\Modules\Notification\Models\NotificationEmailTemplate` owns the
`notification_email_templates` table. Each row is identified by an immutable
`campus_id` and `type_key`, with one row for a given campus/type pair. This
surface is consumed by Notification V2 email delivery.

Current owners:

- `app/Modules/Notification/Models/NotificationEmailTemplate.php`
- `app/Modules/Notification/Enums/NotificationTemplateTypeKey.php`
- `app/Modules/Notification/EmailContent/EmailContentRegistry.php`
- `app/Modules/Notification/Support/NotificationEmailTemplateProvisioner.php`
- `routes/web/notifications.php`

`NOTIFICATIONS_USE_DB_TEMPLATES` selects the DB-backed provider and defaults to
true through `config/notifications.php`. When disabled, only type keys that have
a legacy provider can render. DB-only type keys must not rely on that fallback.

## Placeholder contract

Both surfaces use `{{variable_name}}` substitution.

For Notification V2:

- the allowed variables for each type are defined only by
  `NotificationTemplateTypeKey::availableVariables()`;
- unknown placeholders are rejected when an admin saves a template;
- the HTML body is sanitized before storage;
- substituted HTML values are escaped;
- a missing campus/type row is a delivery error, not a signal to select another
  campus's template;
- subject substitution is plain text and HTML-body substitution is escaped.

Do not copy the variable lists into documentation or frontend constants. The
enum drives validation, the variable picker, previews, tests, and runtime
rendering.

General `EmailTemplate` extracts placeholders from the subject, HTML, and text
content. Its current renderer substitutes values directly, including in HTML.
Callers must therefore provide trusted or pre-sanitized values. Do not assume
it has Notification V2's escaped-value guarantee.

The shared substitution implementation is
`app/Modules/Notification/Concerns/HasTemplateRendering.php`.

## Campus and permission rules

Notification templates are campus-specific. Admin list/edit operations must
resolve the selected campus and must not permit changing a row's campus or type.
The policy defines separate view, edit, preview, and test-send permissions:

`app/Modules/Notification/Policies/NotificationTemplatePolicy.php`

General email configuration and templates use the existing system/admin
surfaces. Preserve their current route middleware rather than assuming the
Notification template policy applies to them.

## Safe edit workflow

1. Select the correct campus and template surface.
2. Edit only placeholders exposed by that surface.
3. Preview with representative values, including names containing quotes and
   angle brackets.
4. Save.
5. For Notification V2, use the rate-limited test-send action.
6. Confirm the resulting delivery and email log before editing another campus.

Notification template edits are activity-logged through `AuditableModel`.
General email templates also retain version relationships; use the existing
versioning service or admin action instead of overwriting historical rows by
hand.

## Focused validation

For Notification V2 template changes:

```bash
./scripts/dev.sh artisan test --compact \
  tests/Feature/Notification/Http/UpdateNotificationTemplateRequestTest.php
./scripts/dev.sh artisan test --compact \
  tests/Feature/Notification/Http/Api/NotificationTemplatePreviewTest.php
./scripts/dev.sh artisan test --compact \
  tests/Unit/Notification/Models/NotificationEmailTemplateRenderTest.php
```

For a type-key or provider change, also run the focused
`tests/Feature/Notification/EmailContent/` and
`tests/Unit/Notification/Enums/` tests.
