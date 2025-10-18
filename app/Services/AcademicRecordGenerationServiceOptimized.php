<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

/**
 * Optimized version for handling large datasets (hundreds of students, thousands of attendances)
 *
 * Key optimizations:
 * - Chunking to prevent memory overflow
 * - Bulk operations (upsert) instead of individual creates
 * - Query optimization with caching
 * - Segmented transactions
 * - Progress callback support
 */
class AcademicRecordGenerationServiceOptimized
{
    private const CHUNK_SIZE = 100;

    private const BATCH_SIZE = 500;

    /**
     * Cache for course offerings to avoid repeated queries
     */
    private array $courseOfferingCache = [];

    /**
     * Cache for assessment components by syllabus template
     */
    private array $assessmentComponentCache = [];

    /**
     * Generate academic records for students with attendance records (optimized with chunking)
     */
    public function generateAcademicRecords(?callable $progressCallback = null): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_students' => 0,
            'total_courses' => 0,
        ];

        // Disable query log for performance
        DB::connection()->disableQueryLog();

        try {
            // Get student-course pairs efficiently using raw SQL
            $studentCoursePairs = $this->getStudentCoursePairs();
            $stats['total_students'] = $studentCoursePairs->pluck('student_id')->unique()->count();
            $stats['total_courses'] = $studentCoursePairs->count();

            if ($progressCallback) {
                $progressCallback(0, $stats['total_courses'], 'Starting...');
            }

            // Process in chunks to avoid memory overflow
            $processed = 0;
            foreach ($studentCoursePairs->chunk(self::CHUNK_SIZE) as $chunk) {
                // Each chunk has its own transaction
                DB::transaction(function () use ($chunk, &$stats, &$processed, $progressCallback) {
                    $this->processChunk($chunk, $stats);
                    $processed += $chunk->count();

                    if ($progressCallback) {
                        $progressCallback($processed, $stats['total_courses'], "Processed {$processed} records");
                    }
                });

                // Clear caches periodically to prevent memory buildup
                if ($processed % 500 === 0) {
                    $this->clearCaches();
                }
            }

            if ($progressCallback) {
                $progressCallback($stats['total_courses'], $stats['total_courses'], 'Completed');
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate academic records', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $stats;
    }

    /**
     * Get student-course pairs efficiently from attendance data
     */
    private function getStudentCoursePairs(): Collection
    {
        return DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->select('attendances.student_id', 'class_sessions.course_offering_id')
            ->whereNull('attendances.deleted_at')
            ->whereNull('class_sessions.deleted_at')
            ->distinct()
            ->get();
    }

    /**
     * Process a chunk of student-course pairs
     */
    private function processChunk(Collection $chunk, array &$stats): void
    {
        // Pre-load all necessary data for this chunk
        $studentIds = $chunk->pluck('student_id')->unique()->toArray();
        $courseOfferingIds = $chunk->pluck('course_offering_id')->unique()->toArray();

        // Load students
        $students = Student::whereIn('id', $studentIds)->get()->keyBy('id');

        // Load course offerings with relationships
        $this->preloadCourseOfferings($courseOfferingIds);

        // Calculate attendance stats in bulk
        $attendanceStatsMap = $this->calculateAttendanceStatsBulk($studentIds, $courseOfferingIds);

        // Prepare academic records data for bulk insert/update
        $academicRecordsData = [];
        $assessmentScoresData = [];

        foreach ($chunk as $pair) {
            $student = $students->get($pair->student_id);
            if (! $student) {
                $stats['errors']++;

                continue;
            }

            $courseOffering = $this->getCachedCourseOffering($pair->course_offering_id);
            if (! $courseOffering) {
                $stats['errors']++;

                continue;
            }

            $key = "{$pair->student_id}_{$pair->course_offering_id}";
            $attendanceStats = $attendanceStatsMap[$key] ?? null;

            if (! $attendanceStats) {
                $stats['skipped']++;

                continue;
            }

            // Get credit hours from syllabus template (total_hours) or fallback to unit
            $creditHours = $courseOffering->syllabusTemplate->total_hours
                ?? $courseOffering->unit->credit_points
                ?? 0;

            // Skip if no valid credit hours (violates check constraint)
            if ($creditHours <= 0) {
                $stats['skipped']++;

                continue;
            }

            // Prepare academic record data
            $academicRecordsData[] = [
                'student_id' => $student->id,
                'course_offering_id' => $courseOffering->id,
                'semester_id' => $courseOffering->semester_id,
                'unit_id' => $courseOffering->unit_id,
                'program_id' => $student->program_id,
                'campus_id' => $courseOffering->campus_id ?? $student->campus_id,
                'enrollment_date' => now(),
                'completion_status' => 'in_progress',
                'grade_status' => 'in_progress',
                'credit_hours' => $creditHours,
                'attendance_percentage' => $attendanceStats['attendance_percentage'],
                'total_absences' => $attendanceStats['total_absences'],
                'total_present' => $attendanceStats['total_present'],
                'total_late' => $attendanceStats['total_late'],
                'total_not_recorded' => $attendanceStats['total_not_recorded'],
                'total_class_sessions' => $attendanceStats['total_sessions'],
                'meets_attendance_requirement' => $attendanceStats['meets_requirement'],
                'is_repeat_course' => false,
                'attempt_number' => 1,
                'is_transfer_credit' => false,
                'excluded_from_gpa' => false,
                'affects_academic_standing' => true,
                'affects_graduation_requirement' => true,
                'satisfies_prerequisite' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Prepare assessment scores data
            $this->prepareAssessmentScoresData(
                $student->id,
                $courseOffering,
                $attendanceStats,
                $assessmentScoresData
            );
        }

        // Bulk upsert academic records
        if (! empty($academicRecordsData)) {
            try {
                $result = DB::table('academic_records')->upsert(
                    $academicRecordsData,
                    ['student_id', 'course_offering_id'], // Unique keys
                    ['attendance_percentage', 'total_absences', 'total_present', 'total_late', 'total_not_recorded', 'total_class_sessions', 'meets_attendance_requirement', 'updated_at'] // Fields to update
                );
                $stats['created'] += count($academicRecordsData);
            } catch (\Exception $e) {
                Log::error('Failed to upsert academic records', [
                    'error' => $e->getMessage(),
                    'count' => count($academicRecordsData),
                ]);
                $stats['errors'] += count($academicRecordsData);
            }
        }

        // Bulk upsert assessment scores
        if (! empty($assessmentScoresData)) {
            try {
                DB::table('assessment_component_detail_scores')->upsert(
                    $assessmentScoresData,
                    ['assessment_component_detail_id', 'student_id', 'course_offering_id'], // Unique keys
                    ['points_earned', 'percentage_score', 'instructor_feedback', 'graded_at', 'updated_at'] // Fields to update
                );
            } catch (\Exception $e) {
                Log::error('Failed to upsert assessment scores', [
                    'error' => $e->getMessage(),
                    'count' => count($assessmentScoresData),
                ]);
            }
        }
    }

    /**
     * Calculate attendance stats for multiple students and courses in bulk
     */
    private function calculateAttendanceStatsBulk(array $studentIds, array $courseOfferingIds): array
    {
        // Get total sessions per course offering
        $sessionCounts = DB::table('class_sessions')
            ->whereIn('course_offering_id', $courseOfferingIds)
            ->whereNull('deleted_at')
            ->select('course_offering_id', DB::raw('COUNT(*) as total'))
            ->groupBy('course_offering_id')
            ->pluck('total', 'course_offering_id')
            ->toArray();

        // Get attendance stats in bulk
        $attendanceStats = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->whereIn('attendances.student_id', $studentIds)
            ->whereIn('class_sessions.course_offering_id', $courseOfferingIds)
            ->whereNull('attendances.deleted_at')
            ->whereNull('class_sessions.deleted_at')
            ->select(
                'attendances.student_id',
                'class_sessions.course_offering_id',
                DB::raw('SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count'),
                DB::raw('SUM(CASE WHEN attendances.status = "late" THEN 1 ELSE 0 END) as late_count'),
                DB::raw('SUM(CASE WHEN attendances.status = "excused" THEN 1 ELSE 0 END) as excused_count'),
                DB::raw('SUM(CASE WHEN attendances.status IN ("present", "late", "excused") THEN 1 ELSE 0 END) as total_attended'),
                DB::raw('SUM(CASE WHEN attendances.status = "absent" THEN 1 ELSE 0 END) as absent_count')
            )
            ->groupBy('attendances.student_id', 'class_sessions.course_offering_id')
            ->get();

        // Build stats map
        $statsMap = [];
        foreach ($attendanceStats as $stat) {
            $totalSessions = $sessionCounts[$stat->course_offering_id] ?? 0;

            if ($totalSessions === 0) {
                continue;
            }

            // Calculate not recorded: total sessions - (all recorded attendances including excused)
            $totalRecorded = $stat->present_count + $stat->late_count + $stat->excused_count + $stat->absent_count;
            $notRecorded = $totalSessions - $totalRecorded;

            // Calculate attendance percentage based on recorded sessions only (excluding not recorded)
            // If no sessions recorded yet, percentage is 0
            $attendancePercentage = $totalRecorded > 0
                ? round(($stat->total_attended / $totalRecorded) * 100, 2)
                : 0;
            $meetsRequirement = $attendancePercentage >= 80;

            $key = "{$stat->student_id}_{$stat->course_offering_id}";
            $statsMap[$key] = [
                'attendance_percentage' => $attendancePercentage,
                'total_absences' => $stat->absent_count,
                'total_present' => $stat->present_count,
                'total_late' => $stat->late_count,
                'total_not_recorded' => $notRecorded,
                'total_sessions' => $totalSessions,
                'present_count' => $stat->total_attended,
                'meets_requirement' => $meetsRequirement,
            ];
        }

        return $statsMap;
    }

    /**
     * Pre-load course offerings with relationships
     */
    private function preloadCourseOfferings(array $courseOfferingIds): void
    {
        $courseOfferings = CourseOffering::with(['unit', 'semester', 'campus', 'syllabusTemplate'])
            ->whereIn('id', $courseOfferingIds)
            ->get();

        foreach ($courseOfferings as $courseOffering) {
            $this->courseOfferingCache[$courseOffering->id] = $courseOffering;

            // Pre-load assessment components for this syllabus
            if ($courseOffering->syllabusTemplate) {
                $this->preloadAssessmentComponents($courseOffering->syllabus_template_id);
            }
        }
    }

    /**
     * Pre-load assessment components for a syllabus template
     */
    private function preloadAssessmentComponents(int $syllabusTemplateId): void
    {
        if (isset($this->assessmentComponentCache[$syllabusTemplateId])) {
            return;
        }

        $components = AssessmentComponent::where('syllabus_template_id', $syllabusTemplateId)
            ->where('type', 'attendance')
            ->with('details')
            ->get();

        $this->assessmentComponentCache[$syllabusTemplateId] = $components;
    }

    /**
     * Get cached course offering
     */
    private function getCachedCourseOffering(int $courseOfferingId): ?CourseOffering
    {
        return $this->courseOfferingCache[$courseOfferingId] ?? null;
    }

    /**
     * Prepare assessment scores data for bulk insert
     */
    private function prepareAssessmentScoresData(
        int $studentId,
        CourseOffering $courseOffering,
        array $attendanceStats,
        array &$assessmentScoresData
    ): void {
        if (! $courseOffering->syllabusTemplate) {
            return;
        }

        $components = $this->assessmentComponentCache[$courseOffering->syllabus_template_id] ?? collect();

        if ($components->isEmpty()) {
            return;
        }

        foreach ($components as $component) {
            // Ensure component has details
            if ($component->details->isEmpty()) {
                // Create default detail if not exists
                $detail = AssessmentComponentDetail::firstOrCreate(
                    ['assessment_component_id' => $component->id],
                    [
                        'name' => $component->name,
                        'weight' => 100.00,
                    ]
                );
                $component->setRelation('details', collect([$detail]));
            }

            foreach ($component->details as $detail) {
                $attendanceScore = $attendanceStats['attendance_percentage'];

                $assessmentScoresData[] = [
                    'assessment_component_detail_id' => $detail->id,
                    'student_id' => $studentId,
                    'course_offering_id' => $courseOffering->id,
                    'points_earned' => $attendanceStats['present_count'],
                    'percentage_score' => $attendanceScore,
                    'status' => 'graded',
                    'score_status' => 'final',
                    'graded_at' => now(),
                    'submitted_at' => now(),
                    'is_late' => false,
                    'score_excluded' => false,
                    'instructor_feedback' => sprintf(
                        'Attendance: %d/%d sessions (%s%%) - %s',
                        $attendanceStats['present_count'],
                        $attendanceStats['total_sessions'],
                        $attendanceScore,
                        $attendanceStats['meets_requirement'] ? 'Meets requirement' : 'Does not meet (absent more than 20% of sessions)'
                    ),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
    }

    /**
     * Clear caches to prevent memory buildup
     */
    private function clearCaches(): void
    {
        $this->courseOfferingCache = [];
        $this->assessmentComponentCache = [];
    }

    /**
     * Update attendance stats for existing academic records (optimized version)
     */
    public function updateAttendanceStatsForExistingRecords(?callable $progressCallback = null): array
    {
        $stats = [
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        DB::connection()->disableQueryLog();

        $totalRecords = AcademicRecord::whereNotNull('course_offering_id')->count();
        $processed = 0;

        if ($progressCallback) {
            $progressCallback(0, $totalRecords, 'Starting update...');
        }

        // Use lazy() for memory-efficient iteration
        AcademicRecord::whereNotNull('course_offering_id')
            ->with(['student', 'courseOffering.syllabusTemplate'])
            ->lazy(self::CHUNK_SIZE)
            ->chunk(self::CHUNK_SIZE)
            ->each(function (LazyCollection $records) use (&$stats, &$processed, $totalRecords, $progressCallback) {
                DB::transaction(function () use ($records, &$stats) {
                    $studentIds = $records->pluck('student_id')->unique()->toArray();
                    $courseOfferingIds = $records->pluck('course_offering_id')->unique()->toArray();

                    // Calculate stats in bulk
                    $attendanceStatsMap = $this->calculateAttendanceStatsBulk($studentIds, $courseOfferingIds);

                    $updates = [];
                    $assessmentScoresData = [];

                    foreach ($records as $record) {
                        try {
                            $key = "{$record->student_id}_{$record->course_offering_id}";
                            $attendanceStats = $attendanceStatsMap[$key] ?? null;

                            if (! $attendanceStats) {
                                $stats['skipped']++;

                                continue;
                            }

                            // Prepare bulk update data
                            $updates[] = [
                                'id' => $record->id,
                                'attendance_percentage' => $attendanceStats['attendance_percentage'],
                                'total_absences' => $attendanceStats['total_absences'],
                                'total_present' => $attendanceStats['total_present'],
                                'total_late' => $attendanceStats['total_late'],
                                'total_not_recorded' => $attendanceStats['total_not_recorded'],
                                'total_class_sessions' => $attendanceStats['total_sessions'],
                                'meets_attendance_requirement' => $attendanceStats['meets_requirement'],
                                'updated_at' => now(),
                            ];

                            // Prepare assessment scores
                            if ($record->courseOffering) {
                                $this->prepareAssessmentScoresData(
                                    $record->student_id,
                                    $record->courseOffering,
                                    $attendanceStats,
                                    $assessmentScoresData
                                );
                            }

                            $stats['updated']++;
                        } catch (\Exception $e) {
                            $stats['errors']++;
                            Log::error('Failed to update academic record', [
                                'academic_record_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Perform bulk updates using case-when
                    if (! empty($updates)) {
                        $this->bulkUpdateAcademicRecords($updates);
                    }

                    // Upsert assessment scores
                    if (! empty($assessmentScoresData)) {
                        DB::table('assessment_component_detail_scores')->upsert(
                            $assessmentScoresData,
                            ['assessment_component_detail_id', 'student_id', 'course_offering_id'],
                            ['points_earned', 'percentage_score', 'instructor_feedback', 'graded_at', 'updated_at']
                        );
                    }
                });

                $processed += $records->count();
                if ($progressCallback) {
                    $progressCallback($processed, $totalRecords, "Updated {$processed} records");
                }
            });

        if ($progressCallback) {
            $progressCallback($totalRecords, $totalRecords, 'Completed');
        }

        return $stats;
    }

    /**
     * Bulk update academic records using SQL CASE WHEN
     */
    private function bulkUpdateAcademicRecords(array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        $ids = collect($updates)->pluck('id')->toArray();

        $cases = [
            'attendance_percentage' => 'CASE id ',
            'total_absences' => 'CASE id ',
            'total_present' => 'CASE id ',
            'total_late' => 'CASE id ',
            'total_not_recorded' => 'CASE id ',
            'total_class_sessions' => 'CASE id ',
            'meets_attendance_requirement' => 'CASE id ',
        ];

        foreach ($updates as $update) {
            $cases['attendance_percentage'] .= "WHEN {$update['id']} THEN {$update['attendance_percentage']} ";
            $cases['total_absences'] .= "WHEN {$update['id']} THEN {$update['total_absences']} ";
            $cases['total_present'] .= "WHEN {$update['id']} THEN {$update['total_present']} ";
            $cases['total_late'] .= "WHEN {$update['id']} THEN {$update['total_late']} ";
            $cases['total_not_recorded'] .= "WHEN {$update['id']} THEN {$update['total_not_recorded']} ";
            $cases['total_class_sessions'] .= "WHEN {$update['id']} THEN {$update['total_class_sessions']} ";
            $cases['meets_attendance_requirement'] .= "WHEN {$update['id']} THEN " . ($update['meets_attendance_requirement'] ? '1' : '0') . ' ';
        }

        foreach ($cases as $key => $case) {
            $cases[$key] .= 'END';
        }

        DB::table('academic_records')
            ->whereIn('id', $ids)
            ->update([
                'attendance_percentage' => DB::raw($cases['attendance_percentage']),
                'total_absences' => DB::raw($cases['total_absences']),
                'total_present' => DB::raw($cases['total_present']),
                'total_late' => DB::raw($cases['total_late']),
                'total_not_recorded' => DB::raw($cases['total_not_recorded']),
                'total_class_sessions' => DB::raw($cases['total_class_sessions']),
                'meets_attendance_requirement' => DB::raw($cases['meets_attendance_requirement']),
                'updated_at' => now(),
            ]);
    }
}
