<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\DngInstallmentContextResolver;
use App\Modules\Finance\Support\ExamResitDngLinkResolver;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use App\Shared\Contracts\Notification\EmailContentResolver;
use Illuminate\Support\Facades\DB;

class SendDueItemRemindersAction
{
    public static function run(array $data): array
    {
        $itemIds = $data['item_ids'];
        $sentCount = 0;
        $failedCount = 0;
        $skippedNoDebtCount = 0;
        $skippedNoStudentEmailCount = 0;
        $skippedLifecycleExceptionCount = 0;

        $settlementService = app(SettlementService::class);
        $registry = app(EmailContentResolver::class);
        $installmentResolver = app(DngInstallmentContextResolver::class);
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

                if (LifecycleDueItemPredicate::isLifecycleException($student)) {
                    \Log::info('Skipping DNG reminder - lifecycle exception', [
                        'dng_request_id' => $id,
                        'student_id' => $student?->id,
                        'student_status' => $student?->status,
                    ]);
                    $skippedLifecycleExceptionCount++;

                    continue;
                }

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

                $balanceContext = $installmentResolver->currentBalance($dngRequest);
                if ($balanceContext['amount'] === null) {
                    \Log::warning('Skipping DNG reminder - Settlement Position requires review', [
                        'dng_request_id' => $id,
                        'student_id' => $student->id,
                        'settlement_position_issue_codes' => $balanceContext['issue_codes'],
                    ]);
                    $failedCount++;

                    continue;
                }

                $balance = $balanceContext['amount'];
                if ($balance <= 0) {
                    $skippedNoDebtCount++;

                    continue;
                }

                $installmentContext = $installmentResolver->resolve($dngRequest);
                $useInstallment = $installmentContext !== null
                    && self::installmentTemplateExists('installment_payment_reminder', (int) $student->campus_id);

                if ($installmentContext !== null && ! $useInstallment) {
                    \Log::warning(
                        'Installment template not provisioned for campus; falling back to payment_reminder',
                        ['campus_id' => $student->campus_id, 'dng_request_id' => $id],
                    );
                }

                $typeKeyForDng = $useInstallment ? 'installment_payment_reminder' : 'payment_reminder';

                $contentData = [
                    'campus_id' => $student->campus_id,
                    'student_name' => $student->full_name,
                    'student_code' => $student->student_id,
                    'semester_code' => $dngRequest->semester?->code ?? '',
                    'invoice_code' => 'DNG-'.$dngRequest->id,
                    'balance_formatted' => number_format($balance, 0, ',', '.'),
                    'due_date' => $dngRequest->due_date?->format('d/m/Y') ?? '',
                ];

                if ($useInstallment && $installmentContext !== null) {
                    $contentData = array_merge($contentData, $installmentContext);
                }

                try {
                    DB::transaction(function () use ($dngRequest, $student, $contentData, $now, $typeKeyForDng): void {
                        $dngRequest->update(['last_reminder_at' => $now]);
                        self::mirrorExamResitReminder($dngRequest, $now);
                        PublishReminderNotificationAction::run([
                            'type_key' => $typeKeyForDng,
                            'aggregate_type' => 'dng_payment_request',
                            'aggregate_id' => $dngRequest->id,
                            'campus_id' => (int) $student->campus_id,
                            'recipient_type' => 'student',
                            'recipient_id' => (int) $student->id,
                            'data' => $contentData,
                        ]);
                    });

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
            'skipped_lifecycle_exception_count' => $skippedLifecycleExceptionCount,
        ]);

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'skipped_no_debt_count' => $skippedNoDebtCount,
            'skipped_no_student_email_count' => $skippedNoStudentEmailCount,
            'skipped_lifecycle_exception_count' => $skippedLifecycleExceptionCount,
            'message' => self::buildSummaryMessage(
                $sentCount,
                $failedCount,
                $skippedNoDebtCount,
                $skippedNoStudentEmailCount,
                $skippedLifecycleExceptionCount,
            ),
        ];
    }

    /**
     * Mirror a successful DNG reminder onto the linked exam-resit attempt(s)
     * (ACAD-RET-002). Additive only — recipient selection and delivery are
     * unchanged; this just keeps Academic's `last_reminded_at` in sync.
     */
    private static function mirrorExamResitReminder(DngPaymentRequest $dngRequest, \DateTimeInterface $now): void
    {
        $touched = app(ExamResitDngLinkResolver::class)->touchLinkedAttempts($dngRequest, $now);

        if ($touched > 0) {
            \Log::info('Mirrored exam-resit reminder timestamp', [
                'dng_request_id' => $dngRequest->id,
                'exam_resit_attempts_touched' => $touched,
                'recipient' => 'student',
            ]);
        }
    }

    /**
     * Cached per-(typeKey, campusId) lookup so a batch of 200 students sharing
     * one campus does not hit the DB 200 times for the same template-exists check.
     *
     * @var array<string, bool>
     */
    private static array $templateExistsCache = [];

    private static function installmentTemplateExists(string $typeKey, int $campusId): bool
    {
        $cacheKey = $typeKey.':'.$campusId;
        if (array_key_exists($cacheKey, self::$templateExistsCache)) {
            return self::$templateExistsCache[$cacheKey];
        }

        return self::$templateExistsCache[$cacheKey] = app(EmailContentResolver::class)
            ->isConfiguredForCampus($typeKey, $campusId);
    }

    private static function buildSummaryMessage(
        int $sentCount,
        int $failedCount,
        int $skippedNoDebtCount,
        int $skippedNoStudentEmailCount,
        int $skippedLifecycleExceptionCount = 0,
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

        if ($skippedLifecycleExceptionCount > 0) {
            $parts[] = "{$skippedLifecycleExceptionCount} mục ngoại lệ lifecycle bị bỏ qua";
        }

        return implode(', ', $parts);
    }
}
