<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Modules\Notification\Enums\NotificationDeliveryChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationMessage;
use App\Shared\Contracts\Notification\DTO\TuitionNoticeDeliveryOutcome;
use App\Shared\Contracts\Notification\DTO\TuitionNoticeRenderedCopy;
use App\Shared\Contracts\Notification\TuitionNoticeDeliveryReader;
use Illuminate\Database\Eloquent\Builder;

final class EloquentTuitionNoticeDeliveryReader implements TuitionNoticeDeliveryReader
{
    public function outcomeForContent(
        string $typeKey,
        string $contentHash,
        ?int $recipientUserId = null,
        ?string $recipientEmail = null,
    ): TuitionNoticeDeliveryOutcome {
        $email = is_string($recipientEmail) ? mb_strtolower(trim($recipientEmail)) : '';
        if ($recipientUserId === null && $email === '') {
            return new TuitionNoticeDeliveryOutcome(exists: false, blocksResend: false, failedDeliveryId: null);
        }

        $messages = NotificationMessage::query()
            ->where('type_key', $typeKey)
            ->where(function (Builder $query) use ($recipientUserId, $email): void {
                if ($recipientUserId !== null) {
                    $query->orWhere('recipient_user_id', $recipientUserId);
                }
                if ($email !== '') {
                    $query->orWhere('recipient_email', $email);
                }
            })
            ->get()
            ->filter(static fn (NotificationMessage $message): bool => ($message->data['content_hash'] ?? null) === $contentHash);

        if ($messages->isEmpty()) {
            return new TuitionNoticeDeliveryOutcome(exists: false, blocksResend: false, failedDeliveryId: null);
        }

        $deliveries = NotificationDelivery::query()
            ->whereIn('message_id', $messages->pluck('id'))
            ->where('channel', NotificationDeliveryChannel::Email)
            ->get();

        if ($deliveries->isEmpty()) {
            return new TuitionNoticeDeliveryOutcome(exists: true, blocksResend: true, failedDeliveryId: null);
        }

        $blocksResend = $deliveries->contains(
            static fn (NotificationDelivery $delivery): bool => in_array(
                $delivery->status,
                [NotificationDeliveryStatus::Pending, NotificationDeliveryStatus::Sent],
                true,
            ),
        );

        $failedId = $deliveries
            ->filter(static fn (NotificationDelivery $delivery): bool => $delivery->status === NotificationDeliveryStatus::Failed)
            ->max('id');

        return new TuitionNoticeDeliveryOutcome(
            exists: true,
            blocksResend: $blocksResend,
            failedDeliveryId: $failedId !== null ? (int) $failedId : null,
        );
    }

    public function renderedCopy(int $messageId): ?TuitionNoticeRenderedCopy
    {
        $message = NotificationMessage::query()->find($messageId);
        if (! $message instanceof NotificationMessage) {
            return null;
        }

        $allowed = [
            NotificationTemplateTypeKey::TuitionNotice->value,
            NotificationTemplateTypeKey::ParentTuitionNotice->value,
        ];
        if (! in_array($message->type_key, $allowed, true)) {
            return null;
        }

        $delivery = NotificationDelivery::query()
            ->where('message_id', $message->id)
            ->where('channel', NotificationDeliveryChannel::Email)
            ->whereNotNull('rendered_html')
            ->orderByDesc('id')
            ->first();

        if (! $delivery instanceof NotificationDelivery || ! is_string($delivery->rendered_html) || $delivery->rendered_html === '') {
            return null;
        }

        return new TuitionNoticeRenderedCopy(
            messageId: (int) $message->id,
            typeKey: (string) $message->type_key,
            campusId: (int) $message->campus_id,
            recipientUserId: $message->recipient_user_id !== null ? (int) $message->recipient_user_id : null,
            recipientEmail: is_string($message->recipient_email) ? mb_strtolower(trim($message->recipient_email)) : null,
            renderedHtml: $delivery->rendered_html,
        );
    }
}
