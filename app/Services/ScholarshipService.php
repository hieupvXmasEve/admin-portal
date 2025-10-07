<?php

namespace App\Services;

use App\Models\ScholarshipDefinition;
use App\Models\StudentScholarshipAward;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScholarshipService
{
    /**
     * Create a new scholarship definition.
     *
     * @param array $data
     * @return ScholarshipDefinition
     * @throws ValidationException
     */
    public function createScholarship(array $data): ScholarshipDefinition
    {
        // Validate code uniqueness
        if (!$this->validateScholarshipCode($data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['The scholarship code has already been taken.']
            ]);
        }

        // Validate date range
        $validFrom = Carbon::parse($data['valid_from']);
        $validUntil = Carbon::parse($data['valid_until']);

        if ($validFrom->greaterThanOrEqualTo($validUntil)) {
            throw ValidationException::withMessages([
                'valid_from' => ['The valid from date must be before the valid until date.']
            ]);
        }

        // Validate amount
        if ($data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['The scholarship amount must be greater than zero.']
            ]);
        }

        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;

        return ScholarshipDefinition::create($data);
    }

    /**
     * Update an existing scholarship definition.
     *
     * @param int $id
     * @param array $data
     * @return ScholarshipDefinition
     * @throws ValidationException
     */
    public function updateScholarship(int $id, array $data): ScholarshipDefinition
    {
        $scholarship = ScholarshipDefinition::findOrFail($id);

        // Validate code uniqueness if code is being changed
        if (isset($data['code']) && $data['code'] !== $scholarship->code) {
            if (!$this->validateScholarshipCode($data['code'])) {
                throw ValidationException::withMessages([
                    'code' => ['The scholarship code has already been taken.']
                ]);
            }
        }

        // Validate date range if dates are being changed
        $validFrom = isset($data['valid_from'])
            ? Carbon::parse($data['valid_from'])
            : $scholarship->valid_from;

        $validUntil = isset($data['valid_until'])
            ? Carbon::parse($data['valid_until'])
            : $scholarship->valid_until;

        if ($validFrom->greaterThanOrEqualTo($validUntil)) {
            throw ValidationException::withMessages([
                'valid_from' => ['The valid from date must be before the valid until date.']
            ]);
        }

        // Validate amount if being changed
        if (isset($data['amount']) && $data['amount'] <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['The scholarship amount must be greater than zero.']
            ]);
        }

        $scholarship->update($data);

        return $scholarship->fresh();
    }

    /**
     * Delete a scholarship definition.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteScholarship(int $id): bool
    {
        $scholarship = ScholarshipDefinition::findOrFail($id);

        // Check if scholarship is assigned to any students
        $studentCount = $scholarship->studentScholarshipAwards()->count();

        if ($studentCount > 0) {
            throw new \Exception(
                "Cannot delete scholarship. It is currently assigned to {$studentCount} student(s)."
            );
        }

        return $scholarship->delete();
    }

    /**
     * Assign a scholarship to a student.
     *
     * @param int $studentId
     * @param string $code
     * @param array $additionalData
     * @return StudentScholarshipAward
     * @throws ValidationException
     */
    public function assignScholarshipToStudent(
        int $studentId,
        string $code,
        array $additionalData = []
    ): StudentScholarshipAward {
        // Validate scholarship code exists
        $scholarship = ScholarshipDefinition::where('code', $code)->first();

        if (!$scholarship) {
            throw ValidationException::withMessages([
                'scholarship_code' => ['The scholarship code does not exist.']
            ]);
        }

        // Check if student already has a scholarship
        $existingAward = StudentScholarshipAward::where('student_id', $studentId)->first();

        if ($existingAward) {
            throw ValidationException::withMessages([
                'student_id' => ['This student already has a scholarship assigned. Please remove it first.']
            ]);
        }

        $data = array_merge([
            'student_id' => $studentId,
            'scholarship_code' => $code,
            'awarded_at' => Carbon::now(),
        ], $additionalData);

        return StudentScholarshipAward::create($data);
    }

    /**
     * Remove a scholarship assignment from a student.
     *
     * @param int $studentId
     * @return bool
     */
    public function removeScholarshipFromStudent(int $studentId): bool
    {
        $award = StudentScholarshipAward::where('student_id', $studentId)->first();

        if (!$award) {
            return false;
        }

        return $award->delete();
    }

    /**
     * Replace a student's scholarship with a different one.
     *
     * @param int $studentId
     * @param string $newCode
     * @param array $additionalData
     * @return StudentScholarshipAward
     */
    public function replaceStudentScholarship(
        int $studentId,
        string $newCode,
        array $additionalData = []
    ): StudentScholarshipAward {
        return DB::transaction(function () use ($studentId, $newCode, $additionalData) {
            // Remove existing scholarship
            $this->removeScholarshipFromStudent($studentId);

            // Assign new scholarship
            return $this->assignScholarshipToStudent($studentId, $newCode, $additionalData);
        });
    }

    /**
     * Get all active scholarships.
     *
     * @return Collection
     */
    public function getActiveScholarships(): Collection
    {
        return ScholarshipDefinition::active()->get();
    }

    /**
     * Get scholarships valid on a specific date.
     *
     * @param Carbon|null $date
     * @return Collection
     */
    public function getValidScholarships(?Carbon $date = null): Collection
    {
        $checkDate = $date ?? Carbon::now();
        return ScholarshipDefinition::validOn($checkDate)->get();
    }

    /**
     * Validate if a scholarship code is unique.
     *
     * @param string $code
     * @return bool
     */
    public function validateScholarshipCode(string $code): bool
    {
        return !ScholarshipDefinition::where('code', $code)->exists();
    }

    /**
     * Check if a scholarship is valid on a specific date.
     *
     * @param string $code
     * @param Carbon|null $date
     * @return bool
     */
    public function isScholarshipValid(string $code, ?Carbon $date = null): bool
    {
        $scholarship = ScholarshipDefinition::where('code', $code)->first();

        if (!$scholarship) {
            return false;
        }

        return $scholarship->isValid($date);
    }

    /**
     * Get a student's scholarship award.
     *
     * @param int $studentId
     * @return StudentScholarshipAward|null
     */
    public function getStudentScholarship(int $studentId): ?StudentScholarshipAward
    {
        return StudentScholarshipAward::where('student_id', $studentId)
            ->with('scholarshipDefinition')
            ->first();
    }

    /**
     * Get all students assigned to a specific scholarship.
     *
     * @param string $code
     * @return Collection
     */
    public function getScholarshipStudents(string $code): Collection
    {
        return StudentScholarshipAward::where('scholarship_code', $code)
            ->with('student')
            ->get();
    }
}
