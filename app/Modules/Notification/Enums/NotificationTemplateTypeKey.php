<?php

declare(strict_types=1);

namespace App\Modules\Notification\Enums;

/**
 * Closed enum of notification email types stored in
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

    case InstallmentPaymentReminder = 'installment_payment_reminder';

    case ParentInstallmentPaymentReminder = 'parent_installment_payment_reminder';

    case DngPaymentPushed = 'dng_payment_pushed';

    case DngPaymentReceived = 'dng_payment_received';

    case ScholarshipAdjustmentConfirmationRequested = 'scholarship_adjustment_confirmation_requested';

    case ScholarshipAdjustmentTuitionDeferred = 'scholarship_adjustment_tuition_deferred';

    case TuitionNotice = 'tuition_notice';

    case ParentTuitionNotice = 'parent_tuition_notice';

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
            self::InstallmentPaymentReminder => [
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'semester_code' => ['label' => 'Semester code', 'sample' => 'SP2026'],
                'invoice_code' => ['label' => 'DNG / Invoice code', 'sample' => 'DNG-456'],
                'installment_no' => ['label' => 'Installment number', 'sample' => 2],
                'installment_total' => ['label' => 'Total installments', 'sample' => 4],
                'installment_amount_formatted' => ['label' => 'This installment amount (formatted)', 'sample' => '5.000.000'],
                'remaining_balance_formatted' => ['label' => 'Remaining balance on parent charge (formatted)', 'sample' => '15.000.000'],
                'due_date' => ['label' => 'Installment due date', 'sample' => '15/06/2026'],
            ],
            self::ParentInstallmentPaymentReminder => [
                'parent_name' => ['label' => 'Parent name', 'sample' => 'Quý Phụ Huynh'],
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'semester_code' => ['label' => 'Semester code', 'sample' => 'SP2026'],
                'invoice_code' => ['label' => 'DNG / Invoice code', 'sample' => 'DNG-456'],
                'installment_no' => ['label' => 'Installment number', 'sample' => 2],
                'installment_total' => ['label' => 'Total installments', 'sample' => 4],
                'installment_amount_formatted' => ['label' => 'This installment amount (formatted)', 'sample' => '5.000.000'],
                'remaining_balance_formatted' => ['label' => 'Remaining balance on parent charge (formatted)', 'sample' => '15.000.000'],
                'due_date' => ['label' => 'Installment due date', 'sample' => '15/06/2026'],
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
            // Variables mirror the payload built by
            // AcademicLifecycleEventFactory::scholarshipAdjustmentConfirmationRequested().
            // No money figures here on purpose: the decision does not exist yet
            // when this is sent, so any amount would be a guess.
            self::ScholarshipAdjustmentConfirmationRequested => [
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'semester_code' => ['label' => 'Semester the adjustment would apply to', 'sample' => 'SUMMER2026'],
                'deadline' => ['label' => 'Response deadline', 'sample' => '16/06/2026 09:00'],
                'action_url' => ['label' => 'Portal link to the review', 'sample' => '/scholarship-review/12'],
            ],
            // Variables mirror the payload built by
            // AcademicLifecycleEventFactory::scholarshipAdjustmentTuitionDeferred().
            // No money figures here on purpose: the fee was never generated,
            // so there is nothing to quote yet.
            self::ScholarshipAdjustmentTuitionDeferred => [
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'semester_code' => ['label' => 'Semester tuition was due', 'sample' => 'SUMMER2026'],
                'scholarship_name' => ['label' => 'Scholarship under review', 'sample' => 'Asia Pioneer'],
                'deferral_reason' => ['label' => 'Reason for the hold', 'sample' => 'Đang chờ xét duyệt điều chỉnh học bổng cho học kỳ này.'],
                'action_url' => ['label' => 'Portal link to the review', 'sample' => '/scholarship-review/12'],
            ],
            self::TuitionNotice, self::ParentTuitionNotice => [
                'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
                'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
                'semester_name' => ['label' => 'Semester name', 'sample' => 'Học kỳ hè 2026'],
                'due_date' => ['label' => 'Payment deadline', 'sample' => '15/06/2026'],
                'total_due' => ['label' => 'Total amount due (formatted)', 'sample' => '12.500.000'],
                'items_summary' => ['label' => 'Outstanding items (plain text)', 'sample' => "Học phí theo kế hoạch: 10.000.000 ₫\nBHYT: 2.500.000 ₫"],
                'payment_instruction' => ['label' => 'How to pay', 'sample' => 'Sinh viên thanh toán bằng cách quét QR trên cổng thông tin. Phụ huynh có thể thanh toán hộ. Kế toán ghi nhận tiền mặt hoặc chuyển khoản tại văn phòng tài chính.'],
            ],
        };
    }
}
