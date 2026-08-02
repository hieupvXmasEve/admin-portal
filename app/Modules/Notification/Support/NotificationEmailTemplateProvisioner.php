<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;

/**
 * Provisions the default notification_email_templates rows for a campus.
 *
 * Used both by the data seed migration (initial backfill against pre-existing
 * campuses) and by the CampusObserver (auto-provision when a new campus is
 * created post-deploy or via factory in tests). Subject + HTML body literals
 * are copied verbatim from the legacy seed migration; mutate-on-edit happens
 * later via the admin template editor (P2).
 *
 * Idempotent per campus via firstOrCreate on the unique (campus_id, type_key) key.
 */
class NotificationEmailTemplateProvisioner
{
    public function provisionForCampus(int $campusId): void
    {
        $defaults = $this->defaults();

        foreach (NotificationTemplateTypeKey::cases() as $typeKey) {
            // Some type_keys (e.g. installment_payment_reminder) are intentionally
            // created on demand by admins via the /admin/notification-templates UI
            // rather than pre-seeded. Skip them here so adding a new enum case
            // does not require updating this provisioner.
            if (! array_key_exists($typeKey->value, $defaults)) {
                continue;
            }

            $payload = $defaults[$typeKey->value];

            NotificationEmailTemplate::firstOrCreate(
                [
                    'campus_id' => $campusId,
                    'type_key' => $typeKey->value,
                ],
                [
                    'subject' => $payload['subject'],
                    'body_html' => $payload['body_html'],
                ],
            );
        }
    }

