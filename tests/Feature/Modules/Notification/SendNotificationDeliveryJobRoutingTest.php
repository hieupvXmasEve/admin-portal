<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Notification\Channels\EmailChannelAdapter;
use App\Modules\Notification\Channels\RenderedEmailChannelAdapter;
use App\Modules\Notification\Enums\NotificationDeliveryChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationMessageStatus;
use App\Modules\Notification\Jobs\SendNotificationDeliveryJob;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function makeDelivery(string $channel, ?string $renderedSubject = null): NotificationDelivery
{
    $user = User::factory()->create();

    $message = NotificationMessage::create([
        'event_id' => (string) Str::uuid(),
        'event_name' => 'finance.dng_payment_pushed',
        'type_key' => 'dng_payment_pushed',
        'campus_id' => null,
        'recipient_user_id' => $user->id,
        'title' => 'Test',
        'body' => 'Test body',
        'data' => [],
        'status' => NotificationMessageStatus::Active,
    ]);

    return NotificationDelivery::create([
        'message_id' => $message->id,
        'channel' => $channel,
        'status' => NotificationDeliveryStatus::Pending,
        'rendered_subject' => $renderedSubject,
        'rendered_html' => $renderedSubject !== null ? '<p>HTML</p>' : null,
        'queued_at' => now(),
    ]);
}

it('routes to RenderedEmailChannelAdapter when rendered_subject is set', function () {
    $delivery = makeDelivery('email', '[Asia Việt Nam] Test subject');

    $renderedAdapter = Mockery::mock(RenderedEmailChannelAdapter::class);
    $renderedAdapter->shouldReceive('send')->once()->with(Mockery::on(fn ($d) => $d->id === $delivery->id))->andReturn(['email_log_id' => null, 'provider_message_id' => null]);

    $emailAdapter = Mockery::mock(EmailChannelAdapter::class);
    $emailAdapter->shouldNotReceive('send');

    app()->instance(RenderedEmailChannelAdapter::class, $renderedAdapter);
    app()->instance(EmailChannelAdapter::class, $emailAdapter);

    SendNotificationDeliveryJob::dispatchSync($delivery->id);
});

it('routes to EmailChannelAdapter when rendered_subject is null', function () {
    $delivery = makeDelivery('email', null);

    $emailAdapter = Mockery::mock(EmailChannelAdapter::class);
    $emailAdapter->shouldReceive('send')->once()->andReturn(['email_log_id' => null, 'provider_message_id' => null]);

    $renderedAdapter = Mockery::mock(RenderedEmailChannelAdapter::class);
    $renderedAdapter->shouldNotReceive('send');

    app()->instance(EmailChannelAdapter::class, $emailAdapter);
    app()->instance(RenderedEmailChannelAdapter::class, $renderedAdapter);

    SendNotificationDeliveryJob::dispatchSync($delivery->id);
});
