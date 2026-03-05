# Research: Notification Module Refactoring Requirements

**Date:** 2026-03-04
**Status:** Completed
**Context:** Transitioning from legacy `App\Models\Notification` + `App\Actions\Notification\*` to new v2 domain event architecture in `App\Modules\Notification\*`

---

## 1. Current Legacy Architecture

### Flow: Controller → Action → Model

```
NotificationController::send()
    ↓
SendManualNotificationAction::execute()
    ↓
DB::transaction() { 
    foreach notifiable_ids:
        $notifiable->notifications()->create([...])  // Legacy App\Models\Notification
}
    ↓
Model Boot Event triggers broadcast (NotificationBroadcast)
```

### Key Components

| File | Responsibility |
|------|----------------|
| `NotificationController.php` | Renders form, handles send, target search |
| `SendManualNotificationAction.php` | Creates notifications directly in transaction |
| `App\Models\Notification` | Legacy polymorphic notification model |
| `SendManualNotificationRequest.php` | Validates notifiable_type, notifiable_ids, title, message |

### Legacy Data Flow

1. Controller calls `SendManualNotificationAction::execute($validated)`
2. Action wraps ALL notification creates in single `DB::transaction`
3. For each target, resolves model class via match expression
4. Calls `$notifiable->notifications()->create()` directly
5. Model boot event triggers broadcast synchronously

### Problems with Legacy

- **Transaction coupling**: Sends notification inside business transaction
- **No idempotency**: Duplicate requests create duplicate notifications
- **No campus isolation**: No `campus_id` on notification or validation
- **Polymorphic sprawl**: Uses MorphTo relationship, harder to query
- **Synchronous broadcast**: Boot event fires broadcast immediately

---

## 2. New v2 Domain Event Architecture

### Flow: Controller → PublishDomainEvent → Outbox → Handler → Intent → Persist → Dispatch

```
Controller::send()
    ↓
PublishDomainEventAction::runAfterCommit(envelope)  // Non-blocking
    ↓
Outbox Worker (async)
    ↓
HandleOutboxEventAction::run(outbox)
    ↓
EventIntentMapper::map(envelope) → NotificationIntent
    ↓
PolicyResolver::decide(intent) → allow_channels[]
    ↓
RecipientResolver::resolve(targets, campus_id) → resolved_user_ids[]
    ↓
PersistIntentAction::run() → NotificationMessage + NotificationDelivery
    ↓
SendNotificationDeliveryJob::dispatch(delivery_id)
    ↓
ChannelAdapter (email/realtime)
```

### Key Components

| Component | File | Responsibility |
|-----------|------|----------------|
| DomainEventEnvelope | `Domain/Contracts/DomainEventEnvelope.php` | Immutable event contract with campus_id |
| NotificationIntent | `Domain/Contracts/NotificationIntent.php` | Describes who/what/how to notify |
| PublishDomainEventAction | `Actions/PublishDomainEventAction.php` | Writes to outbox, supports afterCommit |
| HandleOutboxEventAction | `Actions/HandleOutboxEventAction.php` | Orchestrates full pipeline |
| EventIntentMapper | `Support/EventIntentMapper.php` | Maps event_name → NotificationIntent |
| RecipientResolver | `Support/RecipientResolver.php` | Resolves targets → user_ids with campus check |
| PolicyResolver | `Policies/PolicyResolver.php` | Decides allowed channels, enforces strict isolation |
| PersistIntentAction | `Actions/PersistIntentAction.php` | Creates Message + Deliveries idempotently |

### Idempotency Guarantees

| Layer | Unique Constraint |
|-------|-------------------|
| Outbox | `event_id` |
| Message | `(event_id, type_key, recipient_user_id)` |
| Delivery | `(message_id, channel)` |

---

## 3. RecipientResolver: Campus Isolation

### Code Analysis

```php
// RecipientResolver::resolve(array $targets, ?int $campusId)
```

