<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent\Types;

use App\Modules\Notification\EmailContent\Contracts\EmailContentProvider;
use App\Modules\Notification\EmailContent\Contracts\EmailVariableSchema;

final class DngPaymentPushedEmailContent implements EmailContentProvider, EmailVariableSchema
{
    public function subject(array $data): string
    {
        $semesterPart = isset($data['semester_code']) && $data['semester_code'] !== ''
            ? ' học kỳ ' . $data['semester_code']
            : '';

        return "[Asia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí{$semesterPart}";
    }

    public function htmlBody(array $data): string
    {
        $studentName = htmlspecialchars((string) ($data['student_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $studentCode = htmlspecialchars((string) ($data['student_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $studentNameCode = $studentName . ($studentCode !== '' ? ' ' . $studentCode : '');
        $semesterCode = htmlspecialchars((string) ($data['semester_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $programName = htmlspecialchars((string) ($data['program_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $invoiceCode = htmlspecialchars((string) ($data['invoice_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $amount = htmlspecialchars((string) ($data['amount_formatted'] ?? $data['amount'] ?? ''), ENT_QUOTES, 'UTF-8');

        $dueDateRowVi = '';
        $dueDateRowEn = '';
        if (isset($data['due_date']) && $data['due_date'] !== '' && $data['due_date'] !== null) {
            $dueDate = htmlspecialchars((string) $data['due_date'], ENT_QUOTES, 'UTF-8');
            $dueDateRowVi = <<<HTML
            <tr>
                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Hạn thanh toán:</strong></td>
                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{$dueDate}</strong></td>
            </tr>
            HTML;
            $dueDateRowEn = <<<HTML
            <tr>
                <td style="border: 1px solid #d1d5db; padding: 8px 16px;">Payment Deadline:</td>
                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{$dueDate}</strong></td>
            </tr>
            HTML;
        }

        $semesterLineVi = $semesterCode !== ''
            ? "<p>Phòng Dịch vụ Sinh viên (Student Services) xin thông báo về việc thanh toán học phí Học kỳ <strong>{$semesterCode}</strong>, áp dụng cho sinh viên hệ <strong>{$programName}</strong>.</p>"
            : "<p>Phòng Dịch vụ Sinh viên (Student Services) xin thông báo về việc thanh toán học phí, áp dụng cho sinh viên hệ <strong>{$programName}</strong>.</p>";

        $semesterLineEn = $semesterCode !== ''
            ? "<p>The Student Services Department would like to inform you about the tuition fee payment for the <strong>{$semesterCode}</strong> applicable to students enrolled in the <strong>{$programName}</strong>.</p>"
            : "<p>The Student Services Department would like to inform you about the tuition fee payment applicable to students enrolled in the <strong>{$programName}</strong>.</p>";

        return <<<HTML
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 24px;">

            <p>Thân gửi <strong>{$studentNameCode}</strong>,</p>

            {$semesterLineVi}

            <p><em>Vui lòng bỏ qua email này nếu sinh viên đã thanh toán học phí.</em></p>

            <p>Vui lòng xem chi tiết thông tin học phí như sau:</p>

            <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                <tbody>
                    <tr>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Số hoá đơn (No.):</strong></td>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{$invoiceCode}</strong></td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Số tiền còn nợ:</strong></td>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{$amount}</strong> VND</td>
                    </tr>
                    {$dueDateRowVi}
                </tbody>
            </table>

            <p><strong>Hình thức thanh toán:</strong></p>
            <p>Vui lòng hoàn tất thanh toán qua Cổng Sinh viên hoặc Cổng Phụ huynh (chỉ chọn MỘT phương thức):</p>
            <p><strong>Bước 1:</strong> Đăng nhập Student Portal hoặc Parents Portal tại <a href="http://portal.asia-vn.edu.vn/login" style="color: #1155cc;">portal.asia-vn.edu.vn/login</a> bằng email đã đăng ký</p>
            <p><strong>Bước 2:</strong> Chọn mục <strong>Fee &amp; Finance</strong> → <strong>DNG Payment</strong></p>
            <ul style="margin: 8px 0 8px 20px; padding: 0;">
                <li><strong>Chọn QR code để chuyển khoản qua ngân hàng; hoặc</strong></li>
                <li><strong>Chọn FPT Pay nếu có nhu cầu trả góp và làm theo hướng dẫn trên hệ thống.</strong></li>
            </ul>
            <p><strong>Bước 3:</strong> Lựa chọn phương thức thanh toán: QR Code hoặc FPT Pay và hoàn tất thanh toán học phí</p>
            <p><strong>Lưu ý:</strong> Nhà trường chỉ thu học phí qua một hệ thống duy nhất là cổng Student Portal/Parent Portal. Sinh viên không hoàn thành nghĩa vụ thanh toán đúng thời hạn sẽ được áp dụng các biện pháp xử lý học vụ theo quy định của Nhà trường.</p>
            <p>Nếu sinh viên có bất kỳ thắc mắc nào, vui lòng phản hồi email này, gửi yêu cầu qua hệ thống queries hoặc liên hệ tổng đài để được hỗ trợ.</p>
            <p>Trân trọng.</p>

            <hr style="border: none; border-top: 1px solid #ccc; margin: 24px 0;">

            <p>Dear <strong>{$studentNameCode}</strong>,</p>

            {$semesterLineEn}

            <p><em>Please disregard this email if the tuition fee has already been paid.</em></p>

            <p>Please find the detailed tuition information below:</p>

            <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                <tbody>
                    <tr>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;">Invoice No.:</td>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{$invoiceCode}</strong></td>
                    </tr>
                    <tr>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;">Tuition Fee{$semesterCode}:</td>
                        <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{$amount}</strong> VND</td>
                    </tr>
                    {$dueDateRowEn}
                </tbody>
            </table>

            <p><strong>Payment method:</strong></p>
            <p>Please complete your payment via <strong>Student Portal</strong> OR <strong>Parent Portal</strong> (select <strong>ONE ONLY</strong>)</p>
            <p><strong>Step 1:</strong> Log in to the Student Portal or Parent Portal at <a href="http://portal.asia-vn.edu.vn/login" style="color: #1155cc;">portal.asia-vn.edu.vn/login</a> using your registered personal email.</p>
            <p><strong>Step 2:</strong> Select <strong>Fee &amp; Finance → DNG Payment</strong></p>
            <ul style="margin: 8px 0 8px 20px; padding: 0;">
                <li>Choose the QR code to make a bank transfer; or</li>
                <li>Select FPT Pay if you wish to pay in instalments and follow the instructions on the system.</li>
            </ul>
            <p><strong>Step 3:</strong> Select your preferred payment method (QR Code or FPT Pay) and complete the tuition fee payment.</p>
            <p><strong>Note:</strong> The University only collects tuition fees through a single system, the Student Portal/Parent Portal. Failure to meet the payment deadline may result in academic measures in accordance with University regulations.</p>
            <p>If you have any questions, please reply to this email, submit a query via the system, or contact the hotline for support.</p>
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
            'program_name' => ['label' => 'Program name', 'sample' => 'B.Sc Software Engineering'],
            'invoice_code' => ['label' => 'Invoice code', 'sample' => 'INV-2026-00123'],
            'amount_formatted' => ['label' => 'Amount (formatted)', 'sample' => '12.500.000 VNĐ'],
            'due_date' => ['label' => 'Payment deadline', 'sample' => '15/06/2026'],
        ];
    }
}
