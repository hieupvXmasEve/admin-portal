---
phase: 03
title: Job & Dispatch Layer (noti_v2 path)
status: completed
---

# Phase 03 — Job & Dispatch Layer

## Overview

Thread `campus_id` từ `NotificationMessage` qua `EmailChannelAdapter` → `EmailService` → `SendSingleEmailJob` → `EmailConfiguration::getActiveForCampus()`.

Luồng hiện tại (noti_v2):
```
SendNotificationDeliveryJob
  → EmailChannelAdapter::send($delivery)
      → loads $delivery->message (has campus_id)
      → EmailService::sendSingleEmail(...)       ← cần thêm $campusId
          → dispatch(SendSingleEmailJob(...))    ← cần thêm $campusId
              → EmailConfiguration::getActive() ← thay bằng getActiveForCampus($campusId)
```

## 1. EmailChannelAdapter

**File:** `app/Modules/Notification/Channels/EmailChannelAdapter.php`

Pass `$message->campus_id` vào `sendSingleEmail()`:

```php
public function send(NotificationDelivery $delivery): array
{
    $message = $delivery->message()->with('recipient')->firstOrFail();
    $recipient = $message->recipient;

    if (! $recipient || ! $recipient->email) {
        throw new RuntimeException('Notification recipient email is missing.');
    }

    $subject = $message->title ?: 'Notification';
    $content = $message->body ?: ($message->data['body'] ?? 'You have a new notification.');

    $emailLog = $this->emailService->sendSingleEmail(
        (string) $recipient->email,
        $subject,
        (string) $content,
        campusId: $message->campus_id,   // thread campus context
    );

    return [
        'provider_message_id' => null,
        'email_log_id' => (int) $emailLog->id,
    ];
}
```

## 2. EmailService::sendSingleEmail()

**File:** `app/Services/EmailService.php`

Add `?int $campusId = null` named param, pass to `SendSingleEmailJob`:

```php
public function sendSingleEmail(
    string $recipient,
    string $subject,
    string $content,
    ?EmailTemplate $template = null,
    array $attachments = [],
    ?User $sender = null,
    array $templateVariables = [],
    ?int $campusId = null,   // add
): EmailLog {
    // ...existing validation...

    dispatch(new SendSingleEmailJob(
        $emailLog,
        $htmlContent,
        $textContent,
        $attachments,
        $campusId,   // pass through
    ));
    // ...
}
```

Also update `getActiveConfiguration()`:
```php
public function getActiveConfiguration(?int $campusId = null): ?EmailConfiguration
{
    return EmailConfiguration::getActiveForCampus($campusId);
}
```

And update the rate-limit check in `validateBulkEmailParameters()` to pass campus context if available.

## 3. SendSingleEmailJob

**File:** `app/Jobs/SendSingleEmailJob.php`

Add `?int $campusId = null` constructor param, use in `handle()`:

```php
public function __construct(
    protected EmailLog $emailLog,
    protected string $htmlContent,
    protected ?string $textContent = null,
    protected array $attachments = [],
    protected ?int $campusId = null,   // add
) {
    $this->onQueue('emails');
}

// In handle():
$config = EmailConfiguration::getActiveForCampus($this->campusId);
```

## Out of Scope (Not Needed for noti_v2 path)

- `SendBulkEmailJob` — bulk mail không đi qua noti_v2, giữ nguyên hoặc update sau
- `SendPaymentRemindersAction` — gọi trực tiếp EmailService, campus context khác (handle riêng nếu cần)

## Todo

- [ ] `EmailChannelAdapter::send()` — pass `$message->campus_id` as named arg `campusId`
- [ ] `EmailService::sendSingleEmail()` — add `?int $campusId = null`, pass to job
- [ ] `EmailService::getActiveConfiguration()` — use `getActiveForCampus()`
- [ ] `SendSingleEmailJob` — add `?int $campusId`, update `handle()` to use `getActiveForCampus()`
- [ ] Run compile check: `./scripts/dev.sh npm run type-check` (frontend) + `./scripts/dev.sh composer test` (backend)
