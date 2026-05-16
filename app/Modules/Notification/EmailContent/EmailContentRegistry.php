<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent;

use App\Modules\Notification\EmailContent\Contracts\EmailContentProvider;
use App\Modules\Notification\EmailContent\Types\DbEmailContentProvider;
use App\Modules\Notification\EmailContent\Types\DngPaymentPushedEmailContent;
use App\Modules\Notification\EmailContent\Types\DngPaymentReceivedEmailContent;
use App\Modules\Notification\EmailContent\Types\ParentPaymentReminderEmailContent;
use App\Modules\Notification\EmailContent\Types\PaymentReminderEmailContent;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use InvalidArgumentException;

/**
 * Registry of EmailContentProvider implementations keyed by `type_key`.
 *
 * When `config('notifications.use_db_templates', true)` is true (the default
 * after P1 parity ships), every key resolves to a {@see DbEmailContentProvider}
 * bound to the matching {@see NotificationTemplateTypeKey}. When false, the
 * legacy hard-coded `*EmailContent` classes are resolved instead. The flag
 * exists for the P1 -> P2 transition window and will be removed in the
 * post-P2 cleanup (see config/notifications.php).
 *
 * Resolved providers are memoised per `type_key` so the
 * {@see DbEmailContentProvider} per-(typeKey, campusId) row cache persists
 * across multiple `resolve()` calls in the same request.
 */
final class EmailContentRegistry
{
    /** @var array<string, class-string<EmailContentProvider>> */
    private array $legacyMap = [
        'dng_payment_pushed' => DngPaymentPushedEmailContent::class,
        'dng_payment_received' => DngPaymentReceivedEmailContent::class,
        'parent_payment_reminder' => ParentPaymentReminderEmailContent::class,
        'payment_reminder' => PaymentReminderEmailContent::class,
    ];

    /** @var array<string, EmailContentProvider> */
    private array $resolved = [];

    public function has(string $typeKey): bool
    {
        return isset($this->legacyMap[$typeKey]);
    }

    public function resolve(string $typeKey): EmailContentProvider
    {
        if (! $this->has($typeKey)) {
            throw new InvalidArgumentException("No EmailContentProvider registered for type_key: {$typeKey}");
        }

        if (isset($this->resolved[$typeKey])) {
            return $this->resolved[$typeKey];
        }

        return $this->resolved[$typeKey] = $this->build($typeKey);
    }

    private function build(string $typeKey): EmailContentProvider
    {
        if ((bool) config('notifications.use_db_templates', true)) {
            return new DbEmailContentProvider(NotificationTemplateTypeKey::from($typeKey));
        }

        return app($this->legacyMap[$typeKey]);
    }
}
