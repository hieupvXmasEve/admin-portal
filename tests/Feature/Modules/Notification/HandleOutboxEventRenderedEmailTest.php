<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Notification\Actions\HandleOutboxEventAction;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\Enums\NotificationDeliveryChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEventOutbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['notification.campus.strict_isolation' => false]);
});

function makeOutbox(array $payload, ?int $campusId = null): NotificationEventOutbox
{
    return NotificationEventOutbox::create([
        'event_id' => (string) Str::uuid(),
        'event_name' => 'finance.dng_payment_pushed',
        'event_version' => 1,
        'occurred_at' => now(),
        'aggregate_type' => 'dng_payment_request',
        'aggregate_id' => '1',
        'campus_id' => $campusId,
        'payload' => $payload,
        'status' => NotificationOutboxStatus::Pending,
    ]);
}

it('stores rendered_subject and rendered_html on email delivery when type_key has registered provider', function () {
    $user = User::factory()->create();

    $outbox = makeOutbox([
        'type_key' => 'dng_payment_pushed',
        'channels' => ['email'],
        'recipient_targets' => [['type' => 'user', 'id' => $user->id]],
        'data' => [
            'title' => 'Test',
            'body' => 'Test body',
            'student_name' => 'Jane Smith',
            'student_code' => 'STU001',
            'semester_code' => 'Fall 2024',
            'program_name' => 'CS',
            'invoice_code' => 'STU001_123',
            'amount_formatted' => '25.000.000 VNĐ',
            'due_date' => '30/06/2025',
        ],
    ]);

    // No dng_payment_request_id → data builder uses existing data array
    app(HandleOutboxEventAction::class)->run($outbox);

    $delivery = NotificationDelivery::query()
        ->where('channel', NotificationDeliveryChannel::Email)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->rendered_subject)->not->toBeNull()
        ->and($delivery->rendered_subject)->toContain('[Asia Việt Nam]')
        ->and($delivery->rendered_subject)->toContain('Fall 2024')
        ->and($delivery->rendered_html)->not->toBeNull()
        ->and($delivery->rendered_html)->toContain('Jane Smith');
});

it('stores null rendered_subject on email delivery when type_key has no registered provider', function () {
    $user = User::factory()->create();

    $outbox = makeOutbox([
        'type_key' => 'manual_notification',
        'channels' => ['email'],
        'recipient_targets' => [['type' => 'user', 'id' => $user->id]],
        'data' => [
            'title' => 'Test',
            'body' => 'Test body',
        ],
    ]);

    app(HandleOutboxEventAction::class)->run($outbox);

    $delivery = NotificationDelivery::query()
        ->where('channel', NotificationDeliveryChannel::Email)
        ->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->rendered_subject)->toBeNull();
});
