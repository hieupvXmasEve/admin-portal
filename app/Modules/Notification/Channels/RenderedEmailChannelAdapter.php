<?php

declare(strict_types=1);

namespace App\Modules\Notification\Channels;

use App\Modules\Notification\Channels\Contracts\ChannelAdapter;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Support\SmtpEmailTransport;
use RuntimeException;

class RenderedEmailChannelAdapter implements ChannelAdapter
{
    public function __construct(
        private readonly SmtpEmailTransport $smtpEmailTransport,
    ) {}

    public function send(NotificationDelivery $delivery): array
    {
        if ($delivery->rendered_subject === null || $delivery->rendered_html === null) {
            throw new RuntimeException('RenderedEmailChannelAdapter requires rendered_subject and rendered_html.');
        }

        $message = $delivery->message()->with('recipient')->firstOrFail();
        $recipient = $message->recipient;

        $recipientEmail = $message->recipient_email ?? $recipient?->email;
        if (! is_string($recipientEmail) || $recipientEmail === '') {
            throw new RuntimeException('Notification recipient email is missing.');
        }

        return $this->smtpEmailTransport->send(
            $delivery,
            $recipientEmail,
            $delivery->rendered_subject,
            $delivery->rendered_html,
            $delivery->rendered_text,
        );
    }
}
