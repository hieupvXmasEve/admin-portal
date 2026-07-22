<?php

declare(strict_types=1);

namespace App\Modules\Notification\Channels;

use App\Modules\Notification\Channels\Contracts\ChannelAdapter;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Support\SmtpEmailTransport;
use RuntimeException;

class EmailChannelAdapter implements ChannelAdapter
{
    public function __construct(
        private readonly SmtpEmailTransport $smtpEmailTransport,
    ) {}

    public function send(NotificationDelivery $delivery): array
    {
        $message = $delivery->message()->with('recipient')->firstOrFail();
        $recipient = $message->recipient;

        $recipientEmail = $message->recipient_email ?? $recipient?->email;
        if (! is_string($recipientEmail) || $recipientEmail === '') {
            throw new RuntimeException('Notification recipient email is missing.');
        }

        $subject = $message->title ?: 'Notification';
        $content = $message->body ?: ($message->data['body'] ?? 'You have a new notification.');

        return $this->smtpEmailTransport->send(
            $delivery,
            $recipientEmail,
            $subject,
            (string) $content,
        );
    }
}
