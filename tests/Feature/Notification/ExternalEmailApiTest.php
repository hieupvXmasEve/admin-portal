<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\EmailController;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Shared\Contracts\Notification\ExternalEmailNotification;
use App\Shared\Contracts\Notification\ExternalEmailPublisher;
use Illuminate\Http\Request;

it('publishes a text/html admin email through the external email publisher', function () {
    $publisher = Mockery::mock(ExternalEmailPublisher::class);
    $publisher->shouldReceive('publishAfterCommit')
        ->once()
        ->withArgs(function (ExternalEmailNotification $notification): bool {
            return $notification->recipientEmails === ['parent@example.test']
                && $notification->subject === 'Reminder Parent'
                && $notification->html === '<p>Hello Parent</p>'
                && $notification->typeKey === 'admin_single_email';
        })
        ->andReturn('event-123');

    $controller = new EmailController(
        Mockery::mock(EmailService::class),
        Mockery::mock(NotificationService::class),
        $publisher,
    );

    $response = $controller->sendSingle(Request::create('/api/emails/send', 'POST', [
        'recipient' => 'parent@example.test',
        'subject' => 'Reminder {{name}}',
        'content' => '<p>Hello {{name}}</p>',
        'template_variables' => ['name' => 'Parent'],
    ]));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['data'])->toMatchArray([
            'email_log_id' => null,
            'status' => 'queued',
            'event_id' => 'event-123',
        ]);
});

it('rejects admin email attachments until retry-safe attachment delivery is designed', function () {
    $publisher = Mockery::mock(ExternalEmailPublisher::class);
    $publisher->shouldNotReceive('publishAfterCommit');

    $controller = new EmailController(
        Mockery::mock(EmailService::class),
        Mockery::mock(NotificationService::class),
        $publisher,
    );

    $response = $controller->sendSingle(Request::create('/api/emails/send', 'POST', [
        'recipient' => 'parent@example.test',
        'subject' => 'Reminder',
        'content' => '<p>Hello</p>',
        'attachments' => ['unsupported'],
    ]));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['errors'])->toHaveKey('attachments');
});
