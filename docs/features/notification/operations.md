---
title: Notification Operations
status: current
type: runbook
scope: Notification outbox and delivery operations
last_verified: "2026-07-25"
owner: Notification Module
audience:
  - platform operators
  - administrators
  - developers
---

# Notification Operations

## Runtime flow

Notification V2 uses an outbox and per-channel deliveries:

```text
domain action
  -> notification_event_outbox
  -> intent and policy resolution
  -> canonical recipient resolution
  -> notification_messages
  -> notification_deliveries
  -> email or realtime adapter
```

The outbox preserves the business event independently from provider delivery.
The message is the recipient-facing record; each delivery records one channel
attempt. Do not collapse those layers when diagnosing a failure.

Executable owners:

- `app/Modules/Notification/Actions/PublishDomainEventAction.php`
- `app/Modules/Notification/Actions/HandleOutboxEventAction.php`
- `app/Modules/Notification/Actions/PersistIntentAction.php`
- `app/Modules/Notification/Support/EventIntentMapper.php`
- `app/Modules/Notification/Support/RecipientResolver.php`
- `app/Modules/Notification/Jobs/SendNotificationDeliveryJob.php`

## Configuration

`config/notification.php` owns the V2 modes, retry budgets, campus isolation,
and allowed delivery channels. Relevant environment values include:

```env
NOTIFICATION_V2_ENABLED=true
NOTIFICATION_V2_WRITE_MODE=v2
NOTIFICATION_OUTBOX_PUSH_ENABLED=true
```

Additional outbox and delivery retry values are environment-overridable through
that config file. Treat the config as the inventory; do not maintain a second
list in deployment templates.

The scheduler runs:

```text
notifications:process-outbox --limit=100
```

every five minutes as a safety net. The push path may dispatch immediately, but
production still needs the scheduler and queue workers.

## Campus and recipient rules

Recipient resolution produces canonical `users.id` recipients for staff,
students, and lecturers. It rejects missing identities and campus mismatches.
External email targets are allowed only on the email channel. Duplicate
recipient keys are collapsed before persistence.

Campus isolation is strict except for the code-owned global-event allowlist in
`config/notification.php`. Do not add a global event only to make a campus
mismatch disappear.

Email preference suppression creates a skipped delivery. Realtime delivery is
not currently suppressed by `UserEmailPreference`.

## Realtime delivery

Realtime notifications broadcast the `NotificationCreated` event to the
private channel:

```text
notify.<campus_id>.<canonical_user_id>
```

Authorization is owned by `routes/channels.php`. The callback verifies both
canonical user identity and campus membership. A campus ID of zero is reserved
for permitted global events.

The provider is selected by `BROADCAST_CONNECTION` in
`config/broadcasting.php`. Configure the matching Ably, Pusher, or Reverb
credentials and the frontend `VITE_*` values required by that provider.

## Operations pages

Notification Ops is under `admin.notifications.ops.*` and requires
`view_any_notification`. Its queries are scoped to the selected campus.

Use:

- Outbox for event mapping, recipient-resolution, and dispatch failures;
- Messages for recipient-facing persistence and read state;
- Deliveries for channel attempts, provider errors, and retries.

The controller and routes are:

- `app/Modules/Notification/Http/Web/Admin/NotificationOpsController.php`
- `routes/web/notifications.php`

## Retry procedure

1. Identify the failed layer.
2. Correct the underlying configuration, template, identity, or provider issue.
3. Retry a failed/pending outbox entry when intent creation did not complete.
4. Retry a failed/pending delivery when the message exists and only the channel
   attempt failed.
5. Confirm the existing row advances; do not create a duplicate business
   event.

The retry actions reject statuses that are not retryable:

- `app/Modules/Notification/Actions/RetryOutboxAction.php`
- `app/Modules/Notification/Actions/RetryDeliveryAction.php`

## Failure diagnosis

### Outbox remains pending

- Confirm queue workers and the scheduler are running.
- Run a bounded manual safety-net pass if approved:

```bash
./scripts/dev.sh artisan notifications:process-outbox --limit=100
```

- Inspect `last_error`, attempts, and event mapping.

### No message is created

Inspect unresolved-recipient audit events, campus identity, policy channel
decisions, and `EventIntentMapper`. An event with no mapped intents can be
dispatched without creating a message.

### Email delivery fails

Inspect the delivery error, campus email template row, and SMTP configuration.
Follow `docs/features/email/operations.md` and
`docs/features/email/template-contract.md`.

### Realtime delivery succeeds but the client sees nothing

Check the broadcast provider, queue worker, private-channel authentication,
canonical user ID, campus ID, and the frontend subscription. Do not change the
channel to a public channel for diagnosis.

## Focused validation

```bash
./scripts/dev.sh artisan test --compact \
  tests/Feature/Notification/NotificationOpsControllerTest.php
./scripts/dev.sh artisan test --compact \
  tests/Feature/Notification/RetryActionsTest.php
./scripts/dev.sh artisan test --compact \
  tests/Feature/Modules/Notification/SendNotificationDeliveryJobRoutingTest.php
```

Run the targeted student notification and preference tests when changing
recipient-facing APIs or suppression.
