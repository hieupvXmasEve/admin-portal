<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\AcademicChargeHandoffResult;
use App\Shared\Contracts\Academic\DTO\AcademicChargeSourceData;
use App\Shared\Contracts\Academic\DTO\AcademicEgcBlockData;
use App\Shared\Contracts\Academic\DTO\AcademicExamResitDueData;
use DateTimeInterface;

interface AcademicFinanceChargeSourceGateway
{
    public function createRetakeCharge(int $registrationId, int $actorId, ?string $description = null): AcademicChargeHandoffResult;

    public function createExamResitCharge(int $attemptId, int $actorId, ?string $dueDate = null): AcademicChargeHandoffResult;

    /**
     * @return list<AcademicChargeSourceData>
     */
    public function chargeableRetakeSourcesForStudent(int $studentId, int $semesterId, bool $lockForUpdate = false): array;

    /**
     * @return list<AcademicChargeSourceData>
     */
    public function chargeableExamResitSourcesForStudent(int $studentId, int $semesterId, bool $lockForUpdate = false): array;

    /**
     * @return list<AcademicChargeSourceData>
     */
    public function approvedRetakeSources(?int $campusId = null, ?int $semesterId = null, string $search = ''): array;

    /**
     * @return list<AcademicChargeSourceData>
     */
    public function approvedExamResitSources(?int $campusId = null, ?int $semesterId = null, string $search = ''): array;

    /**
     * @return list<int>
     */
    public function retakeStudentIdsWithExpectedFees(int $semesterId, ?int $campusId = null): array;

    /** @return list<int> */
    public function billingDashboardStudentIds(int $semesterId): array;

    /** @return list<int> */
    public function billingDashboardRetakeStudentIds(int $semesterId): array;

    /**
     * @return list<int>
     */
    public function examResitStudentIdsWithExpectedFees(int $semesterId, ?int $campusId = null): array;

    /**
     * @param  array<int>  $attemptIds
     * @return array<int, AcademicExamResitDueData> keyed by attempt id
     */
    public function examResitDueSourcesByIds(array $attemptIds): array;

    /**
     * @return list<AcademicExamResitDueData>
     */
    public function overdueExamResitDueSources(?int $campusId = null, ?int $semesterId = null, string $search = ''): array;

    /**
     * @param  array<int>  $attemptIds
     */
    public function touchExamResitDueSources(array $attemptIds, DateTimeInterface $timestamp): int;

    /**
     * @return list<AcademicEgcBlockData>
     */
    public function egcBlocksForStudentSemester(int $studentId, int $semesterId): array;

    /**
     * @return list<AcademicEgcBlockData>
     */
    public function egcBlocksForStudent(int $studentId): array;

    /**
     * @return list<AcademicEgcBlockData>
     */
    public function egcBlocksForSemester(int $semesterId): array;

    /**
     * @param  array<int>|null  $studentIds
     * @return list<AcademicEgcBlockData>
     */
    public function failedEgcBlocksForSemester(int $semesterId, ?array $studentIds = null, ?int $campusId = null): array;

    /**
     * @return list<AcademicEgcBlockData>
     */
    public function egcRetakeTargetsForBlock(int $sourceBlockId): array;

    public function egcBlockById(int $blockId): ?AcademicEgcBlockData;

    public function createEgcBlock(
        int $studentId,
        int $semesterId,
        int $blockNumber,
        int $levelNumber,
        bool $isRetake,
    ): AcademicEgcBlockData;

    public function updateEgcBlockResult(int $blockId, string $result, ?float $attendanceRate): void;

    public function updateEgcBlockLevel(int $blockId, int $levelNumber, bool $isRetake): void;

    public function markEgcRetakeDiscountConsumed(int $blockId, int $discountId): void;

    public function isStudentStudyingEgcLevel(int $studentId, int $levelNumber): bool;

    public function isEgcRetakeEligible(int $studentId, int $levelNumber): bool;

    public function egcSourceRef(int $blockId): string;

    public function resolveEgcBlockResult(int $studentId, int $semesterId, int $levelNumber): ?array;

    /**
     * @param  array<int>  $blockIds
     * @return array<int, array{result:string, attendance_rate:float|null}|null>
     */
    public function resolveEgcBlockResultsByBlockIds(array $blockIds): array;

    public function hasImmediateNextEgcBlockProgressionEvidence(int $blockId): bool;

    public function egcBlockHasMatchedRegistration(int $blockId): bool;
}
