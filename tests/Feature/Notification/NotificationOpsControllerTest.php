<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Campus;
use App\Models\User;
use App\Modules\Notification\Actions\RetryDeliveryAction;
use App\Modules\Notification\Actions\RetryOutboxAction;
use App\Modules\Notification\Enums\NotificationDeliveryChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationMessageStatus;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Models\NotificationMessage;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        VerifyCsrfToken::class,
        PreventRequestForgery::class,
    ]);

    $this->authorizedUser = User::factory()->create();
    $this->unauthorizedUser = User::factory()->create();

    $this->campus = Campus::factory()->create();

    session(['current_campus_id' => $this->campus->id]);

    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturnUsing(function (int $userId, ?int $campusId) {
            if ($userId === $this->authorizedUser->id) {
                return ['view_any_notification'];
            }

            return [];
        });

    $this->app->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

describe('GET /admin/notifications/ops/outbox', function () {
    it('lists outbox entries for authorized user', function () {
        NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.outbox'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Notifications/Ops/Outbox')
            ->has('outbox')
            ->has('filters')
            ->has('statuses')
            ->has('eventNames')
        );
    });

    it('denies access to unauthorized user', function () {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.notifications.ops.outbox'));

        $response->assertForbidden();
    });

    it('denies access to guest', function () {
        $response = $this->get(route('admin.notifications.ops.outbox'));

        $response->assertRedirect(route('login'));
    });

    it('filters outbox by status', function () {
        NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Failed,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.outbox', ['status' => 'failed']));

        $response->assertOk();
    });

    it('filters outbox by event name', function () {
        NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.outbox', ['event_name' => 'manual.notification_sent']));

        $response->assertOk();
    });

    it('accepts search parameter', function () {
        NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.outbox', ['search' => 'test']));

        $response->assertOk();
    });

    it('accepts date range filters', function () {
        NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.outbox', [
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $response->assertOk();
    });

    it('accepts sorting parameters', function () {
        NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.outbox', [
                'sort' => 'event_name',
                'direction' => 'asc',
            ]));

        $response->assertOk();
    });
});

describe('GET /admin/notifications/ops/outbox/{id}', function () {
    it('shows outbox detail for authorized user', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.outbox.detail', $outbox));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Notifications/Ops/OutboxDetail')
            ->has('outbox')
        );
    });

    it('denies access to unauthorized user', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.notifications.ops.outbox.detail', $outbox));

        $response->assertForbidden();
    });
});

describe('POST /admin/notifications/ops/outbox/{id}/retry', function () {
    it('retries pending outbox entry', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $action = Mockery::mock(RetryOutboxAction::class);
        $action->shouldReceive('run')->once()->andReturn(true);
        $this->app->instance(RetryOutboxAction::class, $action);

        $response = $this->actingAs($this->authorizedUser)
            ->postJson(route('admin.notifications.ops.outbox.retry', $outbox));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Outbox entry queued for retry.',
            ]);
    });

    it('retries failed outbox entry', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Failed,
        ]);

        $action = Mockery::mock(RetryOutboxAction::class);
        $action->shouldReceive('run')->once()->andReturn(true);
        $this->app->instance(RetryOutboxAction::class, $action);

        $response = $this->actingAs($this->authorizedUser)
            ->postJson(route('admin.notifications.ops.outbox.retry', $outbox));

        $response->assertOk()
            ->assertJson(['success' => true]);
    });

    it('returns 422 for non-retriable status', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Dispatched,
        ]);

        $action = Mockery::mock(RetryOutboxAction::class);
        $action->shouldReceive('run')->once()->andReturn(false);
        $this->app->instance(RetryOutboxAction::class, $action);

        $response = $this->actingAs($this->authorizedUser)
            ->postJson(route('admin.notifications.ops.outbox.retry', $outbox));

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    });

    it('denies access to unauthorized user', function () {
        $outbox = NotificationEventOutbox::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'event_version' => 1,
            'occurred_at' => now(),
            'aggregate_type' => 'manual_notification',
            'aggregate_id' => (string) Str::uuid(),
            'campus_id' => $this->campus->id,
            'payload' => ['type_key' => 'test'],
            'status' => NotificationOutboxStatus::Pending,
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->postJson(route('admin.notifications.ops.outbox.retry', $outbox));

        $response->assertForbidden();
    });
});

