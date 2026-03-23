<?php

namespace App\Modules\Academic\Actions;

use App\Models\CourseOffering;
use App\Services\CourseCompletionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MarkCourseOfferingCompletedAction
{
    /**
     * Mark a course offering as completed.
     *
     * @param  bool  $recalculate  If true, allows recalculating already completed courses
     *
     * @throws RuntimeException
     */
    public static function run(CourseOffering $courseOffering, bool $recalculate = false): array
    {
        // Ensure the course offering belongs to current campus
        // Note: In an API context, middleware usually handles campus scope,
        // but explicit check doesn't hurt.
        if ($courseOffering->campus_id !== app('campus')->id) {
            throw new RuntimeException('Course offering not found in current campus.', 404);
        }

        // Check if course is already completed - cannot modify unless recalculating
        if ($courseOffering->course_status === 'completed' && ! $recalculate) {
            throw new RuntimeException('Course is already completed.');
        }

        // Validation: Check for unmarked sessions or all-fail sessions
        $classSessions = $courseOffering->classSessions()
            ->where('status', '!=', 'cancelled')
            ->with(['attendances' => function ($q) {
                $q->select('id', 'class_session_id', 'status', 'recording_method');
            }])
            ->get();

        $unmarkedSessions = [];
        $autoSystemOnlySessions = [];

        foreach ($classSessions as $session) {
            // Check 1: Unmarked attendance
            if ($session->attendances->isEmpty()) {
                $unmarkedSessions[] = "{$session->session_title} ({$session->formatted_date})";

                continue;
            }

            // Check 2: auto_system-only attendance means attendance has not been manually finalized yet.
            $hasManualAttendance = $session->attendances->contains(function ($attendance) {
                return $attendance->recording_method !== 'auto_system';
            });

            if (! $hasManualAttendance) {
                $autoSystemOnlySessions[] = "{$session->session_title} ({$session->formatted_date})";
            }
        }

        if (! empty($unmarkedSessions)) {
            throw new RuntimeException('Cannot complete course. Attendance has not been taken for the following sessions: ' . implode(', ', $unmarkedSessions));
        }

        if (! empty($autoSystemOnlySessions)) {
            throw new RuntimeException('Cannot complete course. The following sessions only have auto_system attendance and still require manual attendance confirmation: ' . implode(', ', $autoSystemOnlySessions));
        }

        try {
            return DB::transaction(function () use ($courseOffering, $recalculate) {
                // Load necessary relationships
                $courseOffering->load(['unit', 'semester']);

                // Finalize course with all validations and EGC progression
                $result = app(CourseCompletionService::class)->finalizeCourse($courseOffering, $recalculate);

                // Update course offering status (only if not already completed)
                if ($courseOffering->course_status !== 'completed') {
                    $courseOffering->update(['course_status' => 'completed']);
                }

                Log::info('Course status updated to completed', [
                    'course_offering_id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                    'old_status' => $courseOffering->course_status,
                    'new_status' => 'completed',
                    'egc_result' => $result['egc_progression'] ?? null,
                ]);

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Failed to complete course: ' . $e->getMessage(), [
                'course_offering_id' => $courseOffering->id,
                'course_code' => $courseOffering->course_code,
            ]);

            throw new RuntimeException('Failed to complete course: ' . $e->getMessage());
        }
    }
}
