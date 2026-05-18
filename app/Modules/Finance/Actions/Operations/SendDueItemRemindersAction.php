<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Services\EmailService;

class SendDueItemRemindersAction
{
    public static function run(array $data): array
    {
        $itemIds = $data['item_ids'];
        $sentCount = 0;
        $failedCount = 0;
        $skippedNoDebtCount = 0;
        $skippedNoStudentEmailCount = 0;

        $settlementService = app(SettlementService::class);
        $emailService = app(EmailService::class);
        $emailContent = app(EmailContentRegistry::class)->resolve('payment_reminder');
        $now = now();

        foreach ($itemIds as $itemId) {
            $parts = explode(':', $itemId);
            if (count($parts) !== 2) {
                \Log::warning('Invalid item ID format', ['item_id' => $itemId]);
                $failedCount++;

                continue;
            }

            [$type, $id] = $parts;
            $id = (int) $id;

            \Log::info('Processing reminder item', ['type' => $type, 'id' => $id]);

            if ($type === 'dng_request') {
                // Handle DNG Payment Request
                $dngRequest = DngPaymentRequest::find($id);
                if (! $dngRequest || $dngRequest->status !== 'pushed_to_dng') {
                    $failedCount++;

                    continue;
                }

                $student = $dngRequest->student;
                $balance = (float) $dngRequest->amount;

                if (! $student) {
                    \Log::warning('DNG request has no student', ['dng_request_id' => $id]);
                    $failedCount++;

                    continue;
                }

                if (! is_string($student->email) || trim($student->email) === '') {
                    \Log::info('Skipping DNG reminder - no student email', [
                        'dng_request_id' => $id,
                        'student_id' => $student->id,
                    ]);
                    $skippedNoStudentEmailCount++;

                    continue;
                }

                $contentData = [
                    'campus_id' => $student->campus_id,
                    'student_name' => $student->full_name,
                    'student_code' => $student->student_id,
                    'semester_code' => $dngRequest->semester?->code ?? '',
                    'invoice_code' => 'DNG-'.$dngRequest->id,
                    'balance_formatted' => number_format($balance, 0, ',', '.'),
                    'due_date' => $dngRequest->due_date?->format('d/m/Y') ?? '',
                ];

                try {
                    $emailService->sendSingleEmail(
                        recipient: $student->email,
                        subject: $emailContent->subject($contentData),
                        content: $emailContent->htmlBody($contentData),
                        campusId: $student->campus_id,
                    );

                    // Update last_reminder_at for DNG request
                    $dngRequest->update(['last_reminder_at' => $now]);

                    $sentCount++;
                } catch (\Throwable $e) {
                    \Log::error('Failed to send DNG reminder', [
                        'dng_request_id' => $id,
                        'student_id' => $student->id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    $failedCount++;
                }
            } elseif ($type === 'invoice') {
                // Handle Student Invoice
                $invoice = StudentInvoice::find($id);
                if (! $invoice) {
                    $failedCount++;

                    continue;
                }

                $student = $invoice->student;
                $snapshot = $settlementService->deriveInvoiceSnapshot($invoice);
                $balance = (float) $snapshot['remaining'];

                if (! $student) {
                    \Log::warning('Invoice has no student', ['invoice_id' => $id]);
                    $failedCount++;

                    continue;
                }

                if ($balance <= 0) {
                    \Log::info('Skipping invoice reminder - no debt', [
                        'invoice_id' => $id,
                        'balance' => $balance,
                    ]);
                    $skippedNoDebtCount++;

                    continue;
                }

                if (! is_string($student->email) || trim($student->email) === '') {
                    \Log::info('Skipping invoice reminder - no student email', [
                        'invoice_id' => $id,
                        'student_id' => $student->id,
                    ]);
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
                    $emailService->sendSingleEmail(
                        recipient: $student->email,
                        subject: $emailContent->subject($contentData),
                        content: $emailContent->htmlBody($contentData),
                        campusId: $student->campus_id,
                    );

                    $invoice->update(['last_reminder_at' => $now]);
                    $sentCount++;
                } catch (\Throwable $e) {
                    \Log::error('Failed to send invoice reminder', [
                        'invoice_id' => $id,
                        'student_id' => $student->id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    $failedCount++;
                }
            } else {
                \Log::warning('Invalid item type for reminder', [
                    'item_id' => $itemId,
                    'type' => $type ?? 'unknown',
                ]);
                $failedCount++;
            }
        }

        \Log::info('Reminder sending completed', [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'skipped_no_debt_count' => $skippedNoDebtCount,
            'skipped_no_student_email_count' => $skippedNoStudentEmailCount,
        ]);

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
            $parts[] = "{$skippedNoDebtCount} mục không còn nợ";
        }

        if ($skippedNoStudentEmailCount > 0) {
            $parts[] = "{$skippedNoStudentEmailCount} mục không có email sinh viên";
        }

        return implode(', ', $parts);
    }
}
