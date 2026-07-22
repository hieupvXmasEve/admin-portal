<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Support\Facades\DB;

class SendPaymentRemindersAction
{
    public static function run(array $data): array
    {
        $invoiceIds = $data['invoice_ids'];
        $sentCount = 0;
        $failedCount = 0;
        $skippedNoDebtCount = 0;
        $skippedNoStudentEmailCount = 0;

        $invoices = StudentInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->with(['student:id,student_id,full_name,email,campus_id', 'semester:id,code'])
            ->get();

        $settlementService = app(SettlementService::class);
        $now = now();

        foreach ($invoices as $invoice) {
            $student = $invoice->student;
            $snapshot = $settlementService->deriveInvoiceSnapshot($invoice);
            $balance = (float) $snapshot['remaining'];

            if ($balance <= 0) {
                $skippedNoDebtCount++;

                continue;
            }

            if (! is_string($student?->email) || trim($student->email) === '') {
                $skippedNoStudentEmailCount++;

                continue;
            }

            $contentData = [
                'campus_id' => $student->campus_id,
                'student_name' => $student->full_name,
                'student_code' => $student->student_id,
                'semester_code' => $invoice->semester?->code ?? '',
                'invoice_code' => $invoice->invoice_number,
                'balance_formatted' => number_format($balance, 0, ',', '.'),
                'due_date' => $invoice->due_date?->format('d/m/Y') ?? '',
            ];

            try {
                DB::transaction(function () use ($invoice, $student, $contentData, $now): void {
                    $invoice->update(['last_reminder_at' => $now]);
                    PublishReminderNotificationAction::run([
                        'type_key' => 'payment_reminder',
                        'aggregate_type' => 'student_invoice',
                        'aggregate_id' => $invoice->id,
                        'campus_id' => (int) $student->campus_id,
                        'recipient_type' => 'student',
                        'recipient_id' => (int) $student->id,
                        'data' => $contentData,
                    ]);
                });

                $sentCount++;
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'skipped_no_debt_count' => $skippedNoDebtCount,
            'skipped_no_student_email_count' => $skippedNoStudentEmailCount,
            'message' => self::buildSummaryMessage(
                $sentCount,
                $failedCount,
                $skippedNoDebtCount,
                $skippedNoStudentEmailCount,
            ),
        ];
    }

    private static function buildSummaryMessage(
        int $sentCount,
        int $failedCount,
        int $skippedNoDebtCount,
        int $skippedNoStudentEmailCount
    ): string {
        $parts = ["Đã gửi {$sentCount} email nhắc nợ cho sinh viên"];

        if ($failedCount > 0) {
            $parts[] = "{$failedCount} email thất bại";
        }

        if ($skippedNoDebtCount > 0) {
            $parts[] = "{$skippedNoDebtCount} invoice không còn nợ";
        }

        if ($skippedNoStudentEmailCount > 0) {
            $parts[] = "{$skippedNoStudentEmailCount} invoice không có email sinh viên";
        }

        return implode(', ', $parts);
    }
}