**Resolution Logic:**

1. **User targets**: Checks `campusUserRoles()->where('campus_id', $campusId)->exists()`
2. **Student targets**: Checks `$student->campus_id !== $campusId`
3. **Lecturer targets**: Checks `$lecture->campus_id !== $campusId`

**Isolation Behavior:**

- If `campusId` is provided and target doesn't belong to that campus → `reason: 'campus_mismatch'`
- Unresolved targets are logged via `NotificationAuditLogger` and metrics incremented
- **Only resolved_user_ids proceed** to message creation

**Config-Driven Strictness:**

```php
// config/notification.php
'campus' => [
    'strict_isolation' => true,  // Require campus_id on events
    'global_event_allowlist' => ['system.security', 'system.announcement.global'],
],
```

`PolicyResolver::decide()` blocks events without `campus_id` unless in allowlist.

---

## 4. Required Changes for SendManualNotificationAction Refactoring

### New Action: Use PublishDomainEventAction

**Current (legacy):**
```php
public function execute(array $data): void
{
    DB::transaction(function () use ($data) {
        foreach ($notifiableIds as $id) {
            $notifiable->notifications()->create([...]);  // ❌ Inside transaction
        }
    });
}
```

**Target (v2):**
```php
public function run(array $data): void
{
    $campusId = $data['campus_id'];  // Required
    $targets = $this->buildTargets($data['notifiable_type'], $data['notifiable_ids']);
    
    $envelope = new DomainEventEnvelope(
        eventId: (string) Str::ulid(),
        eventName: 'manual.notification_sent',
        eventVersion: 1,
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'manual_notification',
        aggregateId: (string) Str::ulid(),
        campusId: $campusId,
        actorUserId: auth()->id(),
        payload: [
            'type_key' => 'manual_notification',
            'recipient_targets' => $targets,
            'channels' => ['realtime'],  // or ['email', 'realtime']
            'data' => [
                'title' => $data['title'],
                'body' => $data['message'],
                'category' => $data['category'],
                'action_url' => $data['action_url'],
                'action_text' => $data['action_text'],
            ],
        ],
    );
    
    app(PublishDomainEventAction::class)->run($envelope);  // Outside transaction
}

private function buildTargets(string $type, array $ids): array
{
    return array_map(fn ($id) => ['type' => $type, 'id' => $id], $ids);
}
```

### EventIntentMapper Update Required

Add mapping for `manual.notification_sent`:

```php
// EventIntentMapper::resolveTypeKey()
return match ($event->eventName) {
    'finance.invoice_paid' => 'invoice_paid',
    'academic.enrollment_confirmed' => 'enrollment_confirmed',
    'manual.notification_sent' => $event->payload['type_key'] ?? 'manual_notification',  // ← ADD
    default => null,
};
```

---

## 5. Required Controller Changes

### Current Controller Issues

1. **No campus_id in request/action** - Missing campus isolation
2. **No campus_id in searchTargets** - Searches across all campuses
3. **Action called synchronously** - Should be async via outbox

### Required Changes

#### 5.1 SendManualNotificationRequest - Add campus_id

```php
public function rules(): array
{
    return [
        'campus_id' => ['required', 'integer', 'exists:campuses,id'],  // ← ADD
        'notifiable_type' => ['required', 'string', 'in:student,user,lecturer'],
        'notifiable_ids' => ['required', 'array', 'min:1'],
        // ... existing rules
    ];
}

public function passedValidation(): void
{
    // Verify user has access to this campus (if not using `app('campus')`)
}
```

#### 5.2 Controller::send() - Use v2 Action

```php
public function send(
    SendManualNotificationRequest $request,
    SendManualNotificationV2Action $action  // New action
) {
    $data = $request->validated();
    $data['campus_id'] = $data['campus_id'] ?? app('campus')->id;  // Fallback
    
    $action->run($data);
    
    return ApiResponse::success(null, [], 'Notification queued for delivery.');
}
```

