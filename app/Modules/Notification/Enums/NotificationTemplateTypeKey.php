<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

/**
 * Closed enum of the four notification email types stored in
 * `notification_email_templates` and bound through
 * App\Modules\Notification\EmailContent\EmailContentRegistry.
 *
 * Values must exactly match the registry keys; adding a case requires:
 *  1. Adding the binding in EmailContentRegistry (or the DbEmailContentProvider
 *     factory) for the new key.
 *  2. Seeding a row per existing campus for the new case.
 *  3. Adding an EmailVariableSchema implementation for the new key.
 */
enum NotificationTemplateTypeKey: string
{
    case PaymentReminder = 'payment_reminder';

    case ParentPaymentReminder = 'parent_payment_reminder';

    case DngPaymentPushed = 'dng_payment_pushed';

    case DngPaymentReceived = 'dng_payment_received';
}
