<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notification\Actions\RetryDeliveryAction;
use App\Modules\Notification\Actions\RetryOutboxAction;
use App\Modules\Notification\Enums\NotificationDeliveryChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationMessageStatus;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Jobs\ProcessNotificationOutboxJob;
use App\Modules\Notification\Jobs\SendNotificationDeliveryJob;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

describe('RetryOutboxAction', function () {
    it('retries pending outbox entry and dispatches job', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => 1,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
            'attempts' => 1,
            'last_error' => 'Previous error',
        ]);

        $action = new RetryOutboxAction();
        $result = $action->run($outbox);

        expect($result)->toBeTrue();

        $outbox->refresh();
        expect($outbox->status)->toBe(NotificationOutboxStatus::Pending);
        expect($outbox->last_error)->toBeNull();

        Queue::assertPushed(ProcessNotificationOutboxJob::class);
    });

    it('retries failed outbox entry and dispatches job', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => 1,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Failed,
            'attempts' => 2,
            'last_error' => 'Previous error',
        ]);

        $action = new RetryOutboxAction();
        $result = $action->run($outbox);

        expect($result)->toBeTrue();

        $outbox->refresh();
        expect($outbox->status)->toBe(NotificationOutboxStatus::Pending);
        expect($outbox->last_error)->toBeNull();

        Queue::assertPushed(ProcessNotificationOutboxJob::class);
    });

    it('returns false for processing status', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => 1,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Processing,
            'attempts' => 1,
        ]);

        $action = new RetryOutboxAction();
        $result = $action->run($outbox);

        expect($result)->toBeFalse();
        Queue::assertNotPushed(ProcessNotificationOutboxJob::class);
    });

    it('returns false for dispatched status', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => 1,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Dispatched,
            'attempts' => 1,
        ]);

        $action = new RetryOutboxAction();
        $result = $action->run($outbox);

        expect($result)->toBeFalse();
        Queue::assertNotPushed(ProcessNotificationOutboxJob::class);
    });
});

describe('RetryDeliveryAction', function () {
    it('retries pending delivery and dispatches job', function () {
        $user = User::factory()->create();
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => 1,
            'recipient_user_id' => $user->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        $delivery = NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Pending,
            'attempts' => 1,
            'last_error' => 'Previous error',
        ]);

        $action = new RetryDeliveryAction();
        $result = $action->run($delivery);

        expect($result)->toBeTrue();

        $delivery->refresh();
        expect($delivery->status)->toBe(NotificationDeliveryStatus::Pending);
        expect($delivery->last_error)->toBeNull();

        Queue::assertPushed(SendNotificationDeliveryJob::class, function ($job) use ($delivery) {
            return $job->deliveryId === $delivery->id;
        });
    });

    it('retries failed delivery and dispatches job', function () {
        $user = User::factory()->create();
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => 1,
            'recipient_user_id' => $user->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        $delivery = NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Failed,
            'attempts' => 2,
            'last_error' => 'Previous error',
        ]);

        $action = new RetryDeliveryAction();
        $result = $action->run($delivery);

        expect($result)->toBeTrue();

        $delivery->refresh();
        expect($delivery->status)->toBe(NotificationDeliveryStatus::Pending);
        expect($delivery->last_error)->toBeNull();

        Queue::assertPushed(SendNotificationDeliveryJob::class, function ($job) use ($delivery) {
            return $job->deliveryId === $delivery->id;
        });
    });

    it('returns false for sent status', function () {
        $user = User::factory()->create();
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => 1,
            'recipient_user_id' => $user->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        $delivery = NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Sent,
            'attempts' => 1,
        ]);

        $action = new RetryDeliveryAction();
        $result = $action->run($delivery);

        expect($result)->toBeFalse();
        Queue::assertNotPushed(SendNotificationDeliveryJob::class);
    });

    it('returns false for skipped status', function () {
        $user = User::factory()->create();
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => 1,
            'recipient_user_id' => $user->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        $delivery = NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Skipped,
            'attempts' => 1,
        ]);

        $action = new RetryDeliveryAction();
        $result = $action->run($delivery);

        expect($result)->toBeFalse();
        Queue::assertNotPushed(SendNotificationDeliveryJob::class);
    });
});