describe('GET /admin/notifications/ops/deliveries', function () {
    it('lists deliveries for authorized user', function () {
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.deliveries'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Notifications/Ops/Deliveries')
            ->has('deliveries')
            ->has('filters')
            ->has('statuses')
            ->has('channels')
        );
    });

    it('denies access to unauthorized user', function () {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.notifications.ops.deliveries'));

        $response->assertForbidden();
    });

    it('filters deliveries by status', function () {
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Failed,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.deliveries', ['status' => 'failed']));

        $response->assertOk();
    });

    it('filters deliveries by channel', function () {
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.deliveries', ['channel' => 'realtime']));

        $response->assertOk();
    });

    it('accepts search and date range parameters', function () {
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Pending,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.deliveries', [
                'search' => 'test',
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $response->assertOk();
    });
});

describe('POST /admin/notifications/ops/deliveries/{id}/retry', function () {
    it('retries failed delivery', function () {
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        $delivery = NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Failed,
        ]);

        $action = Mockery::mock(RetryDeliveryAction::class);
        $action->shouldReceive('run')->once()->andReturn(true);
        $this->app->instance(RetryDeliveryAction::class, $action);

        $response = $this->actingAs($this->authorizedUser)
            ->postJson(route('admin.notifications.ops.deliveries.retry', $delivery));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Delivery queued for retry.',
            ]);
    });

    it('returns 422 for non-retriable status', function () {
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        $delivery = NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Sent,
        ]);

        $action = Mockery::mock(RetryDeliveryAction::class);
        $action->shouldReceive('run')->once()->andReturn(false);
        $this->app->instance(RetryDeliveryAction::class, $action);

        $response = $this->actingAs($this->authorizedUser)
            ->postJson(route('admin.notifications.ops.deliveries.retry', $delivery));

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    });

    it('denies access to unauthorized user', function () {
        $message = NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);
        $delivery = NotificationDelivery::create([
            'message_id' => $message->id,
            'channel' => NotificationDeliveryChannel::Realtime,
            'status' => NotificationDeliveryStatus::Pending,
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->postJson(route('admin.notifications.ops.deliveries.retry', $delivery));

        $response->assertForbidden();
    });
});

describe('GET /admin/notifications/ops/messages', function () {
    it('lists messages for authorized user', function () {
        NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.messages'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Notifications/Ops/Messages')
            ->has('messages')
            ->has('filters')
            ->has('statuses')
            ->has('typeKeys')
        );
    });

    it('denies access to unauthorized user', function () {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('admin.notifications.ops.messages'));

        $response->assertForbidden();
    });

    it('filters messages by status', function () {
        NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.messages', ['status' => 'active']));

        $response->assertOk();
    });

    it('filters messages by type_key', function () {
        NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.messages', ['type_key' => 'manual_notification']));

        $response->assertOk();
    });

    it('filters messages by read_status', function () {
        NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.messages', ['read_status' => 'unread']));

        $response->assertOk();
    });

    it('accepts sorting parameters', function () {
        NotificationMessage::create([
            'event_id' => (string) Str::uuid(),
            'event_name' => 'manual.notification_sent',
            'type_key' => 'manual_notification',
            'campus_id' => $this->campus->id,
            'recipient_user_id' => $this->authorizedUser->id,
            'title' => 'Test',
            'body' => 'Test body',
            'status' => NotificationMessageStatus::Active,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('admin.notifications.ops.messages', [
                'sort' => 'type_key',
                'direction' => 'asc',
            ]));

        $response->assertOk();
    });
});
