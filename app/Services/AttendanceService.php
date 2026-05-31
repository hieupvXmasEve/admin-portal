<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Create attendance records for all enrolled students in a class session
     * with default status 'absent'
     */
    public function createAttendanceForSession(ClassSession $classSession): array
    {
        try {
            DB::beginTransaction();

            // Lock the class session to prevent race condition
            $lockedSession = ClassSession::lockForUpdate()->find($classSession->id);

            if (! $lockedSession) {
                DB::rollBack();

                return [
                    'success' => false,
                    'created_count' => 0,
                    'message' => 'Class session not found',
                ];
            }

            // Check if attendance records already exist (with lock)
            $existingAttendanceCount = Attendance::where('class_session_id', $classSession->id)->count();

            if ($existingAttendanceCount > 0) {
                DB::rollBack();
                Log::info("Attendance records already exist for class session {$classSession->id}");

                return [
                    'success' => true,
                    'created_count' => 0,
                    'message' => 'Attendance records already exist for this session',
                ];
            }

            // Get enrolled students for this class session
            $students = $this->getEnrolledStudentsForSession($classSession);

            if ($students->isEmpty()) {
                DB::rollBack();
                Log::info("No enrolled students found for class session {$classSession->id}");

                return [
                    'success' => true,
                    'created_count' => 0,
                    'message' => 'No enrolled students found for this session',
                ];
            }

            $attendanceData = [];
            $now = now();

            foreach ($students as $student) {
                $attendanceData[] = [
                    'class_session_id' => $classSession->id,
                    'student_id' => $student->id,
                    'status' => 'absent', // Default status
                    'recording_method' => 'auto_system',
                    'notes' => 'Auto-generated when session started',
                    'affects_grade' => true,
                    'is_verified' => false,
                    'is_makeup_allowed' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Bulk insert attendance records
            $insertedCount = 0;
            if (! empty($attendanceData)) {
                try {
                    Attendance::insert($attendanceData);
                    $insertedCount = count($attendanceData);

                    // Update the class session's expected and actual attendees
                    $classSession->update([
                        'expected_attendees' => $insertedCount,
                        'actual_attendees' => 0, // Initially all are absent
                        'attendance_percentage' => 0.0,
                    ]);

                    Log::info("Created {$insertedCount} attendance records for class session {$classSession->id}");
                } catch (QueryException $e) {
                    // Handle duplicate key error gracefully
                    if ($e->errorInfo[1] === 1062) { // MySQL duplicate entry error code
                        DB::rollBack();
                        Log::info("Attendance records already exist for class session {$classSession->id} (caught duplicate key)");

                        return [
                            'success' => true,
                            'created_count' => 0,
                            'message' => 'Attendance records already exist for this session',
                        ];
                    }
                    throw $e; // Re-throw if it's not a duplicate key error
                }
            }

            DB::commit();

            return [
                'success' => true,
                'created_count' => $insertedCount,
                'message' => "Successfully created {$insertedCount} attendance records",
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create attendance for class session {$classSession->id}: ".$e->getMessage());

            return [
                'success' => false,
                'created_count' => 0,
                'message' => 'Failed to create attendance records: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get all enrolled students for a class session through course registrations
     */
    public function getEnrolledStudentsForSession(ClassSession $classSession): Collection
    {
        return CourseRegistration::query()
            ->where('course_offering_id', $classSession->course_offering_id)
            ->where('semester_id', $classSession->courseOffering->semester_id)
            ->activeForClassRoster()
            ->with('student:id,student_id,full_name,email')
            ->get()
            ->pluck('student')
            ->filter()
            ->values();
    }

    /**
     * Update attendance statistics for a class session
     */
    public function updateAttendanceStatistics(ClassSession $classSession): void
    {
        $activeStudentIds = $classSession->courseOffering->activeClassRosterStudentIds();

        $attendanceStats = $classSession->attendances()
            ->whereIn('student_id', $activeStudentIds)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = "excused" THEN 1 ELSE 0 END) as excused
            ')
            ->first();

        $present = $attendanceStats->present ?? 0;
        $late = $attendanceStats->late ?? 0;

        $actualAttendees = $present + $late;
        $expectedAttendees = $activeStudentIds->count();
        $attendancePercentage = $expectedAttendees > 0
            ? round(($actualAttendees / $expectedAttendees) * 100, 2)
            : 0;

        $classSession->update([
            'expected_attendees' => $expectedAttendees,
            'actual_attendees' => $actualAttendees,
            'attendance_percentage' => $attendancePercentage,
        ]);
    }

    /**
     * Check if a class session has attendance records
     */
    public function hasAttendanceRecords(ClassSession $classSession): bool
    {
        return $classSession->attendances()->exists();
    }

    /**
     * Delete all attendance records for a class session
     * (useful if session is cancelled or rescheduled)
     */
    public function deleteAttendanceForSession(ClassSession $classSession): array
    {
        try {
            $deletedCount = $classSession->attendances()->delete();

            // Reset attendance statistics
            $classSession->update([
                'expected_attendees' => null,
                'actual_attendees' => null,
                'attendance_percentage' => null,
            ]);

            Log::info("Deleted {$deletedCount} attendance records for class session {$classSession->id}");

            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Successfully deleted {$deletedCount} attendance records",
            ];
        } catch (\Exception $e) {
            Log::error("Failed to delete attendance for class session {$classSession->id}: ".$e->getMessage());

            return [
                'success' => false,
                'deleted_count' => 0,
                'message' => 'Failed to delete attendance records: '.$e->getMessage(),
            ];
        }
    }
}
