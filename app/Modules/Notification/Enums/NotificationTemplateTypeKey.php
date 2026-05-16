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
 *  3. Adding a case to `availableVariables()` for the new key.
 */
enum NotificationTemplateTypeKey: string
{
    case PaymentReminder = 'payment_reminder';

    case ParentPaymentReminder = 'parent_payment_reminder';

    case DngPaymentPushed = 'dng_payment_pushed';

    case DngPaymentReceived = 'dng_payment_received';

    /**
     * Per-case variable allow-list consumed by the FormRequest validator,
     * the admin editor's variable picker, and the preview pane sample render.
     *
     * Keys are bare variable names (no braces). Each entry has a human-readable
     * label and a representative sample value used in preview/test renders.
     *
     * @return array<string, array{label: string, sample: mixed}>
     */
    public function availableVariables(): array
    {
        return match ($this) {
            self::PaymentReminder => [
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'semester_code' => ['label' => 'Semester code', 'sample' => 'SP2026'],
                'invoice_code' => ['label' => 'Invoice code', 'sample' => 'INV-2026-00123'],
                'balance_formatted' => ['label' => 'Outstanding balance (formatted)', 'sample' => '5.000.000'],
                'due_date' => ['label' => 'Payment deadline', 'sample' => '15/06/2026'],
            ],
            self::ParentPaymentReminder => [
                'parent_name' => ['label' => 'Parent name', 'sample' => 'Quý Phụ Huynh'],
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'semester_code' => ['label' => 'Semester code', 'sample' => 'SP2026'],
                'invoice_code' => ['label' => 'Invoice code', 'sample' => 'INV-2026-00123'],
                'balance_formatted' => ['label' => 'Outstanding balance (formatted)', 'sample' => '5.000.000'],
                'due_date' => ['label' => 'Payment deadline', 'sample' => '15/06/2026'],
            ],
            self::DngPaymentPushed => [
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'semester_code' => ['label' => 'Semester code', 'sample' => 'SP2026'],
                'program_name' => ['label' => 'Program name', 'sample' => 'B.Sc Software Engineering'],
                'invoice_code' => ['label' => 'Invoice code', 'sample' => 'INV-2026-00123'],
                'amount_formatted' => ['label' => 'Amount (formatted)', 'sample' => '12.500.000 VNĐ'],
                'due_date' => ['label' => 'Payment deadline', 'sample' => '15/06/2026'],
            ],
            self::DngPaymentReceived => [
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'semester_code' => ['label' => 'Semester code', 'sample' => 'SP2026'],
                'amount_formatted' => ['label' => 'Amount paid (formatted)', 'sample' => '12.500.000 VNĐ'],
                'paid_at' => ['label' => 'Paid at', 'sample' => '15/06/2026 14:30'],
            ],
        };
    }
}
