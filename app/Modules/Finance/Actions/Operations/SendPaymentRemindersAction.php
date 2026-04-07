<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Services\EmailService;

class SendPaymentRemindersAction
{
    public static function run(array $data): array
    {
        $invoiceIds = $data['invoice_ids'];
        $sentCount = 0;
        $failedCount = 0;

        $invoices = StudentInvoice::whereIn('id', $invoiceIds)
            ->with('student:id,student_id,full_name,email')
            ->get();

        $settlementService = app(SettlementService::class);
        $emailService = app(EmailService::class);
        $now = now();

        foreach ($invoices as $invoice) {
            $student = $invoice->student;

            if (empty($student?->email)) {
                $failedCount++;
                continue;
            }

            $snapshot = $settlementService->deriveInvoiceSnapshot($invoice);
            $balance = (float) $snapshot['remaining'];

            if ($balance <= 0) {
                $failedCount++;
                continue;
            }

            $dueDate = $invoice->due_date?->format('d/m/Y') ?? 'N/A';
            $formattedBalance = number_format($balance, 0, ',', '.') . ' VNĐ';
            $isOverdue = $invoice->due_date && $invoice->due_date->isPast();

            $subject = $isOverdue
                ? "Thông báo quá hạn thanh toán - Hóa đơn {$invoice->invoice_number}"
                : "Nhắc nhở thanh toán học phí - Hóa đơn {$invoice->invoice_number}";

            $htmlContent = self::buildEmailHtml(
                studentName: $student->full_name,
                invoiceNumber: $invoice->invoice_number,
                balance: $formattedBalance,
                dueDate: $dueDate,
                isOverdue: $isOverdue,
            );

            try {
                // Uses active EmailConfiguration (fallback to .env if none configured)
                $emailService->sendSingleEmail(
                    recipient: $student->email,
                    subject: $subject,
                    content: $htmlContent,
                );

                $invoice->update(['last_reminder_at' => $now]);
                $sentCount++;
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'message' => "Đã gửi {$sentCount} nhắc nợ thành công" . ($failedCount > 0 ? ", {$failedCount} thất bại" : ''),
        ];
    }

    private static function buildEmailHtml(
        string $studentName,
        string $invoiceNumber,
        string $balance,
        string $dueDate,
        bool $isOverdue,
    ): string {
        $statusText = $isOverdue
            ? '<span style="color:#dc2626;font-weight:bold;">ĐÃ QUÁ HẠN</span>'
            : '<span style="color:#d97706;font-weight:bold;">SẮP ĐẾN HẠN</span>';

        return <<<HTML
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e5e7eb;border-radius:8px;">
            <h2 style="color:#1e40af;margin-bottom:16px;">Thông báo thanh toán học phí</h2>
            <p>Kính gửi <strong>{$studentName}</strong>,</p>
            <p>Chúng tôi xin thông báo về hóa đơn học phí của bạn hiện có trạng thái: {$statusText}</p>
            <table style="width:100%;border-collapse:collapse;margin:16px 0;">
                <tr style="background:#f3f4f6;">
                    <td style="padding:10px;border:1px solid #e5e7eb;font-weight:bold;">Số hóa đơn</td>
                    <td style="padding:10px;border:1px solid #e5e7eb;">{$invoiceNumber}</td>
                </tr>
                <tr>
                    <td style="padding:10px;border:1px solid #e5e7eb;font-weight:bold;">Số tiền còn nợ</td>
                    <td style="padding:10px;border:1px solid #e5e7eb;color:#dc2626;font-weight:bold;">{$balance}</td>
                </tr>
                <tr style="background:#f3f4f6;">
                    <td style="padding:10px;border:1px solid #e5e7eb;font-weight:bold;">Hạn thanh toán</td>
                    <td style="padding:10px;border:1px solid #e5e7eb;">{$dueDate}</td>
                </tr>
            </table>
            <p>Vui lòng hoàn tất thanh toán để tránh ảnh hưởng đến việc học. Nếu bạn đã thanh toán, vui lòng bỏ qua email này.</p>
            <p style="color:#6b7280;font-size:12px;margin-top:24px;">Email này được gửi tự động từ hệ thống quản lý tài chính.</p>
        </div>
        HTML;
    }
}
