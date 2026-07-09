<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Modules\Finance\Models\FinanceCharge;
use Carbon\CarbonInterface;

class StudentChargeTimingResolver
{
    /**
     * @var array<int, Semester|null>
     */
    private array $semesterCache = [];

    public function shouldIncludeStudentForChargeGeneration(Student $student, int $semesterId, array $chargeTypes): bool
    {
        $hasEgc = in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes, true);
        $hasTuition = in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes, true);

        if ($hasEgc && $this->shouldGenerateEgcForSemester($student, $semesterId)) {
            return true;
        }

        if ($hasTuition && $this->shouldGenerateTuitionForSemester($student, $semesterId)) {
            return true;
        }

        if (! $hasEgc && ! $hasTuition) {
            return $this->hasStartedBySemester($student, $semesterId);
        }

        return false;
    }

    public function shouldGenerateEgcForSemester(Student $student, int $semesterId): bool
    {
        if (! $this->hasStartedBySemester($student, $semesterId)) {
            return false;
        }

        $targetSemester = $this->getSemester($semesterId);
        $intakeMajorSemester = $this->getSemester($student->intake_major);

        if (! $targetSemester) {
            return false;
        }

        if (! $intakeMajorSemester) {
            return true;
        }

        return $this->startDate($targetSemester)->lt($this->startDate($intakeMajorSemester));
    }

    public function shouldGenerateTuitionForSemester(Student $student, int $semesterId): bool
    {
        if (! $this->hasStartedBySemester($student, $semesterId)) {
            return false;
        }

        $targetSemester = $this->getSemester($semesterId);
        $intakeMajorSemester = $this->getSemester($student->intake_major);

        if (! $targetSemester || ! $intakeMajorSemester) {
            return false;
        }

        return $this->startDate($targetSemester)->gte($this->startDate($intakeMajorSemester));
    }

    public function hasStartedBySemester(Student $student, int $semesterId): bool
    {
        $targetSemester = $this->getSemester($semesterId);
        $intakeSemester = $this->getSemester($student->intake_semester_id);

        if (! $targetSemester || ! $intakeSemester) {
            return false;
        }

        return $this->startDate($targetSemester)->gte($this->startDate($intakeSemester));
    }

    /**
     * @return array{term_number: int|null, amount: float|null, chargeable_term_index: int|null}
     */
    public function getTuitionTermData(Student $student, int $semesterId): array
    {
        if (! $this->shouldGenerateTuitionForSemester($student, $semesterId)) {
            return ['term_number' => null, 'amount' => null, 'chargeable_term_index' => null];
        }

        $intakeMajorSemester = $this->getSemester($student->intake_major);
        $targetSemester = $this->getSemester($semesterId);

        if (! $intakeMajorSemester || ! $targetSemester) {
            return ['term_number' => null, 'amount' => null, 'chargeable_term_index' => null];
        }

        $termNumber = Semester::query()
            ->where('start_date', '>=', $intakeMajorSemester->start_date)
            ->where('start_date', '<=', $targetSemester->start_date)
            ->count();

        $plan = TuitionPlan::query()
            ->where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $student->intake_semester_id)
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

    private function getSemester(?int $semesterId): ?Semester
    {
        if (! $semesterId) {
            return null;
        }

        if (! array_key_exists($semesterId, $this->semesterCache)) {
            $this->semesterCache[$semesterId] = Semester::query()->find($semesterId);
        }

        return $this->semesterCache[$semesterId];
    }

    private function startDate(Semester $semester): CarbonInterface
    {
        return $semester->start_date->copy()->startOfDay();
    }
}
