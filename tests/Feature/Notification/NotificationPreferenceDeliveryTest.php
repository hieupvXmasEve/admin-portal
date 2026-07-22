<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserEmailPreference;
use App\Modules\Notification\Actions\HandleOutboxEventAction;
use App\Modules\Notification\Channels\EmailChannelAdapter;
use App\Modules\Notification\Channels\RealtimeChannelAdapter;
use App\Modules\Notification\Channels\RenderedEmailChannelAdapter;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Jobs\SendNotificationDeliveryJob;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Support\SmtpEmailTransport;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('suppresses opted-out email delivery while retaining the realtime notification', function () {
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
        'notification.campus.strict_isolation' => false,
    ]);
    Queue::fake();

    $user = User::factory()->create();
    UserEmailPreference::setUserPreference(
        $user->id,
        UserEmailPreference::TYPE_ALL,
        false,
        UserEmailPreference::FREQUENCY_NEVER,
    );

    app(DomainEventPublisher::class)->publish(new DomainEvent(
        name: 'manual.notification_sent',
        deduplicationKey: 'manual.notification_sent:preference:'.$user->id,
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'manual_notification',
        aggregateId: 'preference-'.$user->id,
        campusId: null,
        actorUserId: null,
        payload: [
            'type_key' => 'manual_notification',
            'channels' => ['email', 'realtime'],
            'recipient_targets' => [['type' => 'user', 'id' => $user->id]],
            'data' => ['title' => 'Preference test', 'body' => 'Body'],
        ],
    ));

    app(HandleOutboxEventAction::class)->run(NotificationEventOutbox::query()->sole());

    $email = NotificationDelivery::query()->where('channel', 'email')->sole();
    $realtime = NotificationDelivery::query()->where('channel', 'realtime')->sole();

    expect($email->status)->toBe(NotificationDeliveryStatus::Skipped)
        ->and($email->last_error)->toBe('suppressed_by_preference')
        ->and($realtime->status)->toBe(NotificationDeliveryStatus::Pending);
});

it('persists an external recipient as an email-only idempotent delivery', function () {
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
        'notification.campus.strict_isolation' => false,
    ]);
    Queue::fake();

    $event = new DomainEvent(
        name: 'manual.notification_sent',
        deduplicationKey: 'manual.notification_sent:external-recipient',
        occurredAt: CarbonImmutable::now(),
        aggregateType: 'manual_notification',
        aggregateId: 'external-recipient',
        campusId: null,
        actorUserId: null,
        payload: [
            'type_key' => 'manual_notification',
            'channels' => ['email', 'realtime'],
            'recipient_targets' => [['type' => 'email', 'email' => 'external@example.test']],
            'data' => ['title' => 'External recipient', 'body' => 'Text-only body'],
        ],
    );

    app(DomainEventPublisher::class)->publish($event);
    app(HandleOutboxEventAction::class)->run(NotificationEventOutbox::query()->sole());

    $delivery = NotificationDelivery::query()->with('message')->sole();

    expect($delivery->channel->value)->toBe('email')
        ->and($delivery->message->recipient_user_id)->toBeNull()
        ->and($delivery->message->recipient_email)->toBe('external@example.test')
        ->and($delivery->message->recipient_key)->toBe('email:external@example.test');

    $transport = Mockery::mock(SmtpEmailTransport::class);
    $transport->shouldReceive('send')
        ->once()
        ->withArgs(function (NotificationDelivery $delivery, string $recipient): bool {
            return $recipient === 'external@example.test';
        })
        ->andReturn(['provider_message_id' => null, 'email_log_id' => null]);
    app()->instance(SmtpEmailTransport::class, $transport);
    app()->forgetInstance(EmailChannelAdapter::class);
    app()->forgetInstance(RenderedEmailChannelAdapter::class);

    app(SendNotificationDeliveryJob::class, ['deliveryId' => $delivery->id])->handle(
        app(EmailChannelAdapter::class),
        app(RenderedEmailChannelAdapter::class),
        app(RealtimeChannelAdapter::class),
    );

    expect($delivery->fresh()->status)->toBe(NotificationDeliveryStatus::Sent);
});
