<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\ExamResitAttempt;
use App\Shared\Contracts\Notification\ExternalEmailNotification;
use App\Shared\Contracts\Notification\ExternalEmailPublisher;
use RuntimeException;

class SendExamResitCancellationNoticeAction
{
    public function __construct(
        private readonly ExternalEmailPublisher $externalEmailPublisher,
    ) {}

    public function run(ExamResitAttempt $attempt): string
    {
        $attempt->loadMissing(['student:id,student_id,full_name,email', 'unit:id,code,name']);

        $student = $attempt->student;
        if ($student === null || ! is_string($student->email) || trim($student->email) === '') {
            throw new RuntimeException('Student email is missing.');
        }

        return $this->externalEmailPublisher->publishAfterCommit(new ExternalEmailNotification(
            recipientEmails: [trim($student->email)],
            subject: $this->subject($attempt),
            html: $this->htmlBody($attempt),
            campusId: $attempt->campus_id,
            aggregateType: 'exam_resit_attempt',
            aggregateId: (string) $attempt->id,
            typeKey: 'exam_resit_cancellation',
            deduplicationKey: 'exam_resit_attempt:'.$attempt->id.':cancellation_notice',
        ));
    }

    private function subject(ExamResitAttempt $attempt): string
    {
        return match ($attempt->cancellation_fee_disposition) {
            ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND => '[Asia Viet Nam] Exam resit cancelled - paid fee retained',
            ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE => '[Asia Viet Nam] Exam resit cancelled - pending fee cancelled',
            default => '[Asia Viet Nam] Exam resit cancelled',
        };
    }

    private function htmlBody(ExamResitAttempt $attempt): string
    {
        $studentName = e((string) ($attempt->student?->full_name ?? 'Student'));
        $studentCode = e((string) ($attempt->student?->student_id ?? ''));
        $unitCode = e((string) ($attempt->unit?->code ?? ''));
        $unitName = e((string) ($attempt->unit?->name ?? ''));
        $fee = number_format((float) $attempt->fee_amount, 0, ',', '.').' VND';
        $reason = e((string) $attempt->cancellation_reason);
        $feeMessage = match ($attempt->cancellation_fee_disposition) {
            ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND => 'The paid exam-resit fee remains recorded in the student finance portal. This cancellation does not create a refund.',
            ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE => 'The pending exam-resit fee collection has been cancelled and is no longer payable.',
            default => 'No exam-resit fee collection was active for this cancellation.',
        };
        $feeMessageVi = match ($attempt->cancellation_fee_disposition) {
            ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND => 'Khoản phí thi lại đã thanh toán vẫn được ghi nhận trên cổng tài chính sinh viên. Thao tác hủy này không tạo hoàn phí.',
            ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE => 'Khoản phí thi lại đang chờ thu đã được hủy và sinh viên không cần thanh toán khoản này.',
            default => 'Không có khoản phí thi lại đang chờ thu tại thời điểm hủy.',
        };

        return <<<HTML
            <div style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6;">
                <p>Dear <strong>{$studentName} {$studentCode}</strong>,</p>
                <p>Your exam-resit attempt for <strong>{$unitCode} {$unitName}</strong> has been cancelled.</p>
                <p>{$feeMessage}</p>
                <p><strong>Fee amount:</strong> {$fee}</p>
                <p><strong>Cancellation reason:</strong> {$reason}</p>
                <hr style="border: 0; border-top: 1px solid #ddd; margin: 20px 0;" />
                <p>Thân gửi <strong>{$studentName} {$studentCode}</strong>,</p>
                <p>Lượt thi lại của môn <strong>{$unitCode} {$unitName}</strong> đã được hủy.</p>
                <p>{$feeMessageVi}</p>
                <p><strong>Số tiền phí:</strong> {$fee}</p>
                <p><strong>Lý do hủy:</strong> {$reason}</p>
                <p>Trân trọng.</p>
            </div>
            HTML;
    }
}
