<?php

declare(strict_types=1);

namespace App\Services\Canvas;

use App\Exceptions\CanvasConnectionException;
use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CanvasCourseMapping;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumUnit;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CanvasGradeSyncService
{
    public function __construct(
        private CanvasApiService $apiService
    ) {}

    /**
     * Sync grades for all (or a selected subset of) students in a course offering.
     * Only syncs students that match between local and Canvas (by SIS User ID or SIS Login ID).
     *
     * @param  array<int>|null  $studentIds  Restrict the write to these local student IDs; null syncs the whole roster.
     */
    public function syncCourseGrades(CanvasCourseMapping $mapping, ?array $studentIds = null): array
    {
        $originalLimit = ini_get('max_execution_time');
        set_time_limit(300); // 5 minutes for bulk grade sync

        try {
            return $this->performSync($mapping, $studentIds);
        } finally {
            set_time_limit((int) $originalLimit);
        }
    }

    /**
     * Dry-run preview of what syncCourseGrades() would change for the selected students.
     * Never writes to the database and never fires model events.
     *
     * @param  array<int>  $studentIds
     */
    public function previewCourseGrades(CanvasCourseMapping $mapping, array $studentIds): array
    {
        $originalLimit = ini_get('max_execution_time');
        set_time_limit(300);

        try {
            return $this->performPreview($mapping, $studentIds);
        } finally {
            set_time_limit((int) $originalLimit);
        }
    }

    /**
     * @param  array<int>|null  $studentIds
     * @return array{success: bool, students_synced: int, students_skipped: int, total_students: int, students_processed: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>}
     */
    private function performSync(CanvasCourseMapping $mapping, ?array $studentIds): array
    {
        return DB::transaction(function () use ($mapping, $studentIds) {
            $context = $this->buildSyncContext($mapping, $studentIds);
            if (isset($context['early_return'])) {
                return $context['early_return'];
            }

            [
                'course_offering' => $courseOffering,
                'enrollments' => $enrollments,
                'canvas_student_map' => $canvasStudentMap,
                'canvas_components' => $canvasComponents,
                'submission_map' => $submissionMap,
            ] = $context;

            $synced = 0;
            $skipped = 0;
            $errors = [];
            $studentsProcessed = [];

            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student;
                $studentId = strtoupper(trim((string) $student->student_id));

                $canvasStudent = $canvasStudentMap[$studentId] ?? null;
                if (! $canvasStudent) {
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

                    unset($submissionMap[$canvasUserId]);
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

            unset($submissionMap, $canvasStudentMap);
            gc_collect_cycles();

            Log::info('Grade sync completed', [
                'course_offering_id' => $courseOffering->id,
                'student_ids_filter' => $studentIds,
                'synced' => $synced,
                'skipped' => $skipped,
                'errors_count' => count($errors),
            ]);

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
     * @param  array<int>  $studentIds
     * @return array{success: bool, summary: array{total_changes: int, students_changed: int, students_unchanged: int, students_unmatched: int}, changes: array<int, array<string, mixed>>, course_totals: array<int, array<string, mixed>>, unmatched: array<int, array{student_id: int, student_code: string, student_name: string, reason: string}>}
     */
    private function performPreview(CanvasCourseMapping $mapping, array $studentIds): array
    {
        $context = $this->buildSyncContext($mapping, $studentIds);
        if (isset($context['early_return'])) {
            return $this->emptyPreviewResult($context['early_return']);
        }

        [
            'course_offering' => $courseOffering,
            'enrollments' => $enrollments,
            'canvas_student_map' => $canvasStudentMap,
            'canvas_components' => $canvasComponents,
            'submission_map' => $submissionMap,
        ] = $context;

        $changes = [];
        $courseTotals = [];
        $unmatched = [];
        $studentsWithChanges = [];
        $studentsUnchanged = 0;

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $studentCode = strtoupper(trim((string) $student->student_id));

            $canvasStudent = $canvasStudentMap[$studentCode] ?? null;
            if (! $canvasStudent) {
                $unmatched[] = [
                    'student_id' => $student->id,
                    'student_code' => $studentCode,
                    'student_name' => $student->full_name ?? $student->id,
                    'reason' => 'Not found in Canvas course (SIS ID/login mismatch)',
                ];

                continue;
            }

            $canvasUserId = (string) $canvasStudent['id'];
            $studentDiff = $this->diffStudentGradesFromBulk($student, $canvasUserId, $submissionMap, $mapping, $courseOffering, $canvasComponents);
            $courseTotal = $studentDiff['course_total'];
            $totalChanged = $courseTotal !== null && $courseTotal['changed'];

            if (! empty($studentDiff['cell_changes'])) {
                array_push($changes, ...$studentDiff['cell_changes']);
                $studentsWithChanges[$student->id] = true;
            }

            // Always surfaced when Canvas has a grade for this student (even
            // unchanged) so staff can confirm what apply would pull in —
            // `changed` drives the count/highlight, not inclusion.
            if ($courseTotal !== null) {
                $courseTotals[] = $courseTotal;
            }

            if ($totalChanged) {
                $studentsWithChanges[$student->id] = true;
            }

            if (empty($studentDiff['cell_changes']) && ! $totalChanged) {
                $studentsUnchanged++;
            }
        }

        $changedTotalsCount = count(array_filter($courseTotals, fn (array $total) => $total['changed']));

        return [
            'success' => true,
            'summary' => [
                'total_changes' => count($changes) + $changedTotalsCount,
                'students_changed' => count($studentsWithChanges),
                'students_unchanged' => $studentsUnchanged,
                'students_unmatched' => count($unmatched),
            ],
            'changes' => $changes,
            'course_totals' => $courseTotals,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * @param  array<string, mixed>  $earlyReturn
     * @return array<string, mixed>
     */
    private function emptyPreviewResult(array $earlyReturn): array
    {
        return array_merge($earlyReturn, [
            'summary' => ['total_changes' => 0, 'students_changed' => 0, 'students_unchanged' => 0, 'students_unmatched' => 0],
            'changes' => [],
            'course_totals' => [],
            'unmatched' => [],
        ]);
    }

    /**
     * Shared setup for sync and preview: resolves the course offering, the
     * (optionally student-filtered) roster, the Canvas student/submission
     * lookups, and the Canvas-synced assessment components. Read-only.
     *
     * @param  array<int>|null  $studentIds
     * @return array{early_return: array<string, mixed>}|array{course_offering: CourseOffering, enrollments: Collection<int, CourseRegistration>, canvas_student_map: array<string, array<string, mixed>>, canvas_components: Collection<int, AssessmentComponent>, submission_map: array<string, array<string, array<string, mixed>>>}
     */
    private function buildSyncContext(CanvasCourseMapping $mapping, ?array $studentIds): array
    {
        $courseOffering = $mapping->courseOffering()->with('unit')->first();

        if (! $courseOffering || ! $courseOffering->syllabusTemplate) {
            throw new \Exception('Course offering or syllabus not found');
        }

        $enrollments = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->when($studentIds !== null, fn ($q) => $q->whereIn('student_id', $studentIds))
            ->with('student')
            ->get();

        if ($enrollments->isEmpty()) {
            return ['early_return' => [
                'success' => true,
                'message' => 'No enrolled students found',
                'students_synced' => 0,
            ]];
        }

        $canvasStudents = $this->apiService->getCourseStudents(
            $mapping->canvasIntegration,
            $mapping->canvas_course_id
        );

        $canvasStudentMap = [];
        foreach ($canvasStudents as $canvasStudent) {
            $sisUserId = $canvasStudent['sis_user_id'] ?? null;
            $sisLoginId = $canvasStudent['login_id'] ?? null;

            if ($sisUserId) {
                $canvasStudentMap[strtoupper(trim((string) $sisUserId))] = $canvasStudent;
            }
            if ($sisLoginId && ! isset($canvasStudentMap[$sisLoginId])) {
                $canvasStudentMap[strtoupper(trim((string) $sisLoginId))] = $canvasStudent;
            }
        }

        $syllabusTemplate = $courseOffering->syllabusTemplate;
        $canvasComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->id)
            ->where('is_canvas_synced', true)
            ->get();

        if ($canvasComponents->isEmpty()) {
            throw new \Exception('No Canvas components found. Please sync assignments first.');
        }

        $assignmentIds = [];
        foreach ($canvasComponents as $component) {
            $details = AssessmentComponentDetail::where('assessment_component_id', $component->id)
                ->whereNotNull('canvas_assignment_id')
                ->pluck('canvas_assignment_id')
                ->toArray();
            $assignmentIds = array_merge($assignmentIds, $details);
        }

        $allSubmissions = $this->apiService->getAllStudentSubmissions(
            $mapping->canvasIntegration,
            $mapping->canvas_course_id,
            $assignmentIds
        );

        $submissionMap = [];
        foreach ($allSubmissions as $submission) {
            $userId = (string) $submission['user_id'];
            $assignmentId = (string) $submission['assignment_id'];
            $submissionMap[$userId][$assignmentId] = $submission;
        }

        return [
            'course_offering' => $courseOffering,
            'enrollments' => $enrollments,
            'canvas_student_map' => $canvasStudentMap,
            'canvas_components' => $canvasComponents,
            'submission_map' => $submissionMap,
        ];
    }

    /**
     * Sync grades for a specific student from bulk submission data
     *
     * @param  Student  $student  Local student record
     * @param  string  $canvasUserId  Canvas user ID
     * @param  array<string, array<string, array<string, mixed>>>  $submissionMap  Bulk submissions indexed by [user_id][assignment_id]
     * @param  Collection<int, AssessmentComponent>  $canvasComponents
     * @return array{success: bool, synced: int, created: int, updated: int}
     */
    private function syncStudentGradesFromBulk(Student $student, string $canvasUserId, array $submissionMap, CanvasCourseMapping $mapping, Collection $canvasComponents): array
    {
        $courseOffering = $mapping->courseOffering;

        $synced = 0;
        $created = 0;
        $updated = 0;

        $studentSubmissions = $submissionMap[$canvasUserId] ?? [];

        foreach ($canvasComponents as $component) {
            $assignmentDetails = AssessmentComponentDetail::where('assessment_component_id', $component->id)
                ->whereNotNull('canvas_assignment_id')
                ->get();

            foreach ($assignmentDetails as $detail) {
                $submission = $studentSubmissions[$detail->canvas_assignment_id] ?? null;

                if (! $submission) {
                    continue;
                }

                $data = $this->buildScoreData($detail, $submission, $student->id, $courseOffering->id);

                // Match the table's unique key (assessment_component_detail_id,
                // student_id, course_offering_id, submission_attempt) so a
                // score in another offering (e.g. a retake) can't be picked up.
                $existing = AssessmentComponentDetailScore::where('assessment_component_detail_id', $detail->id)
                    ->where('student_id', $student->id)
                    ->where('course_offering_id', $courseOffering->id)
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

        $this->syncCanvasCourseTotal($student, $canvasUserId, $mapping, $courseOffering);

        return [
            'success' => true,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Read-only counterpart to syncStudentGradesFromBulk(): computes the same
     * score data Canvas would produce but only reports the cells that would
     * change, without writing anything or fetching from Canvas twice.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $submissionMap  Bulk submissions indexed by [user_id][assignment_id]
     * @param  Collection<int, AssessmentComponent>  $canvasComponents
     * @return array{
     *   cell_changes: array<int, array{student_id: int, student_code: string, student_name: string, component_id: int, component_name: string, detail_id: int, detail_name: string, old_points: float|null, new_points: float, old_percentage: float|null, new_percentage: float, is_disputed: bool}>,
     *   course_total: array{student_id: int, student_code: string, student_name: string, old_percentage: float|null, new_percentage: float, changed: bool}|null
     * }
     */
    private function diffStudentGradesFromBulk(Student $student, string $canvasUserId, array $submissionMap, CanvasCourseMapping $mapping, CourseOffering $courseOffering, Collection $canvasComponents): array
    {
        $studentSubmissions = $submissionMap[$canvasUserId] ?? [];
        $cellChanges = [];

        foreach ($canvasComponents as $component) {
            $assignmentDetails = AssessmentComponentDetail::where('assessment_component_id', $component->id)
                ->whereNotNull('canvas_assignment_id')
                ->get();

            foreach ($assignmentDetails as $detail) {
                $submission = $studentSubmissions[$detail->canvas_assignment_id] ?? null;
                if (! $submission) {
                    continue;
                }

                $newData = $this->buildScoreData($detail, $submission, $student->id, $courseOffering->id);

                $existing = AssessmentComponentDetailScore::where('assessment_component_detail_id', $detail->id)
                    ->where('student_id', $student->id)
                    ->where('course_offering_id', $courseOffering->id)
                    ->first();

                $oldPoints = $existing?->points_earned;
                $newPoints = $newData['points_earned'];
                $oldPercentage = $existing !== null ? (float) $existing->percentage_score : null;
                $newPercentage = $newData['percentage_score'];

                $changed = $existing === null
                    || abs((float) $oldPoints - (float) $newPoints) > 0.001
                    || abs((float) $oldPercentage - (float) $newPercentage) > 0.01;

                if (! $changed) {
                    continue;
                }

                $cellChanges[] = [
                    'student_id' => $student->id,
                    'student_code' => strtoupper(trim((string) $student->student_id)),
                    'student_name' => $student->full_name ?? $student->id,
                    'component_id' => $component->id,
                    'component_name' => $component->name,
                    'detail_id' => $detail->id,
                    'detail_name' => $detail->name,
                    'old_points' => $oldPoints !== null ? (float) $oldPoints : null,
                    'new_points' => (float) $newPoints,
                    'old_percentage' => $oldPercentage,
                    'new_percentage' => (float) $newPercentage,
                    'is_disputed' => $existing?->score_status === 'disputed',
                ];
            }
        }

        $courseTotal = $this->diffCanvasCourseTotal($student, $canvasUserId, $mapping, $courseOffering);

        return ['cell_changes' => $cellChanges, 'course_total' => $courseTotal];
    }

    /**
     * Build the AssessmentComponentDetailScore attributes Canvas submission
     * data maps to. Shared by the write path and the preview diff so the two
     * can never compute a different "new" value.
     */
    private function buildScoreData(AssessmentComponentDetail $detail, array $submission, int $studentId, int $courseOfferingId): array
    {
        $score = $submission['score'] ?? null;
        $percentageScore = null;

        if ($score !== null && $detail->max_points > 0) {
            $percentageScore = ($score / $detail->max_points) * 100;
        }

        $scoreStatus = 'draft';
        if (isset($submission['grade']) && $submission['grade'] !== null) {
            $scoreStatus = 'final';
        }

        return [
            'assessment_component_detail_id' => $detail->id,
            'student_id' => $studentId,
            'course_offering_id' => $courseOfferingId,
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
    }

    /**
     * Fetch the student's Canvas course total and, unless a custom grading
     * engine is authoritative, write it to the AcademicRecord.
     */
    private function syncCanvasCourseTotal(Student $student, string $canvasUserId, CanvasCourseMapping $mapping, CourseOffering $courseOffering): void
    {
        try {
            $canvasTotal = $this->fetchCanvasCourseTotal($canvasUserId, $mapping);
            if ($canvasTotal === null) {
                return;
            }

            $existingRecord = AcademicRecord::where('student_id', $student->id)
                ->where('course_offering_id', $courseOffering->id)
                ->first();

            if ($existingRecord) {
                if ($this->hasCustomGradingEngine($courseOffering)) {
                    Log::info('Skipped Canvas total overwrite: custom grading engine is authoritative', [
                        'student_id' => $student->id,
                        'course_offering_id' => $courseOffering->id,
                    ]);

                    return;
                }

                $finalPercentage = round($canvasTotal, 2);
                $existingRecord->update([
                    'final_percentage' => $finalPercentage,
                    'final_letter_grade' => AcademicRecord::calculateLetterGrade($finalPercentage),
                ]);

                return;
            }

            $this->createAcademicRecordWithCanvasTotal($student, $courseOffering, $canvasTotal);
        } catch (CanvasConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::warning('Failed to sync Canvas total grade', [
                'student_id' => $student->id,
                'canvas_user_id' => $canvasUserId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Read-only counterpart to syncCanvasCourseTotal(): always reports the
     * Canvas total for a matched student (so staff can see what apply would
     * pull in even when it matches the current record), flagged `changed`.
     * Returns null only when Canvas has no grade yet or a custom grading
     * engine makes the Canvas total non-authoritative (the total is never
     * shown, changed or not, since apply would never touch it either).
     *
     * @return array{student_id: int, student_code: string, student_name: string, old_percentage: float|null, new_percentage: float, changed: bool}|null
     */
    private function diffCanvasCourseTotal(Student $student, string $canvasUserId, CanvasCourseMapping $mapping, CourseOffering $courseOffering): ?array
    {
        try {
            $canvasTotal = $this->fetchCanvasCourseTotal($canvasUserId, $mapping);
            if ($canvasTotal === null || $this->hasCustomGradingEngine($courseOffering)) {
                return null;
            }

            $existingRecord = AcademicRecord::where('student_id', $student->id)
                ->where('course_offering_id', $courseOffering->id)
                ->first();

            $newPercentage = round($canvasTotal, 2);
            $oldPercentage = $existingRecord?->final_percentage !== null ? (float) $existingRecord->final_percentage : null;
            $changed = $oldPercentage === null || abs($oldPercentage - $newPercentage) >= 0.01;

            return [
                'student_id' => $student->id,
                'student_code' => strtoupper(trim((string) $student->student_id)),
                'student_name' => $student->full_name ?? $student->id,
                'old_percentage' => $oldPercentage,
                'new_percentage' => $newPercentage,
                'changed' => $changed,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to diff Canvas total grade', [
                'student_id' => $student->id,
                'canvas_user_id' => $canvasUserId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchCanvasCourseTotal(string $canvasUserId, CanvasCourseMapping $mapping): ?float
    {
        $enrollment = $this->apiService->getStudentEnrollment(
            $mapping->canvasIntegration,
            $mapping->canvas_course_id,
            $canvasUserId
        );

        $grades = $enrollment['grades'] ?? null;
        if (! $grades) {
            return null;
        }

        if (isset($grades['current_score']) && $grades['current_score'] !== null) {
            return (float) $grades['current_score'];
        }

        if (isset($grades['unposted_current_score']) && $grades['unposted_current_score'] !== null) {
            return (float) $grades['unposted_current_score'];
        }

        return null;
    }

    /**
     * When the syllabus has a custom grading engine, Canvas total must NOT
     * overwrite the rule-engine result — the local calculator is authoritative.
     */
    private function hasCustomGradingEngine(CourseOffering $courseOffering): bool
    {
        $gradingScheme = $courseOffering->syllabusTemplate?->grading_scheme;

        return $gradingScheme !== null
            && isset($gradingScheme['engine'])
            && $gradingScheme['engine'] !== 'default_weighted_percentage';
    }

    private function createAcademicRecordWithCanvasTotal(Student $student, CourseOffering $courseOffering, float $canvasTotal): void
    {
        $finalPercentage = round($canvasTotal, 2);
        $academicRecordData = [
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'final_percentage' => $finalPercentage,
            'final_letter_grade' => AcademicRecord::calculateLetterGrade($finalPercentage),
            'enrollment_date' => now()->toDateString(),
        ];

        foreach (['semester_id', 'unit_id', 'campus_id'] as $field) {
            if ($courseOffering->$field) {
                $academicRecordData[$field] = $courseOffering->$field;
            }
        }

        $creditHours = $courseOffering->unit->credit_points ?? null;
        if ($creditHours === null) {
            $unit = Unit::find($courseOffering->unit_id);
            $creditHours = $unit->credit_points ?? null;
        }

        if ($creditHours === null || $creditHours < 0) {
            return;
        }

        $academicRecordData['credit_hours'] = $creditHours;
        $academicRecordData['credit_points'] = $creditHours;

        $programId = $student->program_id ?? null;
        if (! $programId && $courseOffering->unit_id) {
            $curriculumUnit = CurriculumUnit::where('unit_id', $courseOffering->unit_id)
                ->with('curriculumVersion')
                ->first();
            $programId = $curriculumUnit->curriculumVersion->program_id ?? null;
        }

        if (! $programId) {
            return;
        }

        $academicRecordData['program_id'] = $programId;

        $required = ['semester_id', 'unit_id', 'campus_id', 'credit_hours', 'enrollment_date'];
        $missing = array_filter($required, fn ($f) => empty($academicRecordData[$f]));

        if (empty($missing)) {
            AcademicRecord::create($academicRecordData);
        }
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

        $canvasComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->id)
            ->where('is_canvas_synced', true)
            ->get();

        if ($canvasComponents->isEmpty()) {
            return [
                'can_sync' => false,
                'error' => 'No Canvas components found. Please sync assignments first.',
            ];
        }

        $assignmentsCount = AssessmentComponentDetail::whereIn('assessment_component_id', $canvasComponents->pluck('id'))
            ->whereNotNull('canvas_assignment_id')
            ->count();

        if ($assignmentsCount === 0) {
            return [
                'can_sync' => false,
                'error' => 'No Canvas assignments found. Please sync assignments first.',
            ];
        }

        $studentsCount = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->count();

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
