<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Models\DeferCase;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

/**
 * Resolves the dashboard's cross-context student facts through their owners.
 * Finance owns the invoice/defer portions of the candidate set; Registry and
 * Progression own identity, campus visibility, and lifecycle facts.
 */
final class FinanceOperationsStudentScope
{
    public function __construct(
        private readonly StudentReferenceReader $studentReferences,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly AcademicFinanceChargeSourceGateway $academicSources,
    ) {}

    /**
     * @return array<int, array{reference: StudentReference, enrollment: ProgramEnrollmentSummary}>
     */
    public function dashboardStudents(int $semesterId, ?int $campusId, string $search = ''): array
    {
        $studentIds = array_values(array_unique(array_merge(
            $this->academicSources->billingDashboardStudentIds($semesterId),
            DeferCase::query()
                ->where('semester_id', $semesterId)
                ->pluck('student_id')
                ->map(static fn (int|string $id): int => (int) $id)
                ->all(),
            StudentInvoice::query()->where('semester_id', $semesterId)->pluck('student_id')->map(static fn (int|string $id): int => (int) $id)->all(),
        )));

        if ($campusId !== null) {
            $studentIds = array_values(array_intersect($studentIds, $this->studentReferences->idsForCampus($campusId)));
        }
        $references = $this->studentReferences->findMany($studentIds);
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $references = array_filter($references, static fn (StudentReference $reference): bool => str_contains(mb_strtolower($reference->studentCode), $needle)
                || str_contains(mb_strtolower($reference->fullName), $needle));
        }
        $enrollments = $this->programEnrollments->forStudentIds(array_keys($references));
        $students = [];

        foreach ($references as $studentId => $reference) {
            $enrollment = $enrollments[$studentId] ?? null;
            if ($enrollment === null || $enrollment->intakeSemesterId === null || $enrollment->intakeSemesterId > $semesterId) {
                continue;
            }

            $students[$studentId] = ['reference' => $reference, 'enrollment' => $enrollment];
        }

        return $students;
    }

    /** @return list<int> */
    public function retakeStudentIds(int $semesterId, ?int $campusId): array
    {
        $studentIds = $this->academicSources->billingDashboardRetakeStudentIds($semesterId);

        return $campusId === null
            ? $studentIds
            : array_values(array_intersect($studentIds, $this->studentReferences->idsForCampus($campusId)));
    }

    /**
     * @return list<array{student_id: int, fee_policy: string, has_upload_record: bool}>
     */
    public function deferCases(int $semesterId, ?int $campusId): array
    {
        $visibleStudentIds = $campusId === null ? null : array_flip($this->studentReferences->idsForCampus($campusId));

        return DeferCase::query()
            ->where('semester_id', $semesterId)
            ->get(['student_id', 'fee_policy', 'upload_record_id'])
            ->map(static fn (DeferCase $deferCase): array => [
                'student_id' => (int) $deferCase->student_id,
                'fee_policy' => (string) $deferCase->fee_policy,
                'has_upload_record' => $deferCase->upload_record_id !== null,
            ])
            ->filter(static fn (array $deferCase): bool => $visibleStudentIds === null || isset($visibleStudentIds[$deferCase['student_id']]))
            ->values()
            ->all();
    }
}
