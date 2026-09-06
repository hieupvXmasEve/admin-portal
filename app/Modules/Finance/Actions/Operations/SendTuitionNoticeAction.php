<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\Campus;
use App\Models\Student;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Notification\EmailContentResolver;
use App\Shared\Contracts\Notification\TuitionNoticeDeliveryReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendTuitionNoticeAction
{
    public const PAYMENT_INSTRUCTION = 'Sinh viên thanh toán bằng cách quét QR trên cổng thông tin. Phụ huynh có thể thanh toán hộ. Kế toán ghi nhận tiền mặt hoặc chuyển khoản tại văn phòng tài chính.';

    /**
     * @param  array{student_ids: list<int>, semester_id?: int|null}  $data
     * @return array{
     *     sent_count: int,
     *     skipped_duplicate_count: int,
     *     skipped_no_debt_count: int,
     *     skipped_no_student_email_count: int,
     *     skipped_no_parent_count: int,
     *     message: string
     * }
     */
    public static function run(array $data): array
    {
        self::assertPublisherReady();

        $studentIds = array_values(array_unique(array_map(intval(...), $data['student_ids'])));
        $semesterId = isset($data['semester_id']) ? (int) $data['semester_id'] : null;

        $students = Student::query()
            ->whereIn('id', $studentIds)
            ->get(['id', 'student_id', 'full_name', 'email', 'campus_id', 'user_id']);

        self::assertTemplatesProvisioned($students->pluck('campus_id')->filter()->map(intval(...))->unique()->values()->all());

        $settlement = app(SettlementPositionReader::class);
        $guardians = app(GuardianAccessGrantReader::class);
        $deliveries = app(TuitionNoticeDeliveryReader::class);

        $invoicesByStudent = StudentInvoice::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->when($semesterId !== null && $semesterId > 0, static fn ($query) => $query->where('semester_id', $semesterId))
            ->with(['semester:id,name,code', 'invoiceLines.charge'])
            ->get()
            ->groupBy(static fn (StudentInvoice $invoice): int => (int) $invoice->student_id);

        $scopes = [];
        foreach ($invoicesByStudent as $studentInvoices) {
            foreach ($studentInvoices as $invoice) {
                $scopes[] = SettlementPositionScope::invoice((int) $invoice->id);
            }
        }
        $positionsByInvoiceId = [];
        foreach ($settlement->batch($scopes) as $position) {
            if ($position->scope_type === SettlementPosition::SCOPE_INVOICE) {
                $positionsByInvoiceId[(int) $position->scope_id] = $position;
            }
        }

        $parentsByStudent = $guardians->accountsForStudents(
            $students->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
        );

        $sentCount = 0;
        $skippedDuplicateCount = 0;
        $skippedNoDebtCount = 0;
        $skippedNoStudentEmailCount = 0;
        $skippedNoParentCount = 0;

        foreach ($students as $student) {
            $studentInvoices = $invoicesByStudent->get((int) $student->id, collect());
            $snapshot = self::buildSnapshot($studentInvoices, $positionsByInvoiceId);
            if ($snapshot === null) {
                $skippedNoDebtCount++;

                continue;
            }

            $payload = self::templatePayload($student, $snapshot);

            $studentPublished = false;
            if (! is_string($student->email) || trim($student->email) === '' || $student->user_id === null) {
                $skippedNoStudentEmailCount++;
            } else {
                $result = self::publishIfNeeded(
                    student: $student,
                    typeKey: NotificationTemplateTypeKey::TuitionNotice->value,
                    recipientType: 'student',
                    recipientId: (int) $student->id,
                    recipientUserId: (int) $student->user_id,
                    recipientEmail: null,
                    payload: $payload,
                    deliveries: $deliveries,
                );
                if ($result === 'sent') {
                    $sentCount++;
                    $studentPublished = true;
                } elseif ($result === 'duplicate') {
                    $skippedDuplicateCount++;
                }
            }

            $parents = $parentsByStudent[(int) $student->id] ?? [];
            if ($parents === []) {
                if ($studentPublished) {
                    $skippedNoParentCount++;
                }

                continue;
            }

            foreach ($parents as $parent) {
                $result = self::publishIfNeeded(
                    student: $student,
                    typeKey: NotificationTemplateTypeKey::ParentTuitionNotice->value,
                    recipientType: 'email',
                    recipientId: (int) $parent->id,
                    recipientUserId: null,
                    recipientEmail: $parent->email,
                    payload: $payload,
                    deliveries: $deliveries,
                );
                if ($result === 'sent') {
                    $sentCount++;
                } elseif ($result === 'duplicate') {
                    $skippedDuplicateCount++;
                }
            }
        }

        return [
            'sent_count' => $sentCount,
            'skipped_duplicate_count' => $skippedDuplicateCount,
            'skipped_no_debt_count' => $skippedNoDebtCount,
            'skipped_no_student_email_count' => $skippedNoStudentEmailCount,
            'skipped_no_parent_count' => $skippedNoParentCount,
            'message' => self::buildSummaryMessage(
                $sentCount,
                $skippedDuplicateCount,
                $skippedNoDebtCount,
                $skippedNoStudentEmailCount,
                $skippedNoParentCount,
            ),
        ];
    }

    private static function assertPublisherReady(): void
    {
        $enabled = (bool) config('notification.v2_enabled', false);
        $mode = (string) config('notification.write_mode', 'off');
        if ($enabled && in_array($mode, ['dual', 'v2', 'v2_only'], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'student_ids' => 'Thông báo học phí chưa bật. Không thể phát hành.',
        ]);
    }

    /**
     * @param  list<int>  $campusIds
     */
    private static function assertTemplatesProvisioned(array $campusIds): void
    {
        $registry = app(EmailContentResolver::class);
        $missing = [];
        $keys = [
            NotificationTemplateTypeKey::TuitionNotice->value,
            NotificationTemplateTypeKey::ParentTuitionNotice->value,
        ];

        foreach ($campusIds as $campusId) {
            foreach ($keys as $typeKey) {
                if (! $registry->isConfiguredForCampus($typeKey, $campusId)) {
                    $missing[$campusId] = true;
                    break;
                }
            }
        }

        if ($missing === []) {
            return;
        }

        $names = Campus::query()
            ->whereIn('id', array_keys($missing))
            ->orderBy('id')
            ->get(['name', 'code'])
            ->map(static fn (Campus $campus): string => trim($campus->name.' ('.$campus->code.')'))
            ->implode(', ');

        throw ValidationException::withMessages([
            'student_ids' => 'Thiếu mẫu email thông báo học phí cho cơ sở: '.$names.'. Không gửi.',
        ]);
    }

    /**
     * @param  iterable<int, StudentInvoice>  $invoices
     * @param  array<int, SettlementPosition>  $positionsByInvoiceId
     * @return array{items_summary: string, total_due: string, due_date: string, semester_name: string}|null
     */
    private static function buildSnapshot(iterable $invoices, array $positionsByInvoiceId): ?array
    {
        $itemLines = [];
        $totalDue = 0.0;
        $dueDates = [];
        $semesterNames = [];

        foreach ($invoices as $invoice) {
            $position = $positionsByInvoiceId[(int) $invoice->id] ?? null;
            if (! $position instanceof SettlementPosition) {
                continue;
            }
            $rows = self::outstandingRows($invoice, $position);
            foreach ($rows as $row) {
                $totalDue += $row['remaining'];
                $itemLines[] = $row['line'];
                if ($row['due_date'] !== null && $row['due_date'] !== '') {
                    $dueDates[] = $row['due_date'];
                }
                if ($row['semester_name'] !== '') {
                    $semesterNames[] = $row['semester_name'];
                }
            }
        }

        if ($itemLines === [] || $totalDue <= 0) {
            return null;
        }

        sort($dueDates);

        return [
            'items_summary' => implode("\n", $itemLines),
            'total_due' => self::formatAmount($totalDue),
            'due_date' => $dueDates[0] ?? '',
            'semester_name' => implode(', ', array_values(array_unique($semesterNames))),
        ];
    }

    /**
     * @return list<array{line: string, remaining: float, due_date: string|null, semester_name: string}>
     */
    private static function outstandingRows(StudentInvoice $invoice, SettlementPosition $position): array
    {
        $semesterName = (string) ($invoice->semester?->name ?: $invoice->semester?->code ?: '');
        $dueDate = $invoice->due_date?->format('d/m/Y');
        $rows = [];

        foreach ($position->payable_line_breakdown as $linePosition) {
            if (! $linePosition->isValid() || $linePosition->amounts === null) {
                continue;
            }

            $remaining = (float) $linePosition->amounts->remaining->amount;
            if ($remaining <= 0) {
                continue;
            }

            $feeType = (string) ($linePosition->fee_type ?? '');
            $label = ObligationTypeRegistry::has($feeType)
                ? ObligationTypeRegistry::get($feeType)->label
                : ($feeType !== '' ? $feeType : 'Khoản thu');
            $line = $invoice->invoiceLines->firstWhere('id', $linePosition->payable_line_id);
            $description = trim((string) ($line?->description_snapshot ?? $line?->charge?->description ?? ''));
            $text = $description !== ''
                ? $label.' — '.$description.': '.self::formatAmount($remaining).' ₫'
                : $label.': '.self::formatAmount($remaining).' ₫';

            $rows[] = [
                'line' => $text,
                'remaining' => $remaining,
                'due_date' => $dueDate,
                'semester_name' => $semesterName,
            ];
        }

        if ($rows !== []) {
            return $rows;
        }

        if (! $position->isValid() || $position->amounts === null) {
            return [];
        }

        $remaining = (float) $position->amounts->remaining->amount;
        if ($remaining <= 0) {
            return [];
        }

        $descriptions = $invoice->invoiceLines
            ->where('status', 'active')
            ->map(static fn ($line): string => trim((string) ($line->description_snapshot ?? $line->charge?->description ?? '')))
            ->filter()
            ->unique()
            ->implode('; ');
        $label = $descriptions !== '' ? $descriptions : 'Phải thu';

        return [[
            'line' => $label.': '.self::formatAmount($remaining).' ₫',
            'remaining' => $remaining,
            'due_date' => $dueDate,
            'semester_name' => $semesterName,
        ]];
    }

    /**
     * @param  array{items_summary: string, total_due: string, due_date: string, semester_name: string}  $snapshot
     * @return array<string, mixed>
     */
    private static function templatePayload(Student $student, array $snapshot): array
    {
        return [
            'campus_id' => (int) $student->campus_id,
            'student_code' => (string) $student->student_id,
            'student_name' => (string) $student->full_name,
            'semester_name' => $snapshot['semester_name'],
            'due_date' => $snapshot['due_date'],
            'total_due' => $snapshot['total_due'],
            'items_summary' => $snapshot['items_summary'],
            'payment_instruction' => self::PAYMENT_INSTRUCTION,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'sent'|'duplicate'
     */
    private static function publishIfNeeded(
        Student $student,
        string $typeKey,
        string $recipientType,
        int $recipientId,
        ?int $recipientUserId,
        ?string $recipientEmail,
        array $payload,
        TuitionNoticeDeliveryReader $deliveries,
    ): string {
        $contentHash = self::contentHash($payload);
        $outcome = $deliveries->outcomeForContent($typeKey, $contentHash, $recipientUserId, $recipientEmail);
        if ($outcome->blocksResend) {
            return 'duplicate';
        }

        $payload['content_hash'] = $contentHash;
        if ($outcome->failedDeliveryId !== null) {
            $payload['retry_nonce'] = (string) $outcome->failedDeliveryId;
        }

        DB::transaction(static function () use ($student, $typeKey, $recipientType, $recipientId, $recipientEmail, $payload): void {
            $publish = [
                'type_key' => $typeKey,
                'aggregate_type' => 'student',
                'aggregate_id' => (int) $student->id,
                'campus_id' => (int) $student->campus_id,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'data' => $payload,
            ];
            if ($recipientEmail !== null && $recipientEmail !== '') {
                $publish['recipient_email'] = $recipientEmail;
            }
            PublishReminderNotificationAction::run($publish);
        });

        return 'sent';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function contentHash(array $payload): string
    {
        $canonical = $payload;
        unset($canonical['retry_nonce'], $canonical['content_hash']);

        return hash(
            'sha256',
            json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    private static function formatAmount(float $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    private static function buildSummaryMessage(
        int $sentCount,
        int $skippedDuplicateCount,
        int $skippedNoDebtCount,
        int $skippedNoStudentEmailCount,
        int $skippedNoParentCount,
    ): string {
        $parts = ["Đã phát {$sentCount} thông báo học phí"];

        if ($skippedDuplicateCount > 0) {
            $parts[] = "{$skippedDuplicateCount} trùng nội dung đã gửi";
        }
        if ($skippedNoDebtCount > 0) {
            $parts[] = "{$skippedNoDebtCount} sinh viên không còn phải thu";
        }
        if ($skippedNoStudentEmailCount > 0) {
            $parts[] = "{$skippedNoStudentEmailCount} sinh viên không có email";
        }
        if ($skippedNoParentCount > 0) {
            $parts[] = "{$skippedNoParentCount} sinh viên không có phụ huynh";
        }

        return implode(', ', $parts);
    }
}
