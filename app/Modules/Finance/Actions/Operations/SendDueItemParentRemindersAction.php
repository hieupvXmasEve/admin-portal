<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\ParentProfile;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\DngInstallmentContextResolver;
use App\Modules\Finance\Support\ExamResitDngLinkResolver;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use App\Shared\Contracts\Notification\EmailContentResolver;
use App\Shared\Contracts\Notification\ExternalEmailNotification;
use App\Shared\Contracts\Notification\ExternalEmailPublisher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendDueItemParentRemindersAction
{
    public static function run(array $data): array
    {
        $itemIds = $data['item_ids'];
        $sentCount = 0;
        $failedCount = 0;
        $skippedNoDebtCount = 0;
        $skippedNoParentEmailCount = 0;
        $skippedLifecycleExceptionCount = 0;

        $settlementService = app(SettlementService::class);
        $externalEmailPublisher = app(ExternalEmailPublisher::class);
        $registry = app(EmailContentResolver::class);
        $installmentResolver = app(DngInstallmentContextResolver::class);
        $emailContent = $registry->resolve('parent_payment_reminder');
        $installmentEmailContent = $registry->resolve('parent_installment_payment_reminder');
        $now = now();

        foreach ($itemIds as $itemId) {
            $parts = explode(':', $itemId);
            if (count($parts) !== 2) {
                $failedCount++;

                continue;
            }

            [$type, $id] = $parts;
            $id = (int) $id;

            if ($type === 'dng_request') {
                // Handle DNG Payment Request
                $dngRequest = DngPaymentRequest::find($id);
                if (! $dngRequest || $dngRequest->status !== 'pushed_to_dng') {
                    $failedCount++;

                    continue;
                }

                $student = $dngRequest->student;

                if (LifecycleDueItemPredicate::isLifecycleException($student)) {
                    Log::info('Skipping DNG parent reminder - lifecycle exception', [
                        'dng_request_id' => $id,
                        'student_id' => $student?->id,
                        'student_status' => $student?->status,
                    ]);
                    $skippedLifecycleExceptionCount++;

                    continue;
                }

                if (! $student) {
                    Log::warning('DNG request has no student', ['dng_request_id' => $id]);
                    $failedCount++;

                    continue;
                }

                $balanceContext = $installmentResolver->currentBalance($dngRequest);
                if ($balanceContext['amount'] === null) {
                    Log::warning('Skipping DNG parent reminder - Settlement Position requires review', [
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

                $parentEmails = self::extractParentEmails($student?->parentProfiles);

                if ($parentEmails->isEmpty()) {
                    Log::info('Skipping DNG parent reminder - no parent emails', [
                        'dng_request_id' => $id,
                        'student_id' => $student->id,
                    ]);
                    $skippedNoParentEmailCount++;

                    continue;
                }

                $installmentContext = $installmentResolver->resolve($dngRequest);
                $useInstallment = $installmentContext !== null
                    && self::installmentTemplateExists('parent_installment_payment_reminder', (int) $student->campus_id);

                if ($installmentContext !== null && ! $useInstallment) {
                    Log::warning(
                        'Parent installment template not provisioned for campus; falling back to parent_payment_reminder',
                        ['campus_id' => $student->campus_id, 'dng_request_id' => $id],
                    );
                }

                $providerForDng = $useInstallment ? $installmentEmailContent : $emailContent;

                $contentData = [
                    'campus_id' => $student->campus_id,
                    'parent_name' => 'Quý Phụ Huynh',
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

                $sentAnyParent = false;

                foreach ($parentEmails as $parentEmail) {
                    try {
                        $externalEmailPublisher->publishAfterCommit(new ExternalEmailNotification(
                            recipientEmails: [$parentEmail],
                            subject: $providerForDng->subject($contentData),
                            html: $providerForDng->htmlBody($contentData),
                            campusId: $student->campus_id,
                            aggregateType: 'dng_payment_request',
                            aggregateId: (string) $dngRequest->id,
                            typeKey: $useInstallment ? 'parent_installment_payment_reminder' : 'parent_payment_reminder',
                        ));

                        $sentCount++;
                        $sentAnyParent = true;
                        Log::info('Parent reminder sent successfully', [
                            'dng_request_id' => $id,
                            'student_id' => $student->id,
                            'parent_email' => $parentEmail,
                        ]);
                    } catch (\Throwable $e) {
                        Log::error('Failed to send parent reminder email', [
                            'dng_request_id' => $id,
                            'student_id' => $student->id,
                            'parent_email' => $parentEmail,
                            'error' => $e->getMessage(),
                        ]);
                        $failedCount++;
                    }
                }

                // Update last_reminder_at for DNG request if any parent email was sent
                if ($sentAnyParent) {
                    $dngRequest->update(['last_reminder_at' => $now]);
                    self::mirrorExamResitReminder($dngRequest, $now);
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

                if ($balance <= 0) {
                    $skippedNoDebtCount++;

                    continue;
                }

                if (! is_string($student?->parent_email) || trim($student->parent_email) === '') {
                    $skippedNoParentEmailCount++;

                    continue;
                }

                $contentData = [
                    'campus_id' => $student->campus_id,
                    'parent_name' => $student->parent_name ?? 'Quý Phụ Huynh',
                    'student_name' => $student->full_name,
                    'student_code' => $student->student_id,
                    'semester_code' => $invoice->semester?->code ?? '',
                    'invoice_code' => $invoice->invoice_number,
                    'balance_formatted' => number_format($balance, 0, ',', '.'),
                    'due_date' => $invoice->due_date?->format('d/m/Y') ?? '',
                ];

                try {
                    $externalEmailPublisher->publishAfterCommit(new ExternalEmailNotification(
                        recipientEmails: [trim($student->parent_email)],
                        subject: $emailContent->subject($contentData),
                        html: $emailContent->htmlBody($contentData),
                        campusId: $student->campus_id,
                        aggregateType: 'student_invoice',
                        aggregateId: (string) $invoice->id,
                        typeKey: 'parent_payment_reminder',
                    ));

                    $invoice->update(['last_reminder_at' => $now]);
                    $sentCount++;
                } catch (\Throwable $e) {
                    Log::error('Failed to send invoice parent reminder', [
                        'invoice_id' => $id,
                        'student_id' => $student->id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    $failedCount++;
                }
            } else {
                $failedCount++;
            }
        }

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'skipped_no_debt_count' => $skippedNoDebtCount,
            'skipped_no_parent_email_count' => $skippedNoParentEmailCount,
            'skipped_lifecycle_exception_count' => $skippedLifecycleExceptionCount,
            'message' => self::buildSummaryMessage(
                $sentCount,
                $failedCount,
                $skippedNoDebtCount,
                $skippedNoParentEmailCount,
                $skippedLifecycleExceptionCount,
            ),
        ];
    }

    /**
     * Mirror a successful DNG parent reminder onto the linked exam-resit
     * attempt(s) (ACAD-RET-002). Additive only — never changes recipient
     * selection or delivery.
     */
    private static function mirrorExamResitReminder(DngPaymentRequest $dngRequest, \DateTimeInterface $now): void
    {
        $touched = app(ExamResitDngLinkResolver::class)->touchLinkedAttempts($dngRequest, $now);

        if ($touched > 0) {
            Log::info('Mirrored exam-resit reminder timestamp', [
                'dng_request_id' => $dngRequest->id,
                'exam_resit_attempts_touched' => $touched,
                'recipient' => 'parent',
            ]);
        }
    }

    private static function extractParentEmails(?Collection $parentProfiles): Collection
    {
        if ($parentProfiles === null) {
            return collect();
        }

        return $parentProfiles
            ->filter(function (ParentProfile $profile): bool {
                $user = $profile->user;

                return $profile->status === 'active'
                    && $user !== null
                    && $user->isParent()
                    && $user->isActive();
            })
            ->map(fn (ParentProfile $profile) => $profile->user?->email)
            ->filter(fn ($email) => is_string($email) && trim($email) !== '')
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->unique()
            ->values();
    }

    /** @var array<string, bool> */
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
        int $skippedNoParentEmailCount,
        int $skippedLifecycleExceptionCount = 0,
    ): string {
        $parts = ["Đã gửi {$sentCount} email nhắc nợ cho phụ huynh"];

        if ($failedCount > 0) {
            $parts[] = "{$failedCount} email thất bại";
        }

        if ($skippedNoDebtCount > 0) {
            $parts[] = "{$skippedNoDebtCount} mục không còn nợ";
        }

        if ($skippedNoParentEmailCount > 0) {
            $parts[] = "{$skippedNoParentEmailCount} mục không có email phụ huynh";
        }

        if ($skippedLifecycleExceptionCount > 0) {
            $parts[] = "{$skippedLifecycleExceptionCount} mục ngoại lệ lifecycle bị bỏ qua";
        }

        return implode(', ', $parts);
    }
}
