# Logging Guidelines

> How logging is done in this project.

---

## Overview

- Logging uses Laravel logging channels from `config/logging.php`.
- Default app logging is stack/daily.
- There are dedicated `api` and `email` daily channels.
- Newer code prefers structured context arrays instead of free-form strings.

---

## Log Levels

- `info`: successful domain milestones worth auditing (`Student action recorded`, `DNG webhook processed`).
- `warning`: expected-but-bad states that need attention but are not fatal (`orphan callback`, validation-like mismatches).
- `error`: unexpected failures and exceptions, especially in API paths.

Examples:

- Channel definitions: `config/logging.php`
- Domain info/warning logs: `app/Modules/Academic/Actions/RecordStudentActionAction.php`
- Structured webhook logs: `app/Modules/Finance/Dng/Services/DngWebhookService.php`
- API exception logging by severity: `app/Exceptions/ApiExceptionHandler.php`

---

## Structured Logging

- Prefer a stable message string plus context array.
- Include ids and contract-relevant fields (`event_id`, `student_id`, `action_log_id`, `dng_payment_request_id`, `status`).
- Use dedicated channels when the feature already has one (`api`, `email`).

Good baseline:

```php
Log::info('DNG webhook processed', [
    'event_id' => $event->id,
    'dng_payment_request_id' => $request->id,
    'event_type' => $eventType,
    'new_status' => $request->fresh()->status,
]);
```

---

## What to Log

- State transitions and important business events.
- Validation/authorization/API failures with request context.
- Async job or webhook processing decisions.
- Retryable mismatch/orphan states that operations teams may need to inspect.

---

## What NOT to Log

- Secrets, access tokens, CSRF tokens, or credentials.
- Full sensitive payloads unless the existing flow explicitly requires temporary debugging and the risk is understood.
- Large raw request bodies by default.

Current caution: webhook-related code stores/logs rich payload context in some places, so be conservative when adding new logs around payment callbacks.
