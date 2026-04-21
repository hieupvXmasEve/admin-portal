<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\ParentProfile;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Services\EmailService;
use Illuminate\Support\Collection;

class SendParentPaymentRemindersAction
{
    public static function run(array $data): array
    {
        $invoiceIds = $data['invoice_ids'];
        $sentCount = 0;
        $failedCount = 0;
        $skippedNoDebtCount = 0;
        $skippedNoParentEmailCount = 0;

        $invoices = StudentInvoice::query()
            ->whereIn('id', $invoiceIds)
            ->with([
                'student:id,student_id,full_name,campus_id',
                'student.parentProfiles.user:id,email,status,type',
                'semester:id,code',
            ])
            ->get();

        $settlementService = app(SettlementService::class);
        $emailService = app(EmailService::class);
        $emailContent = app(EmailContentRegistry::class)->resolve('payment_reminder');
        $now = now();

        foreach ($invoices as $invoice) {
            $student = $invoice->student;
            $snapshot = $settlementService->deriveInvoiceSnapshot($invoice);
            $balance = (float) $snapshot['remaining'];

            if ($balance <= 0) {
                $skippedNoDebtCount++;

                continue;
            }

            $parentEmails = self::extractParentEmails($student?->parentProfiles);

            if ($parentEmails->isEmpty()) {
                $skippedNoParentEmailCount++;

                continue;
            }

            $contentData = [
                'student_name' => $student->full_name,
                'student_code' => $student->student_id,
                'semester_code' => $invoice->semester?->code ?? '',
                'invoice_code' => $invoice->invoice_number,
                'balance_formatted' => number_format($balance, 0, ',', '.'),
                'due_date' => $invoice->due_date?->format('d/m/Y') ?? '',
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
                } catch (\Throwable $e) {
                    $failedCount++;
                }
            }

            if ($sentAnyParent) {
                $invoice->update(['last_reminder_at' => $now]);
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
        $parts = ["Đã gửi {$sentCount} email thông báo học phí cho phụ huynh"];

        if ($failedCount > 0) {
            $parts[] = "{$failedCount} email thất bại";
        }

        if ($skippedNoDebtCount > 0) {
            $parts[] = "{$skippedNoDebtCount} invoice không còn nợ";
        }

        if ($skippedNoParentEmailCount > 0) {
            $parts[] = "{$skippedNoParentEmailCount} invoice không có email phụ huynh";
        }

        return implode(', ', $parts);
    }
}
