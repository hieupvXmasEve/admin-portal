<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent;

use App\Modules\Notification\EmailContent\Contracts\EmailContentProvider;
use App\Modules\Notification\EmailContent\Types\DngPaymentPushedEmailContent;
use App\Modules\Notification\EmailContent\Types\DngPaymentReceivedEmailContent;
use App\Modules\Notification\EmailContent\Types\ParentPaymentReminderEmailContent;
use App\Modules\Notification\EmailContent\Types\PaymentReminderEmailContent;
use InvalidArgumentException;

final class EmailContentRegistry
{
    /** @var array<string, class-string<EmailContentProvider>> */
    private array $map = [
        'dng_payment_pushed' => DngPaymentPushedEmailContent::class,
        'dng_payment_received' => DngPaymentReceivedEmailContent::class,
        'parent_payment_reminder' => ParentPaymentReminderEmailContent::class,
        'payment_reminder' => PaymentReminderEmailContent::class,
    ];

    public function has(string $typeKey): bool
    {
        return isset($this->map[$typeKey]);
    }

    public function resolve(string $typeKey): EmailContentProvider
    {
        if (! $this->has($typeKey)) {
            throw new InvalidArgumentException("No EmailContentProvider registered for type_key: {$typeKey}");
        }

        return app($this->map[$typeKey]);
    }
}
