<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Shared\Contracts\Identity\DTO\GuardianAccessAccount;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use Illuminate\Support\Facades\DB;

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
                'semester:id,code',
            ])
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
            $parentRecipients = $student === null
                ? collect()
                : collect(app(GuardianAccessGrantReader::class)->accountsForStudent((int) $student->id))
                    ->map(static fn (GuardianAccessAccount $account): array => [
                        'user_id' => $account->id,
                        'email' => mb_strtolower(trim($account->email)),
                        'name' => trim($account->name) !== '' ? $account->name : 'Quý Phụ Huynh',
                    ]);

            if ($parentRecipients->isEmpty()) {
                $skippedNoParentEmailCount++;

                continue;
            }

            $sharedContentData = [
                'campus_id' => $student->campus_id,
                'student_name' => $student->full_name,
                'student_code' => $student->student_id,
                'semester_code' => $invoice->semester?->code ?? '',
                'invoice_code' => $invoice->invoice_number,
                'balance_formatted' => number_format($balance, 0, ',', '.'),
                'due_date' => $invoice->due_date?->format('d/m/Y') ?? '',
            ];

            foreach ($parentRecipients as $recipient) {
                $contentData = $sharedContentData + ['parent_name' => $recipient['name']];

                try {
                    DB::transaction(function () use ($invoice, $student, $recipient, $contentData, $now): void {
                        $invoice->update(['last_reminder_at' => $now]);
                        PublishReminderNotificationAction::run([
                            'type_key' => 'parent_payment_reminder',
                            'aggregate_type' => 'student_invoice',
                            'aggregate_id' => $invoice->id,
                            'campus_id' => (int) $student->campus_id,
                            'recipient_type' => 'user',
                            'recipient_id' => $recipient['user_id'],
                            'data' => $contentData,
                        ]);
                    });

                    $sentCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                }
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
