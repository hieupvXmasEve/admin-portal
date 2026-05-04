<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Models\ParentProfile;
use App\Services\EmailService;
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

        $settlementService = app(SettlementService::class);
        $emailService = app(EmailService::class);
        $emailContent = app(EmailContentRegistry::class)->resolve('parent_payment_reminder');
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
                $balance = (float) $dngRequest->amount;

                $parentEmails = self::extractParentEmails($student?->parentProfiles);

                if ($parentEmails->isEmpty()) {
                    \Log::info('Skipping DNG parent reminder - no parent emails', [
                        'dng_request_id' => $id,
                        'student_id' => $student->id,
                    ]);
                    $skippedNoParentEmailCount++;
                    continue;
                }

                $contentData = [
                    'parent_name' => 'Quý Phụ Huynh',
                    'student_name' => $student->full_name,
                    'student_code' => $student->student_id,
                    'semester_code' => $dngRequest->semester?->code ?? '',
                    'invoice_code' => 'DNG-' . $dngRequest->id,
                    'balance_formatted' => number_format($balance, 0, ',', '.'),
                    'due_date' => $dngRequest->due_date?->format('d/m/Y') ?? '',
                ];

                $sentAnyParent = false;

                foreach ($parentEmails as $parentEmail) {
                    try {
                        $emailService->sendSingleEmail(
                            recipient: $parentEmail,
                            subject: $emailContent->subject($contentData),
                            content: $emailContent->htmlBody($contentData),
                            campusId: $student->campus_id,
                        );

                        $sentCount++;
                        $sentAnyParent = true;
                        \Log::info('Parent reminder sent successfully', [
                            'dng_request_id' => $id,
                            'student_id' => $student->id,
                            'parent_email' => $parentEmail,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::error('Failed to send parent reminder email', [
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
                    'parent_name' => $student->parent_name ?? 'Quý Phụ Huynh',
                    'student_name' => $student->full_name,
                    'student_code' => $student->student_id,
                    'semester_code' => $invoice->semester?->code ?? '',
                    'invoice_code' => $invoice->invoice_number,
                    'balance_formatted' => number_format($balance, 0, ',', '.'),
                    'due_date' => $invoice->due_date?->format('d/m/Y') ?? '',
                ];

                try {
                    $emailService->sendSingleEmail(
                        recipient: $student->parent_email,
                        subject: $emailContent->subject($contentData),
                        content: $emailContent->htmlBody($contentData),
                        campusId: $student->campus_id,
                    );

                    $invoice->update(['last_reminder_at' => $now]);
                    $sentCount++;
                } catch (\Throwable $e) {
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
            'message' => self::buildSummaryMessage(
                $sentCount,
                $failedCount,
                $skippedNoDebtCount,
                $skippedNoParentEmailCount,
            ),
        ];
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

    private static function buildSummaryMessage(
        int $sentCount,
        int $failedCount,
        int $skippedNoDebtCount,
        int $skippedNoParentEmailCount
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

        return implode(', ', $parts);
    }
}