#### 5.3 Controller::searchTargets() - Add Campus Filter

```php
public function searchTargets(Request $request)
{
    $campusId = $request->input('campus_id', app('campus')->id);
    
    switch ($type) {
        case 'student':
            $results = Student::query()
                ->active()
                ->where('campus_id', $campusId)  // ← ADD
                // ... existing filters
                ->limit(20)
                ->get([...]);
            break;
            
        case 'lecturer':
            $results = Lecture::query()
                ->active()
                ->where('campus_id', $campusId)  // ← ADD
                // ... existing filters
                ->limit(20)
                ->get([...]);
            break;
            
        case 'user':
            // User campus check via campusUserRoles relationship
            $results = User::query()
                ->whereHas('campusUserRoles', fn ($q) => $q->where('campus_id', $campusId))
                // ... existing filters
                ->limit(20)
                ->get([...]);
            break;
    }
}
```

#### 5.4 Controller::sendForm() - Pass Campus Context

```php
public function sendForm(): Response
{
    $this->authorize('send_manual_notification');
    
    $campusId = app('campus')->id;
    $programs = Program::query()
        ->where('campus_id', $campusId)  // ← ADD campus filter
        ->select('id', 'name')
        ->get();

    return Inertia::render('Admin/Notifications/Send', [
        'categories' => NotificationCategory::options(),
        'programs' => $programs,
        'currentCampusId' => $campusId,  // ← Pass to frontend
    ]);
}
```

---

## 6. Campus Isolation Requirements for Send UI

### Backend Requirements

| Component | Campus Isolation |
|-----------|------------------|
| SendManualNotificationRequest | Require `campus_id` field |
| searchTargets API | Filter all queries by `campus_id` |
| Programs dropdown | Filter by `campus_id` |
| SendManualNotificationV2Action | Pass `campus_id` in envelope |

### Campus ID Acquisition Pattern

**Recommended approach (per codebase convention):**
```php
$campusId = app('campus')->id;  // From middleware-bound singleton
// OR
$campusId = session('current_campus_id');  // Session-based
```

Both patterns exist in codebase. `app('campus')` is more common in recent controllers.

### Frontend Requirements

1. **Hidden field** or auto-inject `campus_id` from props
2. **Target search** must pass `campus_id` to `search-targets` API
3. **Programs dropdown** already scoped if backend filters

### v2 Module Enforcement

`PolicyResolver` blocks notifications without `campus_id` unless event is in `global_event_allowlist`. Manual notifications are NOT global → `campus_id` is **mandatory**.

---

## 7. Summary: Migration Checklist

### New Files to Create

| File | Purpose |
|------|---------|
| `app/Modules/Notification/Actions/SendManualNotificationV2Action.php` | V2 action using outbox pattern |
| (optional) `app/Modules/Notification/Domain/Events/ManualNotificationSentEvent.php` | Typed event class |

### Files to Modify

| File | Changes |
|------|---------|
| `SendManualNotificationRequest.php` | Add `campus_id` validation |
| `NotificationController.php` | Use v2 action, add campus filters to searchTargets |
| `EventIntentMapper.php` | Add mapping for `manual.notification_sent` |

### Config Updates

Ensure `config/notification.php` has:
```php
'write_mode' => 'v2',  // Enable v2 writes (or dual)
```

### Feature Flag Strategy

During cutover:
```php
if (config('notification.write_mode') === 'v2') {
    $v2Action->run($data);
} else {
    $legacyAction->execute($data);
}
```

---

## Unresolved Questions

1. **Dual-write period**: Should manual notifications dual-write to both legacy and v2 during pilot?
2. **Email channel for manual**: Should manual notifications support email channel or realtime only?
3. **Programs filter**: Does the current UI use programs to filter students, and should it be campus-scoped?