    /**
     * @return array<string, array{subject: string, body_html: string}>
     */
    private function defaults(): array
    {
        return [
            NotificationTemplateTypeKey::PaymentReminder->value => [
                'subject' => '[Asia Việt Nam] Reminder: Tuition Fee Payment {{semester_code}}_Nhắc nhở thanh toán học phí',
                'body_html' => <<<'HTML'
                <!DOCTYPE html>
                <html lang="vi">
                <head>
                    <meta charset="UTF-8">
                </head>
                <body style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 24px;">

                <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Dear <strong>{{student_name}} {{student_code}},</strong></span></p>
                <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">This is a reminder to complete your tuition fee payment for <strong>{{semester_code}}</strong>. Please disregard this email if the tuition fee has already been paid.</span></p>
                <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Please find the detailed tuition information below:</span></p>
                <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                    <tbody>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);">Invoice No.:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{invoice_code}}</strong></span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Tuition Fee – <strong>{{semester_code}}:</strong></span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{balance_formatted}}</strong> VND</span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);">Payment Deadline:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);"><strong>{{due_date}}</strong></span></td>
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

                <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Thân gửi <strong>{{student_name}} {{student_code}},</strong></span></p>
                <p style="text-align: left;"><span style="color: rgb(34, 34, 34);">Phòng Dịch vụ Sinh viên (Student Services) xin thông báo nhắc nhở về việc thanh toán học phí</span><span style="color: rgb(0, 0, 0);"> cho <strong>{{semester_code}}</strong>. Vui lòng bỏ qua email này nếu sinh viên đã thanh toán học phí.</span></p>
                <p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Vui lòng xem chi tiết thông tin học phí như sau:</strong></span></p>
                <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                    <tbody>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Số hoá đơn (No.):</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{invoice_code}}</strong></span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Số tiền còn nợ:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{balance_formatted}}</strong> VND</span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Hạn thanh toán:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);"><strong>{{due_date}}</strong></span></td>
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
                HTML,
            ],

            NotificationTemplateTypeKey::ParentPaymentReminder->value => [
                'subject' => '[Asia Việt Nam] Reminder: Tuition Fee Payment {{semester_code}}_Nhắc nhở thanh toán học phí',
                'body_html' => <<<'HTML'
                <!DOCTYPE html>
                <html lang="vi">
                <head>
                    <meta charset="UTF-8">
                </head>
                <body style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 24px;">

                <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Dear <strong>{{parent_name}},</strong></span></p>
                <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">This is a reminder to complete the tuition fee payment for <strong>{{student_name}} {{student_code}}</strong> for <strong>{{semester_code}}</strong>. Please disregard this email if the tuition fee has already been paid.</span></p>
                <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Please find the detailed tuition information below:</span></p>
                <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                    <tbody>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);">Student Name:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{student_name}}</strong></span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);">Invoice No.:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{invoice_code}}</strong></span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Tuition Fee – <strong>{{semester_code}}:</strong></span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{balance_formatted}}</strong> VND</span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);">Payment Deadline:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);"><strong>{{due_date}}</strong></span></td>
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

                <p style="text-align: left;"><span style="color: rgb(0, 0, 0);">Thân gửi <strong>{{parent_name}},</strong></span></p>
                <p style="text-align: left;"><span style="color: rgb(34, 34, 34);">Phòng Dịch vụ Sinh viên (Student Services) xin thông báo nhắc nhở về việc thanh toán học phí cho <strong>{{student_name}}</strong> <strong>{{semester_code}}</strong>. Vui lòng bỏ qua email này nếu sinh viên đã thanh toán học phí.</span></p>
                <p style="text-align: left;"><span style="color: rgb(34, 34, 34);"><strong>Vui lòng xem chi tiết thông tin học phí như sau:</strong></span></p>
                <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                    <tbody>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Tên sinh viên:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{student_name}}</strong></span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Số hoá đơn (No.):</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{invoice_code}}</strong></span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Số tiền còn nợ:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(0, 0, 0);"><strong>{{balance_formatted}}</strong> VND</span></td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);">Hạn thanh toán:</span></td>
                            <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><span style="color: rgb(34, 34, 34);"><strong>{{due_date}}</strong></span></td>
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
                HTML,
            ],

            NotificationTemplateTypeKey::DngPaymentPushed->value => [
                'subject' => '[Asia Việt Nam] Tuition Fee Payment Notice - Thông báo học phí học kỳ {{semester_code}}',
                'body_html' => <<<'HTML'
                <!DOCTYPE html>
                <html lang="vi">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                </head>
                <body style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 24px;">

                    <p>Thân gửi <strong>{{student_name}} {{student_code}}</strong>,</p>

                    <p>Phòng Dịch vụ Sinh viên (Student Services) xin thông báo về việc thanh toán học phí Học kỳ <strong>{{semester_code}}</strong>, áp dụng cho sinh viên hệ <strong>{{program_name}}</strong>.</p>

                    <p><em>Vui lòng bỏ qua email này nếu sinh viên đã thanh toán học phí.</em></p>

                    <p>Vui lòng xem chi tiết thông tin học phí như sau:</p>

                    <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                        <tbody>
                            <tr>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Số hoá đơn (No.):</strong></td>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{invoice_code}}</strong></td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Số tiền còn nợ:</strong></td>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{amount_formatted}}</strong> VND</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Hạn thanh toán:</strong></td>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{due_date}}</strong></td>
                            </tr>
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

                    <p>Dear <strong>{{student_name}} {{student_code}}</strong>,</p>

                    <p>The Student Services Department would like to inform you about the tuition fee payment for the <strong>{{semester_code}}</strong> applicable to students enrolled in the <strong>{{program_name}}</strong>.</p>

                    <p><em>Please disregard this email if the tuition fee has already been paid.</em></p>

                    <p>Please find the detailed tuition information below:</p>

                    <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                        <tbody>
                            <tr>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;">Invoice No.:</td>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{invoice_code}}</strong></td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;">Tuition Fee{{semester_code}}:</td>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{amount_formatted}}</strong> VND</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;">Payment Deadline:</td>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{due_date}}</strong></td>
                            </tr>
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
                HTML,
            ],

            NotificationTemplateTypeKey::DngPaymentReceived->value => [
                'subject' => '[Asia Việt Nam] Xác nhận thanh toán thành công - Học kỳ {{semester_code}}',
                'body_html' => <<<'HTML'
                <!DOCTYPE html>
                <html lang="vi">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                </head>
                <body style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 24px;">

                    <p>Thân gửi <strong>{{student_name}} {{student_code}}</strong>,</p>

                    <p>Phòng Dịch vụ Sinh viên (Student Services) xác nhận đã nhận được khoản thanh toán học phí của bạn.</p>

                    <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                        <tbody>
                            <tr>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Số tiền đã thanh toán:</strong></td>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{amount_formatted}}</strong> VND</td>
                            </tr>
                            <tr><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Học kỳ:</strong></td><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{semester_code}}</strong></td></tr>
                            <tr><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Thời gian nhận tiền:</strong></td><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{paid_at}}</strong></td></tr>
                        </tbody>
                    </table>

                    <p>Nếu bạn có bất kỳ thắc mắc nào, vui lòng phản hồi email này hoặc gửi yêu cầu qua hệ thống queries.</p>
                    <p>Trân trọng.</p>

                    <hr style="border: none; border-top: 1px solid #ccc; margin: 24px 0;">

                    <p>Dear <strong>{{student_name}} {{student_code}}</strong>,</p>

                    <p>The Student Services Department confirms that your tuition fee payment has been successfully received.</p>

                    <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                        <tbody>
                            <tr>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;">Amount paid:</td>
                                <td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{amount_formatted}}</strong> VND</td>
                            </tr>
                            <tr><td style="border: 1px solid #d1d5db; padding: 8px 16px;">Semester:</td><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{semester_code}}</strong></td></tr>
                            <tr><td style="border: 1px solid #d1d5db; padding: 8px 16px;">Payment received at:</td><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{paid_at}}</strong></td></tr>
                        </tbody>
                    </table>

                    <p>If you have any questions, please reply to this email or submit a query via the system.</p>
                    <p><em>Regards.</em></p>

                </body>
                </html>
                HTML,
            ],
            NotificationTemplateTypeKey::ScholarshipAdjustmentConfirmationRequested->value => [
                'subject' => '[Asia Việt Nam] Cần xác nhận biên bản xét học bổng - Học kỳ {{semester_code}}',
                'body_html' => <<<'HTML'
                <!DOCTYPE html>
                <html lang="vi">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                </head>
                <body style="font-family: Arial, sans-serif; color: #222; font-size: 14px; line-height: 1.6; max-width: 680px; margin: 0 auto; padding: 24px;">

                    <p>Thân gửi <strong>{{student_name}} {{student_code}}</strong>,</p>

                    <p>Nhà trường đã hoàn tất buổi trao đổi về kết quả học tập của bạn và lập biên bản để xét lại mức học bổng cho học kỳ <strong>{{semester_code}}</strong>.</p>

                    <p>Bạn vui lòng đăng nhập Student Portal, đọc biên bản và cho biết bạn <strong>đồng ý</strong> hay <strong>không đồng ý</strong> với nội dung đó. Nếu có điểm nào chưa đúng, hãy ghi rõ ý kiến khi phản hồi.</p>

                    <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                        <tbody>
                            <tr><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Học kỳ áp dụng:</strong></td><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{semester_code}}</strong></td></tr>
                            <tr><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>Hạn phản hồi:</strong></td><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{deadline}}</strong></td></tr>
                        </tbody>
                    </table>

                    <p><strong>Chưa có quyết định nào được đưa ra</strong> và mức học bổng của bạn chưa thay đổi. Nhà trường chỉ ra quyết định sau khi nhận được phản hồi của bạn.</p>

                    <p>Nếu quá hạn trên mà không có phản hồi, nhà trường sẽ liên hệ trực tiếp và có thể tiếp tục xử lý hồ sơ.</p>

                    <p>Nếu bạn có bất kỳ thắc mắc nào, vui lòng phản hồi email này hoặc gửi yêu cầu qua hệ thống queries.</p>
                    <p>Trân trọng.</p>

                    <hr style="border: none; border-top: 1px solid #ccc; margin: 24px 0;">

                    <p>Dear <strong>{{student_name}} {{student_code}}</strong>,</p>

                    <p>We have completed the interview about your academic results and recorded minutes for the review of your scholarship in <strong>{{semester_code}}</strong>.</p>

                    <p>Please sign in to the Student Portal, read the minutes, and tell us whether you <strong>agree</strong> or <strong>disagree</strong>. If anything is wrong, say what it is when you reply.</p>

                    <table style="border-collapse: collapse; width: 100%; margin-bottom: 16px;">
                        <tbody>
                            <tr><td style="border: 1px solid #d1d5db; padding: 8px 16px;">Semester concerned:</td><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{semester_code}}</strong></td></tr>
                            <tr><td style="border: 1px solid #d1d5db; padding: 8px 16px;">Reply by:</td><td style="border: 1px solid #d1d5db; padding: 8px 16px;"><strong>{{deadline}}</strong></td></tr>
                        </tbody>
                    </table>

                    <p><strong>No decision has been made yet</strong> and your scholarship is unchanged. A decision follows only after we hear from you.</p>

                    <p>If we do not hear from you by then, we will contact you directly and the review may continue.</p>

                    <p>If you have any questions, please reply to this email or submit a query via the system.</p>
                    <p><em>Regards.</em></p>

                </body>
                </html>
                HTML,
            ],
        ];
    }
}
