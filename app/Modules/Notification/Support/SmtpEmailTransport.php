<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Modules\Notification\Mail\RenderedNotificationEmail;
use App\Modules\Notification\Models\NotificationDelivery;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Notification-owned SMTP transport for V2 deliveries.
 *
 * EmailLog is retained as the historical audit record while the Notification
 * delivery itself owns queueing, retries, and status transitions.
 */
class SmtpEmailTransport
{
    /**
     * @return array{provider_message_id: string|null, email_log_id: int}
     */
    public function send(
        NotificationDelivery $delivery,
        string $recipient,
        string $subject,
        string $html,
        ?string $text = null,
    ): array {
        $message = $delivery->message()->firstOrFail();
        $emailLog = $this->resolveEmailLog($delivery, $recipient, $subject, $message->campus_id);
        $emailLog->markAsSending();

        try {
            $this->configureMailer($this->resolveConfiguration($message->campus_id));
            Mail::to($recipient)->send(new RenderedNotificationEmail($subject, $html, $text));

            $providerMessageId = null;
            $emailLog->markAsSent($providerMessageId);

            return [
                'provider_message_id' => $providerMessageId,
                'email_log_id' => (int) $emailLog->id,
            ];
        } catch (\Throwable $exception) {
            $emailLog->markAsFailed(mb_substr($exception->getMessage(), 0, 2000));

            throw $exception;
        }
    }

    private function resolveConfiguration(?int $campusId): EmailConfiguration
    {
        $configuration = EmailConfiguration::getActiveForCampus($campusId);
        if ($configuration === null) {
            throw new RuntimeException(sprintf(
                'No active EmailConfiguration found for campus_id=%s. Configure an active email configuration in the database.',
                $campusId === null ? 'null' : (string) $campusId,
            ));
        }

        return $configuration;
    }

    private function configureMailer(EmailConfiguration $configuration): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', $configuration->toMailConfig());
        Config::set('mail.from.address', $configuration->from_address);
        Config::set('mail.from.name', $configuration->from_name);

        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
    }

    private function resolveEmailLog(
        NotificationDelivery $delivery,
        string $recipient,
        string $subject,
        ?int $campusId,
    ): EmailLog {
        if ($delivery->email_log_id !== null) {
            $emailLog = $delivery->emailLog()->first();
            if ($emailLog !== null) {
                return $emailLog;
            }
        }

        $emailLog = EmailLog::create([
            'recipient' => $recipient,
            'sender' => config('mail.from.address'),
            'subject' => $subject,
            'status' => EmailLog::STATUS_QUEUED,
            'queued_at' => now(),
            'metadata' => [
                'source' => 'notification_v2',
                'notification_delivery_id' => $delivery->id,
                'notification_message_id' => $delivery->message_id,
                'campus_id' => $campusId,
            ],
        ]);

        $delivery->forceFill(['email_log_id' => $emailLog->id])->save();

        return $emailLog;
    }
}
