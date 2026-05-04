<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent\Types;

use App\Modules\Notification\EmailContent\Contracts\EmailContentProvider;

final class ParentPaymentReminderEmailContent implements EmailContentProvider
{
    public function subject(array $data): string
    {
        $semesterPart = isset($data['semester_code']) && $data['semester_code'] !== ''
            ? ' ' . $data['semester_code']
            : '';

        return "[Asia Việt Nam] Reminder: Tuition Fee Payment{$semesterPart}_Nhắc nhở thanh toán học phí";
    }

    public function htmlBody(array $data): string
    {
        $parentName = htmlspecialchars((string) ($data['parent_name'] ?? 'Quý Phụ Huynh'), ENT_QUOTES, 'UTF-8');
        $studentName = htmlspecialchars((string) ($data['student_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $studentCode = htmlspecialchars((string) ($data['student_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $studentNameCode = trim($studentName . ($studentCode !== '' ? ' ' . $studentCode : ''));
        $semesterCode = htmlspecialchars((string) ($data['semester_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $invoiceCode = htmlspecialchars((string) ($data['invoice_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $balance = htmlspecialchars((string) ($data['balance_formatted'] ?? ''), ENT_QUOTES, 'UTF-8');
        $dueDate = htmlspecialchars((string) ($data['due_date'] ?? ''), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
        </head>
        <body style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 24px;">

        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Dear <strong>{$parentName},</strong></span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">This is a reminder to complete the tuition fee payment for <strong>{$studentNameCode}</strong> for <strong>{$semesterCode}</strong>. Please disregard this email if the tuition fee has already been paid.</span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Please find the detailed tuition information below:</span></p>
        <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
            <tbody>
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);">Student Name:</span></td>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{$studentName}</strong></span></td>
                </tr>
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);">Invoice No.:</span></td>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{$invoiceCode}</strong></span></td>
                </tr>
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Tuition Fee – <strong>{$semesterCode}:</strong></span></td>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{$balance}</strong> VND</span></td>
                </tr>
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);">Payment Deadline:</span></td>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);"><strong>{$dueDate}</strong></span></td>
                </tr>
            </tbody>
        </table>
        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Payment method:&nbsp;</strong></span></p>
        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);">Please complete your payment via <strong>Student Portal</strong> OR <strong>Parent Portal</strong> (select <strong>ONE ONLY</strong>)</span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);"><strong>Step 1:</strong> Log in to the Student Portal or Parent Portal at <a target="_blank" rel="noopener noreferrer nofollow" href="http://portal.asia-vn.edu.vn/login" style="color: rgb(17, 85, 204);">portal.asia-vn.edu.vn/login</a> using your registered personal email.</span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);"><strong>Step 2:</strong> Select <strong>Fee &amp; Finance → DNG Payment</strong></span></p>
        <ul style="margin: 8px 0 8px 20px; padding: 0;">
            <li><p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Choose the QR code to make a bank transfer; or</span></p></li>
            <li><p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Select FPT Pay if you wish to pay in installments and follow the instructions on the system.</span></p></li>
        </ul>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);"><strong>Step 3:</strong> Select your preferred payment method (QR Code or FPT Pay) and complete the tuition fee payment.</span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Please complete your payment via the Student Portal or Parent Portal before the deadline. Late payment may result in restrictions on academic system access and course participation.</span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);"><strong><em>Note:</em></strong> <em>The University only receives tuition fees through a single system, the Student Portal/Parent Portal. If students or parents have any questions, please reply to this email, submit a query through the system, or contact us via the hotline.</em></span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Regards.</span></p>

        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);">----------------------</span></p>

        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Thân gửi <strong>{$parentName},</strong></span></p>
        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);">Phòng Dịch vụ Sinh viên (Student Services) xin thông báo nhắc nhở về việc thanh toán học phí cho <strong>{$studentName}</strong> <strong>{$semesterCode}</strong>. Vui lòng bỏ qua email này nếu sinh viên đã thanh toán học phí.</span></p>
        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Vui lòng xem chi tiết thông tin học phí như sau:</strong></span></p>
        <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
            <tbody>
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Tên sinh viên:</span></td>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{$studentName}</strong></span></td>
                </tr>
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Số hoá đơn (No.):</span></td>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{$invoiceCode}</strong></span></td>
                </tr>
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Số tiền còn nợ:</span></td>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{$balance}</strong> VND</span></td>
                </tr>
                <tr>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Hạn thanh toán:</span></td>
                    <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);"><strong>{$dueDate}</strong></span></td>
                </tr>
            </tbody>
        </table>
        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Hình thức thanh toán:&nbsp;</strong></span></p>
        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);">Vui lòng hoàn tất thanh toán qua Cổng Sinh viên hoặc Cổng Phụ huynh (chỉ chọn MỘT phương thức):</span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);"><strong>Bước 1:</strong> Đăng nhập Student Portal hoặc Parents Portal tại <a target="_blank" rel="noopener noreferrer nofollow" href="http://portal.asia-vn.edu.vn/login" style="color: rgb(17, 85, 204);">portal.asia-vn.edu.vn/login</a> bằng email cá nhân đã đăng ký</span></p>
        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Bước 2:</strong> Chọn mục <strong>Fee &amp; Finance</strong> → <strong>DNG Payment</strong></span></p>
        <ul style="margin: 8px 0 8px 20px; padding: 0;">
            <li><p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Chọn QR code để chuyển khoản qua ngân hàng; hoặc</strong></span></p></li>
            <li><p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Chọn FPT Pay nếu có nhu cầu trả góp và làm theo hướng dẫn trên hệ thống.</strong></span></p></li>
        </ul>
        <p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Bước 3:</strong> Lựa chọn phương thức thanh toán: QR Code hoặc FPT Pay và hoàn tất thanh toán học phí</span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Vui lòng hoàn tất thanh toán qua Cổng Sinh viên hoặc Cổng Phụ huynh đúng thời hạn. Việc thanh toán trễ có thể dẫn đến việc bị hạn chế truy cập hệ thống học tập và tham gia môn học.</span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);"><strong><em>Lưu ý:</em></strong> <em>Nhà trường chỉ thu học phí qua một hệ thống duy nhất là cổng Student Portal/Parent Portal. Nếu sinh viên hoặc phụ huynh có bất kỳ thắc mắc nào, vui lòng phản hồi email này, gửi yêu cầu qua hệ thống queries hoặc liên hệ hotline để được hỗ trợ.</em></span></p>
        <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Trân trọng.</span></p>

        </body>
        </html>
        HTML;
    }

    public function textBody(array $data): ?string
    {
        return null;
    }
}