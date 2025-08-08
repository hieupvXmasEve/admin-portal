<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\ProgramChangeRequest;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProgramChangeService
{
    /**
     * Create a new program change request
     */
    public function createChangeRequest(Student $student, array $data): ProgramChangeRequest
    {
        return DB::transaction(function () use ($student, $data) {
            // Validate the request
            $this->validateChangeRequest($student, $data);

            // Evaluate affected credits
            $affectedCredits = $this->evaluateAffectedCredits($student, $data);

            $request = ProgramChangeRequest::create([
                'student_code' => $student->id,
                'from_program_id' => $student->program_id,
                'to_program_id' => $data['to_program_id'],
                'from_specialization_id' => $student->specialization_id,
                'to_specialization_id' => $data['to_specialization_id'] ?? null,
                'reason' => $data['reason'],
                'status' => 'pending',
                'affected_credits' => $affectedCredits,
            ]);

            Log::info('Program change request created', [
                'student_code' => $student->id,
                'request_id' => $request->id,
                'from_program' => $student->program_id,
                'to_program' => $data['to_program_id'],
            ]);

            return $request->fresh(['student', 'fromProgram', 'toProgram', 'fromSpecialization', 'toSpecialization']);
        });
    }

    /**
     * Approve a program change request
     */
    public function approveChangeRequest(ProgramChangeRequest $request, User $approver, array $data = []): ProgramChangeRequest
    {
        return DB::transaction(function () use ($request, $approver, $data) {
            if (! $request->canBeApproved()) {
                throw new Exception('Program change request cannot be approved in its current status');
            }

            // Update the request
            $request->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_notes' => $data['approval_notes'] ?? null,
            ]);

            // Apply the program change to the student
            $this->applyProgramChange($request);

            Log::info('Program change request approved', [
                'request_id' => $request->id,
                'student_code' => $request->student_code,
                'approved_by' => $approver->id,
            ]);

            return $request->fresh();
        });
    }

    /**
     * Reject a program change request
     */
    public function rejectChangeRequest(ProgramChangeRequest $request, User $approver, string $reason): ProgramChangeRequest
    {
        return DB::transaction(function () use ($request, $approver, $reason) {
            if (! $request->canBeRejected()) {
                throw new Exception('Program change request cannot be rejected in its current status');
            }

            $request->update([
                'status' => 'rejected',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_notes' => $reason,
            ]);

            Log::info('Program change request rejected', [
                'request_id' => $request->id,
                'student_code' => $request->student_code,
                'rejected_by' => $approver->id,
                'reason' => $reason,
            ]);

            return $request->fresh();
        });
    }

    /**
     * Get program change requests with filtering
     */
    public function getChangeRequests(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = ProgramChangeRequest::with([
            'student',
            'fromProgram',
            'toProgram',
            'fromSpecialization',
            'toSpecialization',
            'approvedBy',
        ])->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['student_code'])) {
            $query->where('student_code', $filters['student_code']);
        }

        if (! empty($filters['from_program_id'])) {
            $query->where('from_program_id', $filters['from_program_id']);
        }

        if (! empty($filters['to_program_id'])) {
            $query->where('to_program_id', $filters['to_program_id']);
        }

        if (! empty($filters['campus_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('campus_id', $filters['campus_id']);
            });
        }

        return $query->paginate(15);
    }

    /**
     * Evaluate how credits will be affected by the program change
     */
    public function evaluateAffectedCredits(Student $student, array $changeData): array
    {
        $toProgram = Program::findOrFail($changeData['to_program_id']);
        $toSpecialization = isset($changeData['to_specialization_id'])
            ? Specialization::find($changeData['to_specialization_id'])
            : null;

        // Get the target curriculum version
        $targetCurriculumVersion = $this->findCurrentCurriculumVersion(
            $toProgram->id,
            $toSpecialization?->id
        );

        // Get student's completed academic records
        $completedRecords = $student->academicRecords()
            ->whereNotNull('final_grade')
            ->with('unit')
            ->get();

        $transferableCredits = 0;
        $nonTransferableCredits = 0;
        $transferableUnits = [];
        $nonTransferableUnits = [];

        foreach ($completedRecords as $record) {
            $unit = $record->unit;

            // Check if this unit is part of the target curriculum
            $isTransferable = $this->isUnitTransferable($unit, $targetCurriculumVersion);

            if ($isTransferable) {
                $transferableCredits += $unit->credit_points;
                $transferableUnits[] = [
                    'unit_id' => $unit->id,
                    'unit_code' => $unit->unit_code,
                    'unit_name' => $unit->unit_name,
                    'credits' => $unit->credit_points,
                    'grade' => $record->final_grade,
                ];
            } else {
                $nonTransferableCredits += $unit->credit_points;
                $nonTransferableUnits[] = [
                    'unit_id' => $unit->id,
                    'unit_code' => $unit->unit_code,
                    'unit_name' => $unit->unit_name,
                    'credits' => $unit->credit_points,
                    'grade' => $record->final_grade,
                ];
            }
        }

        return [
            'transferable_credits' => $transferableCredits,
            'non_transferable_credits' => $nonTransferableCredits,
            'transferable_units' => $transferableUnits,
            'non_transferable_units' => $nonTransferableUnits,
            'total_completed_credits' => $transferableCredits + $nonTransferableCredits,
            'curriculum_version_id' => $targetCurriculumVersion->id,
        ];
    }

    /**
     * Apply the approved program change to the student
     */
    private function applyProgramChange(ProgramChangeRequest $request): void
    {
        $student = $request->student;

        // Find the appropriate curriculum version for the new program
        $newCurriculumVersion = $this->findCurrentCurriculumVersion(
            $request->to_program_id,
            $request->to_specialization_id
        );

        // Update student's program information
        $student->update([
            'program_id' => $request->to_program_id,
            'specialization_id' => $request->to_specialization_id,
            'curriculum_version_id' => $newCurriculumVersion->id,
        ]);

        // Record the change in student's history if needed
        // This could be implemented as a separate student history table

        Log::info('Program change applied to student', [
            'student_code' => $student->id,
            'old_program_id' => $request->from_program_id,
            'new_program_id' => $request->to_program_id,
            'new_curriculum_version_id' => $newCurriculumVersion->id,
        ]);
    }

    /**
     * Validate a program change request
     */
    private function validateChangeRequest(Student $student, array $data): void
    {
        // Check if student has any pending change requests
        if ($student->hasPendingProgramChange()) {
            throw new Exception('Student already has a pending program change request');
        }

        // Check if the target program exists
        $toProgram = Program::find($data['to_program_id']);
        if (! $toProgram) {
            throw new Exception('Target program does not exist');
        }

        // Check if the target program is different from current
        if (
            $student->program_id === $data['to_program_id'] &&
            $student->specialization_id === ($data['to_specialization_id'] ?? null)
        ) {
            throw new Exception('Target program must be different from current program');
        }

        // Check if specialization belongs to the target program
        if (! empty($data['to_specialization_id'])) {
            $specialization = Specialization::where('id', $data['to_specialization_id'])
                ->where('program_id', $data['to_program_id'])
                ->first();

            if (! $specialization) {
                throw new Exception('Selected specialization does not belong to the target program');
            }
        }

        // Check if student is in good academic standing
        $currentStanding = $student->getCurrentAcademicStanding();
        if ($currentStanding && in_array($currentStanding->standing, ['suspension', 'probation'])) {
            throw new Exception('Students on academic probation or suspension cannot change programs');
        }
    }

    /**
     * Check if a unit is transferable to the target curriculum
     */
    private function isUnitTransferable($unit, $targetCurriculumVersion): bool
    {
        // Check if the unit is part of the target curriculum version
        return $targetCurriculumVersion->curriculumUnits()
            ->where('unit_id', $unit->id)
            ->exists();
    }

    /**
     * Find the current curriculum version for a program/specialization
     */
    private function findCurrentCurriculumVersion(int $programId, ?int $specializationId = null): CurriculumVersion
    {
        $query = CurriculumVersion::where('program_id', $programId)
            ->where('is_active', true)
            ->orderBy('version', 'desc');

        if ($specializationId) {
            $query->where('specialization_id', $specializationId);
        } else {
            $query->whereNull('specialization_id');
        }

        $curriculumVersion = $query->first();

        if (! $curriculumVersion) {
            throw new Exception('No active curriculum version found for the target program/specialization');
        }

        return $curriculumVersion;
    }

    /**
     * Get program change statistics
     */
    public function getChangeStatistics(array $filters = []): array
    {
        $query = ProgramChangeRequest::query();

        if (! empty($filters['campus_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('campus_id', $filters['campus_id']);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $total = $query->count();
        $pending = (clone $query)->where('status', 'pending')->count();
        $approved = (clone $query)->where('status', 'approved')->count();
        $rejected = (clone $query)->where('status', 'rejected')->count();

        return [
            'total_requests' => $total,
            'pending_requests' => $pending,
            'approved_requests' => $approved,
            'rejected_requests' => $rejected,
            'approval_rate' => $total > 0 ? round(($approved / $total) * 100, 1) : 0,
        ];
    }
}
