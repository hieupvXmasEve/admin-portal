# Phase 03 - Channel Adapters and Realtime Security

## Context Links

- Spec: `specs/notification_module_domain_event.md`
- Existing realtime docs: `docs/features/notification/realtime_setup_guide.md`

## Overview

- Priority: High
- Status: in-progress
- Objective: implement email/realtime adapters behind module contracts and fix cross-actor/cross-campus exposure risk.

## Key Insights

- Current realtime auth checks only numeric user id channel; insufficient for strict campus isolation.
- Existing `EmailService` + `EmailLog` can be reused to reduce operational risk.

## Requirements

- Functional:
    - channel adapter interface with `email` and `realtime` implementations
    - delivery status updates (`pending|sent|failed|skipped`)
    - enforce strict campus isolation with exception allowlist
- Non-functional:
    - provider-agnostic adapter contracts
    - bounded retries per channel

## Architecture

- Email adapter delegates to `EmailService` and stores `email_log_id` when available.
- Realtime adapter broadcasts private channel bound to canonical user + campus rules.
- Policy allowlist exceptions: `system/security` and explicit global announcements only.

## Related Code Files

- Create:
    - `app/Modules/Notification/Channels/Contracts/ChannelAdapter.php`
    - `app/Modules/Notification/Channels/EmailChannelAdapter.php`
    - `app/Modules/Notification/Channels/RealtimeChannelAdapter.php`
    - `app/Modules/Notification/Jobs/SendNotificationDeliveryJob.php`
- Modify:
    - `routes/channels.php`
    - `app/Events/NotificationBroadcast.php` (or replace usage with module event)
    - `resources/js/composables/useRealtimeNotifications.ts`
    - `resources/js/lib/echo.ts` (if channel auth params/config updates needed)

## Implementation Steps

1. Implement adapter contract and wire channel selection from delivery records.
2. Integrate email delivery with `EmailService` and capture `email_log_id`.
3. Emit realtime payload from message record (minimal safe fields only).
4. Tighten channel auth to validate actor and campus boundary.
5. Apply exception allowlist logic for global/system-security notifications.

## Todo List

- [ ] Implement adapter interface and registry
- [x] Build email adapter with log correlation
- [x] Build realtime adapter with private channel naming
- [x] Harden `routes/channels.php` authorization
- [x] Update frontend realtime subscription to new channel key

## Success Criteria

- Email delivery records include send/fail state and correlation IDs.
- Realtime events reach correct user and deny cross-campus listeners.
- No cross-actor/cross-campus leak in channel auth tests.

## Risk Assessment

- Risk: breaking existing realtime listeners.
- Mitigation: feature flag for new realtime path and temporary dual subscription window.

## Security Considerations

- Treat channel auth as hard security gate, not convenience check.
- Keep payload fields minimal; avoid PII blobs.

## Next Steps

- Continue to Phase 04 dual-read API/UI cutover.

## Unresolved Questions

- None.
