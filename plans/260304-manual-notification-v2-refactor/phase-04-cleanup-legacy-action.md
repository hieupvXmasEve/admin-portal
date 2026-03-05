# Phase 04: Cleanup Legacy Action

**Status:** pending
**Effort:** 0.5h
**Dependencies:** Phase 03 + Validation Period

---

## Objective

Remove the legacy `SendManualNotificationAction` after confirming v2 mode is stable in production.

---

## Pre-Cleanup Checklist

Before proceeding with cleanup:

- [ ] V2 mode has been running in production for ≥ 1 week
- [ ] No `NOTIFICATION_V2_WRITE_MODE=off` rollbacks triggered
- [ ] Outbox processing metrics show healthy delivery rates
- [ ] No error reports related to manual notifications
- [ ] Stakeholder approval for legacy removal

---

## Task 4.1: Remove Feature Flag from Controller

**File:** `app/Http/Controllers/Web/Admin/NotificationController.php`

```diff
public function send(
    SendManualNotificationRequest $request,
-   SendManualNotificationAction $legacyAction,
    SendManualNotificationV2Action $v2Action
) {
    $data = $request->validated();

-   // Feature flag for rollback safety
-   if (config('notification.write_mode') === 'v2') {
-       $eventId = $v2Action->run($data);
-
-       return \App\Http\Responses\ApiResponse::success(
-           ['event_id' => $eventId],
-           [],
-           'Notification queued for delivery.'
-       );
-   }
-
-   // Legacy fallback
-   $legacyAction->execute($data);

+   $eventId = $v2Action->run($data);

    return \App\Http\Responses\ApiResponse::success(
-       null,
+       ['event_id' => $eventId],
        [],
-       'Notification sent successfully.'
+       'Notification queued for delivery.'
    );
}
```

Also remove the unused import:

```diff
- use App\Actions\Notification\SendManualNotificationAction;
```

---

## Task 4.2: Delete Legacy Action File

**File to delete:** `app/Actions/Notification/SendManualNotificationAction.php`

```bash
git rm app/Actions/Notification/SendManualNotificationAction.php
```

---

## Task 4.3: Clean Up Config (Optional)

If `write_mode` is no longer needed for other features:

**File:** `config/notification.php`

```diff
return [
    'v2_enabled' => (bool) env('NOTIFICATION_V2_ENABLED', false),

    'read_mode' => env('NOTIFICATION_V2_READ_MODE', 'legacy'),
-   'write_mode' => env('NOTIFICATION_V2_WRITE_MODE', 'v2'),
    // ...
];
```

**Note:** Only remove if no other code paths use `write_mode`. Search codebase first:

```bash
rg "config\('notification.write_mode'\)" --type php
```

---

## Task 4.4: Update Documentation

**File:** `docs/features/notification/README.md`

Add note about v2 architecture:

```markdown
## Manual Notification Send

Manual notifications use the v2 domain event architecture:

1. Controller validates request with campus_id
2. `SendManualNotificationV2Action` creates `DomainEventEnvelope`
3. `PublishDomainEventAction` writes to `notification_event_outbox`
4. Outbox worker processes via `HandleOutboxEventAction`
5. Recipients resolved and filtered by campus
6. `SendNotificationDeliveryJob` delivers via realtime channel

### Event Name

`manual.notification_sent`

### Payload Structure

```json
{
  "type_key": "manual_notification",
  "recipient_targets": [{"type": "student", "id": 123}],
  "channels": ["realtime"],
  "data": {
    "title": "...",
    "body": "...",
    "category": "system",
    "is_important": false,
    "action_url": "/...",
    "action_text": "View"
  }
}
```
```

---

## Task 4.5: Remove Legacy Action Tests (if any)

Search for tests referencing the legacy action:

```bash
rg "SendManualNotificationAction" tests/ --type php
```

Delete or update any tests that specifically test the legacy action.

---

## Rollback Plan

If issues are discovered after cleanup:

1. **Revert git commit** containing the deletion
2. **Add back feature flag** in controller
3. **Set env:** `NOTIFICATION_V2_WRITE_MODE=off`
4. **Investigate** outbox processing issues

---

## Acceptance Criteria

- [ ] Pre-cleanup checklist completed
- [ ] Legacy action file deleted
- [ ] Controller no longer references legacy action
- [ ] No import errors or missing class exceptions
- [ ] All tests pass
- [ ] Documentation updated
- [ ] Commit message: `refactor(notification): remove legacy SendManualNotificationAction`

---

## Files Changed

| File | Action |
|------|--------|
| `app/Actions/Notification/SendManualNotificationAction.php` | Delete |
| `app/Http/Controllers/Web/Admin/NotificationController.php` | Modify |
| `docs/features/notification/README.md` | Modify |
| `config/notification.php` | Modify (optional) |
