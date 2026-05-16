<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent\Types;

use App\Modules\Notification\EmailContent\Contracts\EmailContentProvider;
use App\Modules\Notification\EmailContent\Contracts\EmailVariableSchema;

final class DngPaymentReceivedEmailContent implements EmailContentProvider, EmailVariableSchema
{
    public function subject(array $data): string
    {
        $semesterPart = isset($data['semester_code']) && $data['semester_code'] !== ''
            ? ' - Học kỳ '.$data['semester_code']
            : '';

        return "[Asia Việt Nam] Xác nhận thanh toán thành công{$semesterPart}";
    }

    public function htmlBody(array $data): string
    {
        $studentName = htmlspecialchars((string) ($data['student_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $studentCode = htmlspecialchars((string) ($data['student_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $studentNameCode = $studentName.($studentCode !== '' ? ' '.$studentCode : '');
        $amount = htmlspecialchars((string) ($data['amount_formatted'] ?? ''), ENT_QUOTES, 'UTF-8');
        $semesterCode = htmlspecialchars((string) ($data['semester_code'] ?? ''), ENT_QUOTES, 'UTF-8');

        $paidAt = '';
        if (! empty($data['paid_at'])) {
            try {
                $paidAt = \Carbon\Carbon::parse($data['paid_at'])->format('d/m/Y H:i');
            } catch (\Throwable) {
                $paidAt = htmlspecialchars((string) $data['paid_at'], ENT_QUOTES, 'UTF-8');
            }
        }

        $semesterRowVi = $semesterCode !== ''
            ? "<tr><td style=\"border: 1px solid #d1d5db; padding: 8px 16px;\"><strong>Học kỳ:</strong></td><td style=\"border: 1px solid #d1d5db; padding: 8px 16px;\"><strong>{$semesterCode}</strong></td></tr>"
            : '';

        $semesterRowEn = $semesterCode !== ''
            ? "<tr><td style=\"border: 1px solid #d1d5db; padding: 8px 16px;\">Semester:</td><td style=\"border: 1px solid #d1d5db; padding: 8px 16px;\"><strong>{$semesterCode}</strong></td></tr>"
            : '';

        $paidAtRowVi = $paidAt !== ''
            ? "<tr><td style=\"border: 1px solid #d1d5db; padding: 8px 16px;\"><strong>Thời gian nhận tiền:</strong></td><td style=\"border: 1px solid #d1d5db; padding: 8px 16px;\"><strong>{$paidAt}</strong></td></tr>"
            : '';

        $paidAtRowEn = $paidAt !== ''
            ? "<tr><td style=\"border: 1px solid #d1d5db; padding: 8px 16px;\">Payment received at:</td><td style=\"border: 1px solid #d1d5db; padding: 8px 16px;\"><strong>{$paidAt}</strong></td></tr>"
            : '';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 24px;">

            <p>Thân gửi <strong>{$studentNameCode}</strong>,</p>

            <p>Phòng Dịch vụ Sinh viên (Student Services) xác nhận đã nhận được khoản thanh toán học phí của bạn.</p>

            <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                <tbody>
                    <tr>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Số tiền đã thanh toán:</strong></td>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{$amount}</strong> VND</td>
                    </tr>
                    {$semesterRowVi}
                    {$paidAtRowVi}
                </tbody>
            </table>

            <p>Nếu bạn có bất kỳ thắc mắc nào, vui lòng phản hồi email này hoặc gửi yêu cầu qua hệ thống queries.</p>
            <p>Trân trọng.</p>

            <hr style="border: none; border-top: 1px solid #ccc; margin: 24px 0;">

            <p>Dear <strong>{$studentNameCode}</strong>,</p>

            <p>The Student Services Department confirms that your tuition fee payment has been successfully received.</p>

            <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                <tbody>
                    <tr>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;">Amount paid:</td>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{$amount}</strong> VND</td>
                    </tr>
                    {$semesterRowEn}
                    {$paidAtRowEn}
                </tbody>
            </table>

            <p>If you have any questions, please reply to this email or submit a query via the system.</p>
            <p><em>Regards.</em></p>

        </body>
        </html>
        HTML;
    }

    public function textBody(array $data): ?string
    {
        return null;
    }

    public function availableVariables(): array
    {
        return [
            'student_name' => ['label' => 'Student name', 'sample' => 'Nguyễn Văn A'],
            'student_code' => ['label' => 'Student code', 'sample' => 'SE12345'],
            'semester_code' => ['label' => 'Semester code', 'sample' => 'SP2026'],
            'amount_formatted' => ['label' => 'Amount paid (formatted)', 'sample' => '12.500.000 VNĐ'],
            'paid_at' => ['label' => 'Paid at', 'sample' => '15/06/2026 14:30'],
        ];
    }
}
