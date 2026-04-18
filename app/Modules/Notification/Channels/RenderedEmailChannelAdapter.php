<?php

declare(strict_types=1);

namespace App\Modules\Notification\Channels;

use App\Modules\Notification\Channels\Contracts\ChannelAdapter;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Services\EmailService;
use RuntimeException;

class RenderedEmailChannelAdapter implements ChannelAdapter
{
    public function __construct(
        private readonly EmailService $emailService,
    ) {}

    public function send(NotificationDelivery $delivery): array
    {
        if ($delivery->rendered_subject === null || $delivery->rendered_html === null) {
            throw new RuntimeException('RenderedEmailChannelAdapter requires rendered_subject and rendered_html.');
        }

        $message = $delivery->message()->with('recipient')->firstOrFail();
        $recipient = $message->recipient;

        if (! $recipient || ! $recipient->email) {
            throw new RuntimeException('Notification recipient email is missing.');
        }

        $emailLog = $this->emailService->sendSingleEmail(
            (string) $recipient->email,
            $delivery->rendered_subject,
            $delivery->rendered_html,
            campusId: $message->campus_id,
        );

        return [
            'provider_message_id' => null,
            'email_log_id' => (int) $emailLog->id,
        ];
    }
}
