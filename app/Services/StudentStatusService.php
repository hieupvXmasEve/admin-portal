<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class StudentStatusService
{
    /**
     * Update student academic status
     */
    public function updateStatus(Student $student, string $newStatus, string $reason, User $changedBy): Student
    {
        return DB::transaction(function () use ($student, $newStatus, $reason, $changedBy) {
            $this->validateStatusChange($student, $newStatus);

            $oldStatus = $student->academic_status;

            $student->update([
                'academic_status' => $newStatus,
                'status_change_date' => now(),
                'status_reason' => $reason,
                'status_changed_by' => $changedBy->id,
            ]);

            Log::info('Student status updated', [
                'student_id' => $student->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
                'changed_by' => $changedBy->id,
            ]);

            return $student->fresh();
        });
    }

    /**
     * Get students by status with filtering
     */
    public function getStudentsByStatus(string $status, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Student::where('academic_status', $status)
            ->with(['campus', 'program', 'specialization', 'statusChangedBy']);

        if (!empty($filters['campus_id'])) {
            $query->where('campus_id', $filters['campus_id']);
        }

        if (!empty($filters['program_id'])) {
            $query->where('program_id', $filters['program_id']);
        }

        return $query->orderBy('status_change_date', 'desc')->paginate(15);
    }

    /**
     * Get status change statistics
     */
    public function getStatusStatistics(array $filters = []): array
    {
        $query = Student::query();

        if (!empty($filters['campus_id'])) {
            $query->where('campus_id', $filters['campus_id']);
        }

        $total = $query->count();
        $active = (clone $query)->where('academic_status', 'active')->count();
        $inactive = (clone $query)->where('academic_status', 'inactive')->count();
        $graduated = (clone $query)->where('academic_status', 'graduated')->count();
        $suspended = (clone $query)->where('academic_status', 'suspended')->count();
        $withdrawn = (clone $query)->where('academic_status', 'withdrawn')->count();

        return [
            'total_students' => $total,
            'active_students' => $active,
            'inactive_students' => $inactive,
            'graduated_students' => $graduated,
            'suspended_students' => $suspended,
            'withdrawn_students' => $withdrawn,
        ];
    }

    /**
     * Validate status change
     */
    private function validateStatusChange(Student $student, string $newStatus): void
    {
        $validStatuses = ['active', 'inactive', 'graduated', 'suspended', 'withdrawn'];
        
        if (!in_array($newStatus, $validStatuses)) {
            throw new Exception('Invalid academic status');
        }

        // Prevent changing from graduated status
        if ($student->academic_status === 'graduated' && $newStatus !== 'graduated') {
            throw new Exception('Cannot change status from graduated');
        }

        // Validate specific transitions
        if ($student->academic_status === 'suspended' && $newStatus === 'graduated') {
            throw new Exception('Cannot graduate a suspended student');
        }
    }
}