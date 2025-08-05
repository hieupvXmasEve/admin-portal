<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\AcademicHold;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class HoldsManagementService
{
    /**
     * Place a hold on a student account
     */
    public function placeHold(array $data): AcademicHold
    {
        return DB::transaction(function () use ($data) {
            // Validate student exists
            $student = Student::findOrFail($data['student_code']);

            // Check for duplicate active holds of the same type
            $existingHold = AcademicHold::where('student_code', $student->id)
                ->where('hold_type', $data['hold_type'])
                ->where('status', 'active')
                ->first();

            if ($existingHold) {
                throw new Exception("Student already has an active {$data['hold_type']} hold");
            }

            // Create the hold
            $hold = AcademicHold::create(array_merge($data, [
                'status' => 'active',
                'placed_date' => $data['placed_date'] ?? now()->toDateString(),
            ]));

            // Log the action
            $this->logHoldAction($hold, 'placed');

            return $hold->fresh(['student', 'placedByUser']);
        });
    }

    /**
     * Resolve a hold
     */
    public function resolveHold(AcademicHold $hold, int $resolvedByUserId, string $notes = null): bool
    {
        return DB::transaction(function () use ($hold, $resolvedByUserId, $notes) {
            if (!$hold->isActive()) {
                throw new Exception('Hold is not active and cannot be resolved');
            }

            $success = $hold->resolve($resolvedByUserId, $notes);

            if ($success) {
                $this->logHoldAction($hold, 'resolved', $resolvedByUserId);
            }

            return $success;
        });
    }

    /**
     * Waive a hold
     */
    public function waiveHold(AcademicHold $hold, int $resolvedByUserId, string $notes = null): bool
    {
        return DB::transaction(function () use ($hold, $resolvedByUserId, $notes) {
            if (!$hold->isActive()) {
                throw new Exception('Hold is not active and cannot be waived');
            }

            $success = $hold->waive($resolvedByUserId, $notes);

            if ($success) {
                $this->logHoldAction($hold, 'waived', $resolvedByUserId);
            }

            return $success;
        });
    }

    /**
     * Get all holds for a student
     */
    public function getStudentHolds(Student $student, string $status = null): array
    {
        $query = AcademicHold::with(['placedByUser', 'resolvedByUser'])
            ->where('student_code', $student->id);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('placed_date', 'desc')->get()->toArray();
    }

    /**
     * Get active holds that block registration
     */
    public function getRegistrationBlockingHolds(Student $student): array
    {
        return AcademicHold::where('student_code', $student->id)
            ->active()
            ->whereIn('hold_category', ['registration', 'all'])
            ->with(['placedByUser'])
            ->orderBy('priority', 'desc')
            ->orderBy('placed_date', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Check if student has any active holds
     */
    public function hasActiveHolds(Student $student, string $category = null): bool
    {
        $query = AcademicHold::where('student_code', $student->id)->active();

        if ($category) {
            $query->whereIn('hold_category', [$category, 'all']);
        }

        return $query->exists();
    }

    /**
     * Create financial hold for unpaid tuition
     */
    public function createFinancialHold(Student $student, float $amount, string $description, int $placedByUserId): AcademicHold
    {
        return $this->placeHold([
            'student_code' => $student->id,
            'hold_type' => 'financial',
            'hold_category' => 'registration',
            'title' => 'Unpaid Tuition',
            'description' => $description,
            'amount' => $amount,
            'priority' => 'high',
            'placed_by_user_id' => $placedByUserId,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);
    }

    /**
     * Create academic hold for low GPA
     */
    public function createAcademicHold(Student $student, float $gpa, int $placedByUserId): AcademicHold
    {
        return $this->placeHold([
            'student_code' => $student->id,
            'hold_type' => 'academic',
            'hold_category' => 'registration',
            'title' => 'Academic Probation',
            'description' => "Student placed on academic probation due to low GPA: {$gpa}",
            'priority' => 'medium',
            'placed_by_user_id' => $placedByUserId,
        ]);
    }

    /**
     * Create administrative hold
     */
    public function createAdministrativeHold(Student $student, string $title, string $description, int $placedByUserId, string $category = 'registration'): AcademicHold
    {
        return $this->placeHold([
            'student_code' => $student->id,
            'hold_type' => 'administrative',
            'hold_category' => $category,
            'title' => $title,
            'description' => $description,
            'priority' => 'medium',
            'placed_by_user_id' => $placedByUserId,
        ]);
    }

    /**
     * Automatically expire overdue holds
     */
    public function expireOverdueHolds(): int
    {
        $expiredCount = 0;

        $overdueHolds = AcademicHold::active()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->get();

        foreach ($overdueHolds as $hold) {
            $hold->update(['status' => 'expired']);
            $this->logHoldAction($hold, 'expired');
            $expiredCount++;
        }

        return $expiredCount;
    }

    /**
     * Get holds summary for dashboard
     */
    public function getHoldsSummary(int $campusId = null): array
    {
        $query = AcademicHold::query();

        if ($campusId) {
            $query->whereHas('student', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            });
        }

        return [
            'total_active_holds' => $query->clone()->active()->count(),
            'financial_holds' => $query->clone()->active()->byType('financial')->count(),
            'academic_holds' => $query->clone()->active()->byType('academic')->count(),
            'administrative_holds' => $query->clone()->active()->byType('administrative')->count(),
            'high_priority_holds' => $query->clone()->active()->byPriority('high')->count(),
            'overdue_holds' => $query->clone()->active()
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->count(),
        ];
    }

    /**
     * Get students with active holds
     */
    public function getStudentsWithHolds(int $campusId = null, string $holdType = null): array
    {
        $query = Student::with(['academicHolds' => function ($q) use ($holdType) {
            $q->active();
            if ($holdType) {
                $q->where('hold_type', $holdType);
            }
        }])->whereHas('academicHolds', function ($q) use ($holdType) {
            $q->active();
            if ($holdType) {
                $q->where('hold_type', $holdType);
            }
        });

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        return $query->get()->map(function ($student) {
            return [
                'student' => $student,
                'active_holds_count' => $student->academicHolds->count(),
                'highest_priority' => $student->academicHolds->max('priority'),
                'total_amount' => $student->academicHolds->sum('amount'),
            ];
        })->toArray();
    }

    /**
     * Bulk resolve holds
     */
    public function bulkResolveHolds(array $holdIds, int $resolvedByUserId, string $notes = null): int
    {
        $resolvedCount = 0;

        foreach ($holdIds as $holdId) {
            try {
                $hold = AcademicHold::findOrFail($holdId);
                if ($this->resolveHold($hold, $resolvedByUserId, $notes)) {
                    $resolvedCount++;
                }
            } catch (Exception $e) {
                // Log error but continue with other holds
                continue;
            }
        }

        return $resolvedCount;
    }

    /**
     * Log hold actions for audit trail
     */
    private function logHoldAction(AcademicHold $hold, string $action, int $userId = null): void
    {
        // TODO: Implement logging to audit table or log file
        // This would record all hold-related actions for compliance and tracking
    }

    /**
     * Send notification about hold placement/resolution
     */
    private function sendHoldNotification(AcademicHold $hold, string $action): void
    {
        // TODO: Implement notification system
        // This would send emails/SMS to students about hold status changes
    }
}
