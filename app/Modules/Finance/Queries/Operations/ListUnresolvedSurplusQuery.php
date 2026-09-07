<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\Reporting\UnappliedCashReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Derived worklist: leftover cash on students who have left school.
 * Inbox (Phase 8) consumes this; no new payment status column.
 */
final class ListUnresolvedSurplusQuery
{
    public const WORK_TYPE = 'unresolved_surplus';

    /** @var list<string> */
    public const LEFT_SCHOOL_STATUSES = ['graduated', 'dropout', 'dropout_transfer'];

    public function __construct(
        private readonly UnappliedCashReader $unappliedCash,
        private readonly ProgramEnrollmentReader $programEnrollments,
    ) {}

    public static function isLeftSchool(?string $status): bool
    {
        return $status !== null && in_array($status, self::LEFT_SCHOOL_STATUSES, true);
    }

    /**
     * @return list<array{work_type: string, student_id: int, unapplied: float, enrollment_status: string, payment_ids: list<int>}>
     */
    public function handle(?int $campusId, bool $canViewAllCampus = false): array
    {
        if ($campusId === null && ! $canViewAllCampus) {
            throw new AuthorizationException('Campus is required to list unresolved surplus.');
        }

        $payments = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->when($campusId !== null, function ($query) use ($campusId): void {
                $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId));
            })
            ->orderBy('id')
            ->get(['id', 'student_id']);

        if ($payments->isEmpty()) {
            return [];
        }

        $studentIds = $payments
            ->pluck('student_id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $unappliedByStudent = $this->unappliedCash->unappliedByStudent($studentIds);
        $withSurplus = [];
        foreach ($unappliedByStudent as $studentId => $amount) {
            if ($amount > 0) {
                $withSurplus[] = (int) $studentId;
            }
        }

        if ($withSurplus === []) {
            return [];
        }

        $enrollments = $this->programEnrollments->forStudentIds($withSurplus);
        $rows = [];
        foreach ($withSurplus as $studentId) {
            $status = $enrollments[$studentId]?->legacyCompatibleStatus();
            if (! self::isLeftSchool($status)) {
                continue;
            }

            $paymentIds = $payments
                ->where('student_id', $studentId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $rows[] = [
                'work_type' => self::WORK_TYPE,
                'student_id' => $studentId,
                'unapplied' => round($unappliedByStudent[$studentId], 2),
                'enrollment_status' => (string) $status,
                'payment_ids' => $paymentIds,
            ];
        }

        return $rows;
    }
}
