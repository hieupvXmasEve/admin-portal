<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAttendanceToAcademicRecords extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:sync-to-academic-records
                            {--course-offering-id= : Sync specific course offering only}
                            {--force : Force sync even if session not completed}';

    /**
     * The console command description.
     */
    protected $description = 'Sync attendance data from attendances table to academic_records for completed class sessions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting attendance sync to academic records...');
        $startTime = microtime(true);

        try {
            $courseOfferingId = $this->option('course-offering-id');
            $force = $this->option('force');

            if ($courseOfferingId) {
                $this->info("Syncing specific course offering: {$courseOfferingId}");
                $courseOfferings = CourseOffering::where('id', $courseOfferingId)->get();
            } else {
                // Get all active course offerings with completed sessions
                $courseOfferings = $this->getEligibleCourseOfferings($force);
            }

            if ($courseOfferings->isEmpty()) {
                $this->info('No eligible course offerings found.');

                return self::SUCCESS;
            }

            $this->info("Found {$courseOfferings->count()} course offerings to sync");

            $totalSynced = 0;
            $totalErrors = 0;

            $progressBar = $this->output->createProgressBar($courseOfferings->count());
            $progressBar->start();

            foreach ($courseOfferings as $courseOffering) {
                try {
                    $result = $this->syncCourseOfferingAttendance($courseOffering, $force);
                    $totalSynced += $result['synced'];
                    $totalErrors += $result['errors'];
                    $progressBar->advance();
                } catch (\Exception $e) {
                    $totalErrors++;
                    Log::error('Failed to sync course offering attendance', [
                        'course_offering_id' => $courseOffering->id,
                        'error' => $e->getMessage(),
                    ]);
                    $progressBar->advance();
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            $elapsed = round(microtime(true) - $startTime, 2);

            $this->info("✓ Sync completed successfully!");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Course Offerings', $courseOfferings->count()],
                    ['Academic Records Synced', $totalSynced],
                    ['Errors', $totalErrors],
                    ['Time Elapsed', "{$elapsed}s"],
                ]
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Fatal error during sync: '.$e->getMessage());
            Log::error('Fatal error in attendance sync command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Get course offerings eligible for attendance sync
     */
    private function getEligibleCourseOfferings(bool $force): \Illuminate\Database\Eloquent\Collection
    {
        $query = CourseOffering::query()
            ->where('is_active', true)
            ->whereHas('classSessions');

        if (! $force) {
            // Only courses with at least one completed session
            $query->whereHas('classSessions', function ($q) {
                $q->where('status', 'completed');
            });
        }

        return $query->get();
    }

    /**
     * Sync attendance data for a specific course offering
     */
    private function syncCourseOfferingAttendance(CourseOffering $courseOffering, bool $force): array
    {
        $synced = 0;
        $errors = 0;

        DB::beginTransaction();

        try {
            // Get all class sessions for this course offering
            $sessionsQuery = $courseOffering->classSessions();

            if (! $force) {
                // Only completed sessions
                $sessionsQuery->where('status', 'completed');
            }

            $sessions = $sessionsQuery->get();
            $totalSessions = $sessions->count();

            if ($totalSessions === 0) {
                DB::commit();

                return ['synced' => 0, 'errors' => 0];
            }

            $sessionIds = $sessions->pluck('id')->toArray();

            // Calculate allowed absences (20% of total sessions)
            $allowedAbsences = (int) ceil($totalSessions * 0.2);

            // Get all academic records for this course offering
            $academicRecords = AcademicRecord::where('course_offering_id', $courseOffering->id)->get();

            foreach ($academicRecords as $record) {
                try {
                    // Get all attendances for this student in these sessions
                    $attendances = Attendance::whereIn('class_session_id', $sessionIds)
                        ->where('student_id', $record->student_id)
                        ->get();

                    // Count by status
                    $totalPresent = $attendances->where('status', 'present')->count();
                    $totalAbsences = $attendances->where('status', 'absent')->count();
                    $totalLate = $attendances->where('status', 'late')->count();
                    $totalNotRecorded = $attendances->where('status', 'not_recorded')->count();

                    // Calculate attendance percentage
                    $attendancePercentage = $totalSessions > 0
                        ? round((($totalPresent + $totalLate) / $totalSessions) * 100, 2)
                        : 0;

                    // Determine if meets requirement (absences must not exceed allowed)
                    $meetsRequirement = $totalAbsences <= $allowedAbsences;

                    // Update academic record
                    $record->update([
                        'total_class_sessions' => $totalSessions,
                        'total_present' => $totalPresent,
                        'total_absences' => $totalAbsences,
                        'total_late' => $totalLate,
                        'total_not_recorded' => $totalNotRecorded,
                        'attendance_percentage' => $attendancePercentage,
                        'meets_attendance_requirement' => $meetsRequirement,
                    ]);

                    $synced++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to sync attendance for academic record', [
                        'academic_record_id' => $record->id,
                        'student_id' => $record->student_id,
                        'course_offering_id' => $courseOffering->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            Log::info('Course offering attendance synced', [
                'course_offering_id' => $courseOffering->id,
                'total_sessions' => $totalSessions,
                'allowed_absences' => $allowedAbsences,
                'academic_records_synced' => $synced,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return ['synced' => $synced, 'errors' => $errors];
    }
}
