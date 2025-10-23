<?php

declare(strict_types=1);

namespace App\Services\Canvas;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CanvasCourseMapping;
use App\Models\CourseRegistration;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CanvasGradeSyncService
{
    public function __construct(
        private CanvasApiService $apiService
    ) {}

    /**
     * Sync grades for all students in a course offering
     * Only syncs students that match between local and Canvas (by SIS User ID or SIS Login ID)
     */
    public function syncCourseGrades(CanvasCourseMapping $mapping): array
    {
        // Increase execution time for bulk operations
        $originalLimit = ini_get('max_execution_time');
        set_time_limit(300); // 5 minutes for bulk grade sync

        try {
            return $this->performSync($mapping);
        } finally {
            // Restore original limit
            set_time_limit((int) $originalLimit);
        }
    }

    private function performSync(CanvasCourseMapping $mapping): array
    {
        return DB::transaction(function () use ($mapping) {
            $courseOffering = $mapping->courseOffering;

            Log::info('========== GRADE SYNC DEBUG START ==========');
            Log::info('Canvas Course Mapping', [
                'mapping_id' => $mapping->id,
                'canvas_course_id' => $mapping->canvas_course_id,
                'canvas_course_name' => $mapping->canvas_course_name,
                'course_offering_id' => $courseOffering?->id,
            ]);

            if (! $courseOffering || ! $courseOffering->syllabusTemplate) {
                throw new \Exception('Course offering or syllabus not found');
            }

            // Get local enrolled students (registered or confirmed)
            $enrollments = CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->whereIn('registration_status', ['registered', 'confirmed'])
                ->with('student')
                ->get();

            if ($enrollments->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No enrolled students found',
                    'students_synced' => 0,
                ];
            }

            // Get Canvas students
            $canvasStudents = $this->apiService->getCourseStudents(
                $mapping->canvasIntegration,
                $mapping->canvas_course_id
            );

            Log::info('Fetched Canvas students for grade sync', [
                'canvas_course_id' => $mapping->canvas_course_id,
                'canvas_students_count' => count($canvasStudents),
                'local_enrollments_count' => $enrollments->count(),
                'sample_canvas_student' => $canvasStudents[0] ?? null,
            ]);

            // Build Canvas student lookup by SIS User ID and SIS Login ID
            $canvasStudentMap = [];
            foreach ($canvasStudents as $canvasStudent) {
                $sisUserId = $canvasStudent['sis_user_id'] ?? null;
                $sisLoginId = $canvasStudent['login_id'] ?? null;

                if ($sisUserId) {
                    $canvasStudentMap[$sisUserId] = $canvasStudent;
                }
                if ($sisLoginId && ! isset($canvasStudentMap[$sisLoginId])) {
                    $canvasStudentMap[$sisLoginId] = $canvasStudent;
                }
            }

            Log::info('Built Canvas student map', [
                'map_size' => count($canvasStudentMap),
                'map_keys_sample' => array_slice(array_keys($canvasStudentMap), 0, 5),
                'first_canvas_student_sample' => $canvasStudents[0] ?? null,
            ]);

            Log::info('Local students sample', [
                'total_enrollments' => $enrollments->count(),
                'first_3_students' => $enrollments->take(3)->map(fn($e) => [
                    'student_id' => $e->student->student_id,
                    'full_name' => $e->student->full_name ?? 'N/A',
                    'id' => $e->student->id,
                ])->toArray(),
            ]);

            // Get all Canvas-synced assignments to build filter list
            $syllabusTemplate = $courseOffering->syllabusTemplate;
            $canvasComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->id)
                ->where('is_canvas_synced', true)
                ->get();

            if ($canvasComponents->isEmpty()) {
                throw new \Exception('No Canvas components found. Please sync assignments first.');
            }

            // Collect all Canvas assignment IDs
            $assignmentIds = [];
            foreach ($canvasComponents as $component) {
                $details = AssessmentComponentDetail::where('assessment_component_id', $component->id)
                    ->whereNotNull('canvas_assignment_id')
                    ->pluck('canvas_assignment_id')
                    ->toArray();
                $assignmentIds = array_merge($assignmentIds, $details);
            }

            Log::info('Canvas components and assignments', [
                'components_count' => $canvasComponents->count(),
                'total_assignment_ids' => count($assignmentIds),
                'assignment_ids' => $assignmentIds,
                'components_details' => $canvasComponents->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->component_name,
                    'canvas_group_id' => $c->canvas_assignment_group_id,
                ])->toArray(),
            ]);

            // Fetch submissions ONLY for our assignments (much faster!)
            Log::info('Starting bulk submissions fetch...', [
                'canvas_course_id' => $mapping->canvas_course_id,
                'expected_students' => $enrollments->count(),
                'assignment_ids_count' => count($assignmentIds),
                'assignment_ids' => $assignmentIds,
            ]);

            $startTime = microtime(true);
            $allSubmissions = $this->apiService->getAllStudentSubmissions(
                $mapping->canvasIntegration,
                $mapping->canvas_course_id,
                $assignmentIds // ← FILTER BY ASSIGNMENT IDs!
            );
            $fetchTime = microtime(true) - $startTime;

            Log::info('Bulk submissions fetched', [
                'total_submissions' => count($allSubmissions),
                'fetch_time_seconds' => round($fetchTime, 2),
            ]);

            // Build submission lookup: [user_id][assignment_id] => submission
            Log::info('Building submission map...');
            $mapStartTime = microtime(true);

            $submissionMap = [];
            foreach ($allSubmissions as $submission) {
                $userId = (string) $submission['user_id'];
                $assignmentId = (string) $submission['assignment_id'];
                $submissionMap[$userId][$assignmentId] = $submission;
            }

            $mapTime = microtime(true) - $mapStartTime;
            Log::info('Submission map built', [
                'unique_students' => count($submissionMap),
                'map_time_seconds' => round($mapTime, 2),
                'submission_map_keys' => array_keys($submissionMap),
                'first_submission_sample' => ! empty($allSubmissions) ? $allSubmissions[0] : null,
            ]);

            $synced = 0;
            $skipped = 0;
            $errors = [];
            $studentsProcessed = [];

            Log::info('Starting student processing...', [
                'total_students_to_process' => $enrollments->count(),
            ]);
            $processStartTime = microtime(true);

            foreach ($enrollments as $index => $enrollment) {
                if ($index % 10 === 0) {
                    $elapsed = microtime(true) - $processStartTime;
                    Log::info('Processing progress', [
                        'processed' => $index,
                        'total' => $enrollments->count(),
                        'elapsed_seconds' => round($elapsed, 2),
                    ]);
                }
                $student = $enrollment->student;
                // Use student_id column (e.g., "AUS15189") to match with Canvas SIS User ID
                $studentId = (string) $student->student_id;

                Log::debug('Processing student', [
                    'student_id' => $student->id,
                    'student_code' => $studentId,
                    'student_name' => $student->full_name ?? 'N/A',
                    'looking_in_map' => array_key_exists($studentId, $canvasStudentMap),
                ]);

                // Try to find Canvas student by matching student_id with SIS User ID or SIS Login ID
                $canvasStudent = $canvasStudentMap[$studentId] ?? null;

                if (! $canvasStudent) {
                    Log::warning('Student not found in Canvas', [
                        'student_id' => $student->id,
                        'student_code' => $studentId,
                        'available_canvas_keys' => array_keys($canvasStudentMap),
                    ]);
                    $skipped++;
                    $studentsProcessed[] = [
                        'student_id' => $student->id,
                        'student_code' => $studentId,
                        'student_name' => $student->full_name ?? $student->id,
                        'status' => 'skipped',
                        'reason' => 'Not found in Canvas course',
                        'grades_synced' => 0,
                    ];

                    continue;
                }

                $canvasUserId = (string) $canvasStudent['id'];

                Log::info('Matched student - syncing grades', [
                    'local_student_id' => $student->id,
                    'student_code' => $studentId,
                    'canvas_user_id' => $canvasUserId,
                    'has_submissions' => isset($submissionMap[$canvasUserId]),
                    'submission_count' => isset($submissionMap[$canvasUserId]) ? count($submissionMap[$canvasUserId]) : 0,
                ]);

                try {
                    $result = $this->syncStudentGradesFromBulk($student, $canvasUserId, $submissionMap, $mapping, $canvasComponents);
                    $synced++;
                    $studentsProcessed[] = [
                        'student_id' => $student->id,
                        'student_code' => $studentId,
                        'student_name' => $student->full_name ?? $student->id,
                        'canvas_user_id' => $canvasUserId,
                        'status' => 'success',
                        'grades_synced' => $result['synced'],
                        'grades_created' => $result['created'],
                        'grades_updated' => $result['updated'],
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->full_name ?? $student->id,
                        'error' => $e->getMessage(),
                    ];
                    $studentsProcessed[] = [
                        'student_id' => $student->id,
                        'student_code' => $studentId,
                        'student_name' => $student->full_name ?? $student->id,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to sync student grades', [
                        'student_id' => $student->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Grade sync completed', [
                'course_offering_id' => $courseOffering->id,
                'synced' => $synced,
                'skipped' => $skipped,
                'errors_count' => count($errors),
            ]);

            Log::info('========== GRADE SYNC DEBUG END ==========');

            return [
                'success' => true,
                'students_synced' => $synced,
                'students_skipped' => $skipped,
                'total_students' => $enrollments->count(),
                'students_processed' => $studentsProcessed,
                'errors' => $errors,
            ];
        });
    }

    /**
     * Sync grades for a specific student from bulk submission data
     *
     * @param  Student  $student  Local student record
     * @param  string  $canvasUserId  Canvas user ID
     * @param  array  $submissionMap  Bulk submissions indexed by [user_id][assignment_id]
     * @param  $canvasComponents  Canvas components
     */
    private function syncStudentGradesFromBulk(Student $student, string $canvasUserId, array $submissionMap, CanvasCourseMapping $mapping, $canvasComponents): array
    {
        $courseOffering = $mapping->courseOffering;

        $synced = 0;
        $created = 0;
        $updated = 0;

        $studentSubmissions = $submissionMap[$canvasUserId] ?? [];

        Log::debug('syncStudentGradesFromBulk called', [
            'student_id' => $student->id,
            'canvas_user_id' => $canvasUserId,
            'submissions_found' => count($studentSubmissions),
            'submission_assignment_ids' => array_keys($studentSubmissions),
        ]);

        foreach ($canvasComponents as $component) {
            // Get all assignment details for this component
            $assignmentDetails = AssessmentComponentDetail::where('assessment_component_id', $component->id)
                ->whereNotNull('canvas_assignment_id')
                ->get();

            foreach ($assignmentDetails as $detail) {
                // Get submission from bulk data
                $submission = $studentSubmissions[$detail->canvas_assignment_id] ?? null;

                if (! $submission) {
                    Log::debug('No submission for assignment', [
                        'student_id' => $student->id,
                        'canvas_assignment_id' => $detail->canvas_assignment_id,
                        'detail_id' => $detail->id,
                    ]);

                    continue;
                }

                Log::debug('Processing submission', [
                    'student_id' => $student->id,
                    'canvas_assignment_id' => $detail->canvas_assignment_id,
                    'submission_data' => $submission,
                ]);

                // Calculate percentage
                $score = $submission['score'] ?? null;
                $percentageScore = null;

                if ($score !== null && $detail->max_points > 0) {
                    $percentageScore = ($score / $detail->max_points) * 100;
                }

                // Determine score status based on grading state
                $scoreStatus = 'draft';
                if (isset($submission['grade']) && $submission['grade'] !== null) {
                    $scoreStatus = 'final';
                }

                $data = [
                    'assessment_component_detail_id' => $detail->id,
                    'student_id' => $student->id,
                    'course_offering_id' => $courseOffering->id,
                    'points_earned' => $score ?? 0,
                    'percentage_score' => $percentageScore ?? 0,
                    'submitted_at' => isset($submission['submitted_at'])
                        ? date('Y-m-d H:i:s', strtotime($submission['submitted_at']))
                        : null,
                    'graded_at' => isset($submission['graded_at'])
                        ? date('Y-m-d H:i:s', strtotime($submission['graded_at']))
                        : null,
                    'status' => $this->mapCanvasStatus($submission['workflow_state'] ?? 'unsubmitted'),
                    'score_status' => $scoreStatus,
                ];

                // Check if score already exists
                $existing = AssessmentComponentDetailScore::where('assessment_component_detail_id', $detail->id)
                    ->where('student_id', $student->id)
                    ->first();

                if ($existing) {
                    Log::debug('Updating existing score', [
                        'score_id' => $existing->id,
                        'old_points' => $existing->points_earned,
                        'new_points' => $data['points_earned'],
                        'student_id' => $student->id,
                        'detail_id' => $detail->id,
                    ]);
                    $existing->update($data);
                    $updated++;
                } else {
                    Log::debug('Creating new score', [
                        'student_id' => $student->id,
                        'detail_id' => $detail->id,
                        'data' => $data,
                    ]);
                    $newScore = AssessmentComponentDetailScore::create($data);
                    Log::debug('Created score', ['score_id' => $newScore->id]);
                    $created++;
                }

                $synced++;
            }
        }

        // Sync Canvas total grade to academic record
        try {
            // Fetch Canvas total grade
            $enrollment = $this->apiService->getStudentEnrollment(
                $mapping->canvasIntegration,
                $mapping->canvas_course_id,
                $canvasUserId
            );

            if ($enrollment && isset($enrollment['grades']['current_score'])) {
                $canvasTotal = (float) $enrollment['grades']['current_score'];

                // Update or create academic record with Canvas total grade
                \App\Models\AcademicRecord::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'course_offering_id' => $courseOffering->id,
                    ],
                    [
                        'final_percentage' => round($canvasTotal, 2),
                    ]
                );

                Log::info('Synced Canvas total grade to academic record', [
                    'student_id' => $student->id,
                    'canvas_total' => $canvasTotal,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sync Canvas total grade', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - assignment scores already synced successfully
        }

        return [
            'success' => true,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Sync grades for a specific student (OLD - using individual API calls)
     * Kept for backward compatibility or fallback
     *
     * @param  Student  $student  Local student record
     * @param  array  $canvasStudent  Canvas student data with 'id' (Canvas user ID)
     */
    public function syncStudentGrades(Student $student, array $canvasStudent, CanvasCourseMapping $mapping): array
    {
        $courseOffering = $mapping->courseOffering;
        $syllabusTemplate = $courseOffering->syllabusTemplate;

        $canvasUserId = (string) $canvasStudent['id'];

        // Get all Canvas-synced components (Assignment Groups)
        $canvasComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->id)
            ->where('is_canvas_synced', true)
            ->get();

        if ($canvasComponents->isEmpty()) {
            throw new \Exception('No Canvas components found. Please sync assignments first.');
        }

        $synced = 0;
        $created = 0;
        $updated = 0;

        foreach ($canvasComponents as $component) {
            // Get all assignment details for this component
            $assignmentDetails = AssessmentComponentDetail::where('assessment_component_id', $component->id)
                ->whereNotNull('canvas_assignment_id')
                ->get();

            foreach ($assignmentDetails as $detail) {
                // Fetch submission from Canvas for this specific student and assignment
                $submission = $this->apiService->getSubmission(
                    $mapping->canvasIntegration,
                    $mapping->canvas_course_id,
                    $detail->canvas_assignment_id,
                    $canvasUserId
                );

                if (! $submission) {
                    Log::debug('No submission found', [
                        'student_id' => $student->id,
                        'canvas_user_id' => $canvasUserId,
                        'assignment_id' => $detail->canvas_assignment_id,
                    ]);

                    continue;
                }

                // Calculate percentage
                $score = $submission['score'] ?? null;
                $percentageScore = null;

                if ($score !== null && $detail->max_points > 0) {
                    $percentageScore = ($score / $detail->max_points) * 100;
                }

                // Determine score status based on grading state
                $scoreStatus = 'draft'; // default
                if (isset($submission['grade']) && $submission['grade'] !== null) {
                    $scoreStatus = 'final';
                } elseif (isset($submission['submitted_at'])) {
                    $scoreStatus = 'draft';
                }

                $data = [
                    'assessment_component_detail_id' => $detail->id,
                    'student_id' => $student->id,
                    'course_offering_id' => $courseOffering->id,
                    'points_earned' => $score ?? 0,
                    'percentage_score' => $percentageScore ?? 0,
                    'submitted_at' => isset($submission['submitted_at'])
                        ? date('Y-m-d H:i:s', strtotime($submission['submitted_at']))
                        : null,
                    'graded_at' => isset($submission['graded_at'])
                        ? date('Y-m-d H:i:s', strtotime($submission['graded_at']))
                        : null,
                    'status' => $this->mapCanvasStatus($submission['workflow_state'] ?? 'unsubmitted'),
                    'score_status' => $scoreStatus,
                ];

                // Check if score already exists
                $existing = AssessmentComponentDetailScore::where('assessment_component_detail_id', $detail->id)
                    ->where('student_id', $student->id)
                    ->first();

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    AssessmentComponentDetailScore::create($data);
                    $created++;
                }

                $synced++;
            }
        }

        Log::info('Student grades synced from Canvas', [
            'student_id' => $student->id,
            'canvas_user_id' => $canvasUserId,
            'course_offering_id' => $courseOffering->id,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
        ]);

        return [
            'success' => true,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Map Canvas workflow state to local status
     */
    private function mapCanvasStatus(string $canvasStatus): string
    {
        return match ($canvasStatus) {
            'submitted', 'pending_review' => 'submitted',
            'graded' => 'graded',
            'unsubmitted' => 'not_submitted',
            default => 'not_submitted',
        };
    }

    /**
     * Get grade sync summary
     */
    public function getGradeSyncSummary(CanvasCourseMapping $mapping): array
    {
        $courseOffering = $mapping->courseOffering;

        if (! $courseOffering || ! $courseOffering->syllabusTemplate) {
            return [
                'can_sync' => false,
                'error' => 'Course offering or syllabus not found',
            ];
        }

        $syllabusTemplate = $courseOffering->syllabusTemplate;

        // Find all Canvas components (Assignment Groups)
        $canvasComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->id)
            ->where('is_canvas_synced', true)
            ->get();

        if ($canvasComponents->isEmpty()) {
            return [
                'can_sync' => false,
                'error' => 'No Canvas components found. Please sync assignments first.',
            ];
        }

        // Count assignments across all Canvas components
        $assignmentsCount = AssessmentComponentDetail::whereIn('assessment_component_id', $canvasComponents->pluck('id'))
            ->whereNotNull('canvas_assignment_id')
            ->count();

        if ($assignmentsCount === 0) {
            return [
                'can_sync' => false,
                'error' => 'No Canvas assignments found. Please sync assignments first.',
            ];
        }

        // Count enrolled students (registered or confirmed)
        $studentsCount = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->count();

        // Count graded scores across all Canvas components
        $gradedScoresCount = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($q) use ($canvasComponents) {
            $q->whereIn('assessment_component_id', $canvasComponents->pluck('id'))
                ->whereNotNull('canvas_assignment_id');
        })
            ->where('course_offering_id', $courseOffering->id)
            ->where('status', 'graded')
            ->count();

        $lastSync = AssessmentComponentDetailScore::whereHas('assessmentComponentDetail', function ($q) use ($canvasComponents) {
            $q->whereIn('assessment_component_id', $canvasComponents->pluck('id'));
        })
            ->where('course_offering_id', $courseOffering->id)
            ->max('updated_at');

        return [
            'can_sync' => true,
            'components_count' => $canvasComponents->count(),
            'assignments_count' => $assignmentsCount,
            'students_count' => $studentsCount,
            'graded_scores_count' => $gradedScoresCount,
            'expected_total_scores' => $assignmentsCount * $studentsCount,
            'completion_percentage' => $assignmentsCount * $studentsCount > 0
                ? round(($gradedScoresCount / ($assignmentsCount * $studentsCount)) * 100, 2)
                : 0,
            'last_synced_at' => $lastSync,
        ];
    }
}
