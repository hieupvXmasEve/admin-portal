---
title: Email Operations
status: current
type: runbook
scope: Email transport and delivery operations
last_verified: "2026-07-25"
owner: Platform Team
audience:
  - platform operators
  - email administrators
  - support engineers
---

# Email Operations

## Runtime surfaces

Swinx can send through the default Laravel mail configuration and through
campus-aware database SMTP configurations.

Database SMTP credentials are encrypted by `App\Models\EmailConfiguration`.
For a campus send, the service selects the active configuration for that
campus, then falls back to an active global configuration. Activating one
configuration deactivates the others in the same scope.

Owners:

- `config/mail.php`
- `app/Models/EmailConfiguration.php`
- `app/Services/SmtpConfigurationService.php`
- `app/Modules/Notification/Support/SmtpEmailTransport.php`
- `routes/api/admin.php`
- `routes/web/systems.php`

## Initial configuration

Set the default Laravel mail values in `.env`, using a provider credential
appropriate to the environment:

```env
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=587
MAIL_USERNAME=<smtp-user>
MAIL_PASSWORD=<smtp-secret>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<sender-address>
MAIL_FROM_NAME="${APP_NAME}"
QUEUE_CONNECTION=redis
```

Then create or test campus-specific SMTP configuration from the system email
configuration page when the campus should not use the global transport.
Credentials must be entered through the model/service path so encryption
metadata and integrity checks are maintained.

## Queue requirements

Email and Notification V2 delivery are asynchronous. Production must run:

- the application queue worker;
- the Laravel scheduler;
- Redis when `QUEUE_CONNECTION=redis`.

Notification outbox processing has a five-minute scheduler safety net in
`routes/console.php`. The push path may dispatch sooner, but the safety net is
still required.

## Deployment verification

Check configuration and optionally send a test email:

```bash
./scripts/dev.sh artisan email:test-configuration --smtp-only
./scripts/dev.sh artisan email:test-configuration --to=operator@example.edu
```

Check credential integrity:

```bash
./scripts/dev.sh artisan email:audit-security
```

Inspect the staff surfaces:

- system email configuration;
- system email history;
- admin email monitoring;
- Notification Ops deliveries for Notification V2 messages.

The route owners are `routes/web/systems.php`,
`routes/web/email-monitoring.php`, and `routes/web/notifications.php`.

## Routine maintenance

Dry-run log retention before deleting records:

```bash
./scripts/dev.sh artisan email:cleanup-logs --dry-run
```

Credential rotation also supports a dry run:

```bash
./scripts/dev.sh artisan email:rotate-credentials --dry-run
```

Review the command help before a live maintenance run:

```bash
./scripts/dev.sh artisan help email:cleanup-logs
./scripts/dev.sh artisan help email:rotate-credentials
```

The cleanup and rotation commands are not scheduled by
`routes/console.php`; operations must schedule them externally or run them
under an approved maintenance procedure.

## Failure diagnosis

### SMTP connection fails

1. Run `email:test-configuration --smtp-only`.
2. Confirm host, port, encryption mode, credentials, and provider account
   policy.
3. If a database configuration is involved, inspect its `last_tested_at` and
   `test_result`.
4. Run `email:audit-security` if decryption or integrity is suspected.
5. Clear configuration cache after changing environment values:

```bash
./scripts/dev.sh artisan config:clear
```

### Mail remains queued

1. Check that queue workers are running against the configured connection.
2. Inspect failed jobs and application logs.
3. Check Notification Ops when the message originated from Notification V2.
4. Confirm Redis connectivity when Redis is the queue backend.

### Notification email fails to render

Check the campus ID, template type key, and presence of the matching
`notification_email_templates` row. Then inspect the delivery's `last_error`.
Unknown template variables should be corrected in the template editor, not
silently supplied by a sender.

### Delivery is sent but not received

Use the email log and provider response before retrying. Confirm the recipient,
sender-domain policy, SPF/DKIM/DMARC, provider suppression lists, and spam
placement. A retry should be based on a failed or missing provider handoff, not
only on an absent inbox message.

## Safety

- Never print SMTP passwords or encrypted credential backups in logs.
- Do not use live student addresses for connectivity testing.
- Prefer the stored retry action for a failed delivery; avoid creating a second
  business notification by hand.
- Keep template rules in `docs/features/email/template-contract.md`.
