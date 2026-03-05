# Phase 01: Backend V2 Action + EventIntentMapper

**Status:** completed
**Effort:** 1.5h
**Dependencies:** Phase 01-03 of notification-module-domain-event-phase-1 (completed)

---

## Objective

Create `SendManualNotificationV2Action` that publishes a domain event to the outbox instead of directly creating notifications. Update `EventIntentMapper` to handle the new `manual.notification_sent` event.

---

## Task 1.1: Create SendManualNotificationV2Action

**File:** `app/Modules/Notification/Actions/SendManualNotificationV2Action.php`

```php
<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class SendManualNotificationV2Action
{
    public function __construct(
        private PublishDomainEventAction $publishAction
    ) {}

    /**
     * @param array{
     *   campus_id: int,
     *   notifiable_type: string,
     *   notifiable_ids: array<int>,
     *   title: string,
     *   message: string,
     *   category: string,
     *   is_important?: bool,
     *   action_url?: string|null,
     *   action_text?: string|null,
     * } $data
     */
    public function run(array $data): string
    {
        $eventId = (string) Str::ulid();
        
        $envelope = new DomainEventEnvelope(
            eventId: $eventId,
            eventName: 'manual.notification_sent',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'manual_notification',
            aggregateId: $eventId,
            campusId: (int) $data['campus_id'],
            actorUserId: auth()->id(),
            payload: [
                'type_key' => 'manual_notification',
                'recipient_targets' => $this->buildTargets(
                    (string) $data['notifiable_type'],
                    (array) $data['notifiable_ids']
                ),
                'channels' => ['realtime'],
                'data' => [
                    'title' => (string) $data['title'],
                    'body' => (string) $data['message'],
                    'category' => (string) $data['category'],
                    'is_important' => (bool) ($data['is_important'] ?? false),
                    'action_url' => $data['action_url'] ?? null,
                    'action_text' => $data['action_text'] ?? null,
                ],
            ],
        );

        $this->publishAction->run($envelope);

        return $eventId;
    }

    /**
     * @param array<int> $ids
     * @return array<int, array{type: string, id: int}>
     */
    private function buildTargets(string $type, array $ids): array
    {
        return array_map(
            fn (int $id): array => ['type' => $type, 'id' => $id],
            array_values(array_filter(array_map('intval', $ids)))
        );
    }
}
```

### Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| `channels: ['realtime']` only | Manual notifications are for instant alerts, not email |
| `aggregateId = eventId` | Manual notifications don't have a natural aggregate |
| Return `eventId` | Allows caller to track the notification for debugging |
| No transaction wrapper | Outbox pattern handles atomicity |

---

## Task 1.2: Update EventIntentMapper

**File:** `app/Modules/Notification/Support/EventIntentMapper.php`

**Change:** Add `manual.notification_sent` mapping in `resolveTypeKey()`.

```diff
private function resolveTypeKey(DomainEventEnvelope $event): ?string
{
    if (isset($event->payload['type_key']) && is_string($event->payload['type_key'])) {
        return $event->payload['type_key'];
    }

    return match ($event->eventName) {
        'finance.invoice_paid' => 'invoice_paid',
        'academic.enrollment_confirmed' => 'enrollment_confirmed',
+       'manual.notification_sent' => 'manual_notification',
        default => null,
    };
}
```

**Note:** The existing code already checks `$event->payload['type_key']` first, so the explicit match case is a fallback/documentation. The payload contains `type_key: 'manual_notification'` which takes precedence.

---

## Task 1.3: Create Unit Test

**File:** `tests/Unit/Notification/SendManualNotificationV2ActionTest.php`

```php
<?php

declare(strict_types=1);

use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Actions\SendManualNotificationV2Action;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Models\NotificationEventOutbox;

beforeEach(function () {
    $this->publishAction = Mockery::mock(PublishDomainEventAction::class);
    $this->action = new SendManualNotificationV2Action($this->publishAction);
});

it('creates domain event envelope with correct structure', function () {
    $this->actingAs(User::factory()->create());
    
    $capturedEnvelope = null;
    $this->publishAction
        ->shouldReceive('run')
        ->once()
        ->withArgs(function (DomainEventEnvelope $envelope) use (&$capturedEnvelope) {
            $capturedEnvelope = $envelope;
            return true;
        });

    $data = [
        'campus_id' => 1,
        'notifiable_type' => 'student',
        'notifiable_ids' => [100, 200],
        'title' => 'Test Notification',
        'message' => 'This is a test message',
        'category' => 'system',
        'is_important' => true,
        'action_url' => '/dashboard',
        'action_text' => 'View',
    ];

    $eventId = $this->action->run($data);

    expect($eventId)->toBeString()->not->toBeEmpty();
    expect($capturedEnvelope)->not->toBeNull();
    expect($capturedEnvelope->eventName)->toBe('manual.notification_sent');
    expect($capturedEnvelope->campusId)->toBe(1);
    expect($capturedEnvelope->payload['type_key'])->toBe('manual_notification');
    expect($capturedEnvelope->payload['recipient_targets'])->toBe([
        ['type' => 'student', 'id' => 100],
        ['type' => 'student', 'id' => 200],
    ]);
    expect($capturedEnvelope->payload['channels'])->toBe(['realtime']);
    expect($capturedEnvelope->payload['data']['title'])->toBe('Test Notification');
});

it('filters invalid notifiable ids', function () {
    $this->actingAs(User::factory()->create());
    
    $capturedEnvelope = null;
    $this->publishAction->shouldReceive('run')->once()
        ->withArgs(function (DomainEventEnvelope $envelope) use (&$capturedEnvelope) {
            $capturedEnvelope = $envelope;
            return true;
        });

    $data = [
        'campus_id' => 1,
        'notifiable_type' => 'user',
        'notifiable_ids' => [1, 0, -1, 2], // 0 and -1 should be filtered
        'title' => 'Test',
        'message' => 'Test',
        'category' => 'system',
    ];

    $this->action->run($data);

    expect($capturedEnvelope->payload['recipient_targets'])->toBe([
        ['type' => 'user', 'id' => 1],
        ['type' => 'user', 'id' => 2],
    ]);
});
```

---

## Acceptance Criteria

- [ ] `SendManualNotificationV2Action` created in `app/Modules/Notification/Actions/`
- [ ] Action publishes `manual.notification_sent` event to outbox
- [ ] `EventIntentMapper` handles the event (via payload `type_key` or explicit match)
- [ ] Unit test passes with mocked `PublishDomainEventAction`
- [ ] Code follows strict_types and return type hints
- [ ] No linter errors after `vendor/bin/pint --dirty`

---

## Files Changed

| File | Action |
|------|--------|
| `app/Modules/Notification/Actions/SendManualNotificationV2Action.php` | Create |
| `app/Modules/Notification/Support/EventIntentMapper.php` | Modify |
| `tests/Unit/Notification/SendManualNotificationV2ActionTest.php` | Create |
