<?php

namespace App\Services;

use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TuitionPlanService
{
    /**
     * Create a new tuition plan.
     *
     * @param array $data
     * @return TuitionPlan
     * @throws ValidationException
     */
    public function createTuitionPlan(array $data): TuitionPlan
    {
        // Validate curriculum/intake uniqueness
        if (!$this->validateCurriculumIntakeUniqueness(
            $data['curriculum_version_id'],
            $data['intake_semester_id']
        )) {
            throw ValidationException::withMessages([
                'curriculum_version_id' => ['A tuition plan already exists for this curriculum version and intake semester combination.']
            ]);
        }

        // Validate total amount
        if ($data['total_amount'] < 0) {
            throw ValidationException::withMessages([
                'total_amount' => ['The total amount must be greater than or equal to zero.']
            ]);
        }

        // Set default values
        $data['currency'] = $data['currency'] ?? 'VND';
        $data['is_active'] = $data['is_active'] ?? true;

        return DB::transaction(function () use ($data) {
            $plan = TuitionPlan::create($data);

            // Create terms if provided
            if (isset($data['terms']) && is_array($data['terms'])) {
                foreach ($data['terms'] as $termData) {
                    $this->addTerm($plan->id, $termData);
                }
            }

            return $plan->fresh(['terms']);
        });
    }

    /**
     * Update an existing tuition plan.
     *
     * @param int $id
     * @param array $data
     * @return TuitionPlan
     * @throws ValidationException
     */
    public function updateTuitionPlan(int $id, array $data): TuitionPlan
    {
        $plan = TuitionPlan::findOrFail($id);

        // Validate curriculum/intake uniqueness if being changed
        if (
            (isset($data['curriculum_version_id']) && $data['curriculum_version_id'] !== $plan->curriculum_version_id) ||
            (isset($data['intake_semester_id']) && $data['intake_semester_id'] !== $plan->intake_semester_id)
        ) {
            $curriculumVersionId = $data['curriculum_version_id'] ?? $plan->curriculum_version_id;
            $intakeSemesterId = $data['intake_semester_id'] ?? $plan->intake_semester_id;

            if (!$this->validateCurriculumIntakeUniqueness($curriculumVersionId, $intakeSemesterId, $id)) {
                throw ValidationException::withMessages([
                    'curriculum_version_id' => ['A tuition plan already exists for this curriculum version and intake semester combination.']
                ]);
            }
        }

        // Validate total amount if being changed
        if (isset($data['total_amount']) && $data['total_amount'] < 0) {
            throw ValidationException::withMessages([
                'total_amount' => ['The total amount must be greater than or equal to zero.']
            ]);
        }

        return DB::transaction(function () use ($plan, $data) {
            $plan->update($data);

            // Update terms if provided
            if (isset($data['terms']) && is_array($data['terms'])) {
                // Delete existing terms
                $plan->terms()->delete();

                // Create new terms
                foreach ($data['terms'] as $termData) {
                    $this->addTerm($plan->id, $termData);
                }
            }

            return $plan->fresh(['terms']);
        });
    }

    /**
     * Delete a tuition plan.
     *
     * @param int $id
     * @return bool
     */
    public function deleteTuitionPlan(int $id): bool
    {
        $plan = TuitionPlan::findOrFail($id);

        return DB::transaction(function () use ($plan) {
            // Delete all terms first
            $plan->terms()->delete();

            // Delete the plan
            return $plan->delete();
        });
    }

    /**
     * Add a term to a tuition plan.
     *
     * @param int $planId
     * @param array $termData
     * @return TuitionPlanTerm
     * @throws ValidationException
     */
    public function addTerm(int $planId, array $termData): TuitionPlanTerm
    {
        $plan = TuitionPlan::findOrFail($planId);

        // Validate amount
        if (isset($termData['amount']) && $termData['amount'] < 0) {
            throw ValidationException::withMessages([
                'amount' => ['The term amount must be greater than or equal to zero.']
            ]);
        }

        // Validate term number
        if (isset($termData['term_number']) && $termData['term_number'] < 1) {
            throw ValidationException::withMessages([
                'term_number' => ['The term number must be greater than zero.']
            ]);
        }

        // Check for duplicate term number
        if (isset($termData['term_number'])) {
            $existingTerm = TuitionPlanTerm::where('tuition_plan_id', $planId)
                ->where('term_number', $termData['term_number'])
                ->first();

            if ($existingTerm) {
                throw ValidationException::withMessages([
                    'term_number' => ['A term with this number already exists for this tuition plan.']
                ]);
            }
        }

        $termData['tuition_plan_id'] = $planId;
        $termData['amount'] = $termData['amount'] ?? 0;

        return TuitionPlanTerm::create($termData);
    }

    /**
     * Update a term.
     *
     * @param int $termId
     * @param array $data
     * @return TuitionPlanTerm
     * @throws ValidationException
     */
    public function updateTerm(int $termId, array $data): TuitionPlanTerm
    {
        $term = TuitionPlanTerm::findOrFail($termId);

        // Validate amount if being changed
        if (isset($data['amount']) && $data['amount'] < 0) {
            throw ValidationException::withMessages([
                'amount' => ['The term amount must be greater than or equal to zero.']
            ]);
        }

        // Validate term number if being changed
        if (isset($data['term_number'])) {
            if ($data['term_number'] < 1) {
                throw ValidationException::withMessages([
                    'term_number' => ['The term number must be greater than zero.']
                ]);
            }

            // Check for duplicate term number
            if ($data['term_number'] !== $term->term_number) {
                $existingTerm = TuitionPlanTerm::where('tuition_plan_id', $term->tuition_plan_id)
                    ->where('term_number', $data['term_number'])
                    ->where('id', '!=', $termId)
                    ->first();

                if ($existingTerm) {
                    throw ValidationException::withMessages([
                        'term_number' => ['A term with this number already exists for this tuition plan.']
                    ]);
                }
            }
        }

        $term->update($data);

        return $term->fresh();
    }

    /**
     * Delete a term.
     *
     * @param int $termId
     * @return bool
     */
    public function deleteTerm(int $termId): bool
    {
        $term = TuitionPlanTerm::findOrFail($termId);
        return $term->delete();
    }

    /**
     * Get the tuition plan for a student in a specific semester.
     *
     * @param int $studentId
     * @param int $semesterId
     * @return TuitionPlan|null
     */
    public function getTuitionForStudent(int $studentId, int $semesterId): ?TuitionPlan
    {
        $student = Student::findOrFail($studentId);

        if (!$student->curriculum_version_id || !$student->intake_semester_id) {
            return null;
        }

        return TuitionPlan::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $student->intake_semester_id)
            ->where('is_active', true)
            ->with('terms')
            ->first();
    }

    /**
     * Get all active tuition plans.
     *
     * @return Collection
     */
    public function getActiveTuitionPlans(): Collection
    {
        return TuitionPlan::active()
            ->with(['curriculumVersion', 'intakeSemester', 'terms'])
            ->get();
    }

    /**
     * Validate curriculum/intake uniqueness.
     *
     * @param int $curriculumVersionId
     * @param int $intakeSemesterId
     * @param int|null $excludeId
     * @return bool
     */
    private function validateCurriculumIntakeUniqueness(
        int $curriculumVersionId,
        int $intakeSemesterId,
        ?int $excludeId = null
    ): bool {
        $query = TuitionPlan::where('curriculum_version_id', $curriculumVersionId)
            ->where('intake_semester_id', $intakeSemesterId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }
}
