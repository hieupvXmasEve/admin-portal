<?php

declare(strict_types=1);

namespace App\Modules\Notification\Channels;

use App\Modules\Notification\Channels\Contracts\ChannelAdapter;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Services\EmailService;
use RuntimeException;

class EmailChannelAdapter implements ChannelAdapter
{
    public function __construct(
        private readonly EmailService $emailService,
    ) {}

    public function send(NotificationDelivery $delivery): array
    {
        $message = $delivery->message()->with('recipient')->firstOrFail();
        $recipient = $message->recipient;

        if (! $recipient || ! $recipient->email) {
            throw new RuntimeException('Notification recipient email is missing.');
        }

        $subject = $message->title ?: 'Notification';
        $content = $message->body ?: ($message->data['body'] ?? 'You have a new notification.');
        $emailLog = $this->emailService->sendSingleEmail(
            (string) $recipient->email,
            $subject,
            (string) $content,
            campusId: $message->campus_id,
        );

        return [
            'provider_message_id' => null,
            'email_log_id' => (int) $emailLog->id,
        ];
    }
}
