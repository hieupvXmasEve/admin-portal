<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;

class StudentChargeTimingResolver
{
    public function __construct(private readonly AcademicPeriodReader $academicPeriods) {}

    public function shouldIncludeStudentForChargeGeneration(mixed $enrollment, int $semesterId, array $chargeTypes): bool
    {
        $enrollment = $this->pricingFacts($enrollment);
        $hasEgc = in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes, true);
        $hasTuition = in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes, true);

        if ($hasEgc && $this->shouldGenerateEgcForSemester($enrollment, $semesterId)) {
            return true;
        }

        if ($hasTuition && $this->shouldGenerateTuitionForSemester($enrollment, $semesterId)) {
            return true;
        }

        if (! $hasEgc && ! $hasTuition) {
            return $this->hasStartedBySemester($enrollment, $semesterId);
        }

        return false;
    }

    public function shouldGenerateEgcForSemester(mixed $enrollment, int $semesterId): bool
    {
        $enrollment = $this->pricingFacts($enrollment);
        if (! $this->hasStartedBySemester($enrollment, $semesterId)) {
            return false;
        }

        $targetSemester = $this->academicPeriods->find($semesterId);
        $intakeMajorSemester = $enrollment->intakeMajorSemesterId === null
            ? null
            : $this->academicPeriods->find($enrollment->intakeMajorSemesterId);

        if (! $targetSemester) {
            return false;
        }

        if (! $intakeMajorSemester) {
            return true;
        }

        return $targetSemester->start_date?->lt($intakeMajorSemester->start_date) ?? false;
    }

    public function shouldGenerateTuitionForSemester(mixed $enrollment, int $semesterId): bool
    {
        $enrollment = $this->pricingFacts($enrollment);
        if (! $this->hasStartedBySemester($enrollment, $semesterId)) {
            return false;
        }

        $targetSemester = $this->academicPeriods->find($semesterId);
        $intakeMajorSemester = $enrollment->intakeMajorSemesterId === null
            ? null
            : $this->academicPeriods->find($enrollment->intakeMajorSemesterId);

        if (! $targetSemester || ! $intakeMajorSemester) {
            return false;
        }

        return $targetSemester->start_date?->gte($intakeMajorSemester->start_date) ?? false;
    }

    public function hasStartedBySemester(mixed $enrollment, int $semesterId): bool
    {
        $enrollment = $this->pricingFacts($enrollment);
        $targetSemester = $this->academicPeriods->find($semesterId);
        $intakeSemester = $enrollment->intakeSemesterId === null
            ? null
            : $this->academicPeriods->find($enrollment->intakeSemesterId);

        if (! $targetSemester || ! $intakeSemester) {
            return false;
        }

        return $targetSemester->start_date?->gte($intakeSemester->start_date) ?? false;
    }

    /**
     * @return array{term_number: int|null, amount: float|null, chargeable_term_index: int|null}
     */
    public function getTuitionTermData(mixed $enrollment, int $semesterId): array
    {
        $enrollment = $this->pricingFacts($enrollment);
        if (! $this->shouldGenerateTuitionForSemester($enrollment, $semesterId)) {
            return ['term_number' => null, 'amount' => null, 'chargeable_term_index' => null];
        }

        $intakeMajorSemester = $enrollment->intakeMajorSemesterId === null
            ? null
            : $this->academicPeriods->find($enrollment->intakeMajorSemesterId);
        $targetSemester = $this->academicPeriods->find($semesterId);

        if (! $intakeMajorSemester || ! $targetSemester) {
            return ['term_number' => null, 'amount' => null, 'chargeable_term_index' => null];
        }

        if ($intakeMajorSemester->start_date === null || $targetSemester->start_date === null) {
            return ['term_number' => null, 'amount' => null, 'chargeable_term_index' => null];
        }

        $termNumber = $this->academicPeriods->countStartingBetween(
            $intakeMajorSemester->start_date,
            $targetSemester->start_date,
        );

        $plan = TuitionPlan::query()
            ->where('curriculum_version_id', $enrollment->curriculumVersionId)
            ->where('intake_semester_id', $enrollment->intakeSemesterId)
            ->first();

        if (! $plan) {
            return ['term_number' => null, 'amount' => null, 'chargeable_term_index' => null];
        }

        $term = TuitionPlanTerm::query()
            ->where('tuition_plan_id', $plan->id)
            ->where('term_number', $termNumber)
            ->first();

        if (! $term) {
            return ['term_number' => $termNumber, 'amount' => null, 'chargeable_term_index' => null];
        }

        $chargeableTermIndex = TuitionPlanTerm::query()
            ->where('tuition_plan_id', $plan->id)
            ->where('term_number', '<=', $termNumber)
            ->where('amount', '>', 0)
            ->count();

        return [
            'term_number' => $termNumber,
            'amount' => (float) $term->amount,
            'chargeable_term_index' => $chargeableTermIndex > 0 ? $chargeableTermIndex : null,
        ];
    }

    private function pricingFacts(mixed $candidate): ProgramEnrollmentSummary
    {
        if ($candidate instanceof ProgramEnrollmentSummary) {
            return $candidate;
        }

        $studentId = is_object($candidate) ? (int) ($candidate->id ?? 0) : 0;
        if ($studentId <= 0) {
            throw new \InvalidArgumentException('Pricing requires a student identifier or ProgramEnrollmentSummary.');
        }

        return app(ProgramEnrollmentReader::class)->forStudentId($studentId);
    }
}
