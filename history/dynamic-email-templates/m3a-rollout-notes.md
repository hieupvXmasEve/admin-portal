# M3a Rollout Notes — test-send button → outbox emit (Option α)

**Date:** 2026-05-19
**Bead:** M3a
**Worker:** m3a-worker-01

## Implementation Summary

5 files touched:

1. `app/Modules/Notification/Actions/HandleOutboxEventAction.php` — added Option α short-circuit guard at top of `buildRenderedEmail`. When `payload.rendered_email.rendered_subject` + `rendered_html` are present, returns them verbatim without consulting `EmailContentRegistry`. Existing registry path unchanged for all other callers.
2. `app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php` — replaced `app(EmailService::class)->sendSingleEmail(...)` in `testSend` with `PublishDomainEventAction::run(DomainEventEnvelope)`. Response now returns `queued: true`. Removed `EmailService` import; added `PublishDomainEventAction`, `DomainEventEnvelope`, `CarbonImmutable`, `Str`.
3. `tests/Feature/Modules/Notification/HandleOutboxEventPreRenderedTest.php` — 3 unit test cases for the Option α short-circuit: pre-rendered bypasses registry, absent rendered_email falls through to registry, unsafe chars stored verbatim.
4. `tests/Feature/Notification/TestSendOutboxEmitTest.php` — parity test for full pipeline (endpoint → outbox → handler → delivery → job → EmailService spy). Two distinct sends per Critical Pattern #7. Unsafe chars per Critical Pattern #1.
5. `history/dynamic-email-templates/m3a-rollout-notes.md` — this file.

No migrations needed. No schema changes.

## Deploy Steps

```bash
# 1. Local dev — confirm tests pass (no migrate needed, no schema changes in M3a)
./scripts/dev.sh test tests/Feature/Notification tests/Feature/Modules/Notification

# 2. Pint style check
./scripts/dev.sh artisan pint --test \
  app/Modules/Notification/ \
  tests/Feature/Modules/Notification/ \
  tests/Feature/Notification/

# 3. Push and deploy
git push origin dev
# deploy per standard pipeline
```

## Staging Verification Checklist

- [ ] Open admin template editor → select a `payment_reminder` template → click test-send with a draft subject/body that differs from the persisted values → confirm email arrives with `[TEST]` prefix AND the **draft** body (not the persisted body).
- [ ] Confirm `notification_event_outbox` row exists with `event_name = notification.test_send_requested` and `payload.rendered_email.rendered_subject` starting with `[TEST] `.
- [ ] Confirm `notification_messages` row exists for the event_id.
- [ ] Confirm `notification_deliveries` row exists with `rendered_subject` and `rendered_html` populated from the payload (not registry-derived).
- [ ] Regression check: trigger a real Finance payment reminder (existing path) → verify delivery still works; `rendered_subject` comes from `EmailContentRegistry` (no `rendered_email` key in that payload, so registry path runs unchanged).
- [ ] Confirm `notification_deliveries.status = sent` after job processes.

## Rollback Steps

Revert exactly 2 production files (test files can stay — they will fail and signal rollback is active):

```bash
git checkout HEAD~1 -- \
  app/Modules/Notification/Actions/HandleOutboxEventAction.php \
  app/Modules/Notification/Http/Api/V1/Admin/NotificationTemplateController.php
```

After rollback the test-send endpoint returns to the old synchronous `EmailService::sendSingleEmail` path. The two new test files will fail (asserting `queued: true` and outbox rows that no longer exist) — expected; confirms rollback is active.

No database cleanup needed: `notification_event_outbox` rows with `event_name = notification.test_send_requested` are inert after rollback (the old controller never emits that event_name).

## M3b Unblock Gate

**M3a green on staging required before M3b kickoff.**

M3b wires Finance Actions (cross-module fan-out) to the same outbox-emit pattern. M3a proves the pattern end-to-end within the Notification module. If M3a staging verification fails, do not start M3b.
