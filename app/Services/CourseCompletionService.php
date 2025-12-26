<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Notification;
use App\Notifications\CourseCompletedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseCompletionService
{
    public function __construct(
        protected EgcLevelProgressionService $egcService,
        protected CourseSurveyService $courseSurveyService
    ) {}

    /**
     * Finalize a course offering when marked as completed
     */
    public function finalizeCourse(CourseOffering $courseOffering): array
    {
        // 1. Validate prerequisites
        $this->validateAcademicRecordsExist($courseOffering);

        // 1.5 Aggregate manual grades if not Canvas synced
        if (! $courseOffering->is_canvas_synced) {
            $this->aggregateManualGrades($courseOffering);
        }

        $this->validateGradesExist($courseOffering);

        // 2. Finalize academic records
        $this->finalizeAcademicRecords($courseOffering);

        // 3. Update course registrations
        $this->updateCourseRegistrations($courseOffering);

        // 4. Process EGC level progression if applicable
        $egcResult = $this->egcService->processEgcProgression($courseOffering);

        // 5. Automatically attach survey to completed course
        $this->courseSurveyService->attachSurveyToCompletedCourse($courseOffering);

        // 6. Send notifications to students for non-EGC courses
        // (EGC courses send notifications in EgcLevelProgressionService)
        $nonEgcResult = null;
        if (! $egcResult['processed']) {
            $nonEgcResult = $this->notifyStudentsNonEgcCompletion($courseOffering);
        }

        Log::info('Course Finalized', [
            'course_offering_id' => $courseOffering->id,
            'course_code' => $courseOffering->course_code,
            'semester' => $courseOffering->semester?->code,
            'total_students' => $courseOffering->current_enrollment,
            'is_egc_course' => $egcResult['processed'],
        ]);

        return [
            'success' => true,
            'course_code' => $courseOffering->course_code,
            'course_title' => $courseOffering->course_title,
            'section_code' => $courseOffering->section_code,
            'total_students' => $courseOffering->current_enrollment,
            'egc_progression' => $egcResult,
            'non_egc_result' => $nonEgcResult,
        ];
    }

    /**
     * Finalize all academic records for the course
     */
    private function finalizeAcademicRecords(CourseOffering $courseOffering): void
    {
        // Load unit to check type for passing threshold
        $courseOffering->load('unit');
        $isEgcCourse = $courseOffering->unit->unit_type === 'egc';

        // Passing threshold:
        // EGC courses: >= 70%
        // Other courses: >= 60%
        $passingThreshold = $isEgcCourse ? 70 : 60;

        // Get all registered student IDs to filter academic records
        $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->toArray();

        // Update each record individually (only for registered students)
        $records = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $registeredStudentIds)
            ->get();

        $attendanceFailedCount = 0;
        $gradeFailedCount = 0;
        $passedCount = 0;

        foreach ($records as $record) {
            // STEP 1: CHECK ATTENDANCE REQUIREMENT FIRST (PRIORITY)
            // Student must attend >= 80% of classes (cannot be absent > 20%)
            $meetsAttendanceRequirement = $record->meets_attendance_requirement ?? true;

            // If student failed attendance requirement, automatic FAIL regardless of grade
            if (! $meetsAttendanceRequirement) {
                $attendancePercentage = (float) ($record->attendance_percentage ?? 0);
                $attendanceNote = "FAILED: Attendance requirement not met ({$attendancePercentage}% attendance, required >= 80%)";

                // Ensure we don't duplicate the note
                $newNotes = $record->administrative_notes;
                if (! $newNotes || ! str_contains($newNotes, "FAILED: Attendance requirement not met")) {
                    $newNotes = ($newNotes ? $newNotes . "\n" : '') . $attendanceNote;
                }

                $record->update([
                    'grade_status' => 'final',
                    'grade_finalized_date' => now(),
                    'final_letter_grade' => 'F', // Override letter grade to F for attendance failure
                    'grade_points' => 0.0, // F grade for attendance failure
                    'completion_status' => 'failed',
                    'is_passed' => false,
                    'credit_hours_earned' => 0,
                    'affects_graduation_requirement' => true,
                    'satisfies_prerequisite' => false,
                    'administrative_notes' => $newNotes,
                ]);

                $attendanceFailedCount++;
                Log::warning('Student failed due to attendance', [
                    'student_id' => $record->student_id,
                    'course_offering_id' => $courseOffering->id,
                    'attendance_percentage' => $attendancePercentage,
                ]);

                continue;
            }

            // STEP 2: CHECK GRADE (only if attendance requirement is met)
            // Cast to float to ensure type safety
            $finalPercentage = (float) ($record->final_percentage ?? 0);

            // Determine if passing based on final percentage and course type
            $isPassing = $finalPercentage >= $passingThreshold;

            // Calculate grade points from final percentage
            $gradePoints = AcademicRecord::calculateGradePoints($finalPercentage);
            $finalLetterGrade = AcademicRecord::calculateLetterGrade($finalPercentage);

            if ($isPassing) {
                $passedCount++;
            } else {
                $gradeFailedCount++;
            }

            // If student previously failed attendance but now meets it (after a re-run),
            // we should remove the failure note to avoid confusion.
            $cleanNotes = $record->administrative_notes;
            if ($cleanNotes && str_contains($cleanNotes, "FAILED: Attendance requirement not met")) {
                $cleanNotes = preg_replace('/^FAILED: Attendance requirement not met.*$/m', '', $cleanNotes);
                $cleanNotes = trim($cleanNotes);
            }

            $record->update([
                'grade_status' => 'final',
                'grade_finalized_date' => now(),
                'final_letter_grade' => $finalLetterGrade,
                'grade_points' => $gradePoints,
                'completion_status' => $isPassing ? 'completed' : 'failed',
                'is_passed' => $isPassing,
                'credit_hours_earned' => $isPassing ? $record->credit_hours : 0,
                'affects_graduation_requirement' => true,
                'satisfies_prerequisite' => $isPassing,
                'administrative_notes' => $cleanNotes ?: null,
            ]);
        }

        Log::info('Academic records finalized', [
            'course_offering_id' => $courseOffering->id,
            'records_count' => $records->count(),
            'course_type' => $courseOffering->unit->unit_type,
            'passing_threshold' => $passingThreshold,
            'passed' => $passedCount,
            'failed_attendance' => $attendanceFailedCount,
            'failed_grade' => $gradeFailedCount,
            'total_failed' => $attendanceFailedCount + $gradeFailedCount,
        ]);
    }

    /**
     * Aggregate and calculate scores for manually graded course offerings.
     * This sums up all assessment component scores to update AcademicRecords.
     */
    public function aggregateManualGrades(CourseOffering $courseOffering): void
    {
        $syllabus = $courseOffering->syllabusTemplate;
        if (! $syllabus) {
            return;
        }

        // Get all assessment components with their details
        $components = AssessmentComponent::where('syllabus_template_id', $syllabus->id)
            ->with('details')
            ->get();

        // Get all registered students
        $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->toArray();

        // Get all entered scores for these students in this course offering
        $allScores = AssessmentComponentDetailScore::where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $registeredStudentIds)
            ->where('score_excluded', false)
            ->whereIn('score_status', ['final', 'provisional', 'draft'])
            ->get()
            ->groupBy('student_id');

        // Identify which details have been graded for at least one student in this course
        $gradedDetailIds = $allScores->flatten()->pluck('assessment_component_detail_id')->unique()->toArray();

        foreach ($registeredStudentIds as $studentId) {
            $studentScores = $allScores->get($studentId) ?? collect();

            $totalWeightedScore = 0;
            $totalWeight = 0;

            foreach ($components as $component) {
                $componentWeightedScore = 0;
                $componentWeight = 0;
                $hasComponentScores = false;

                foreach ($component->details as $detail) {
                    $score = $studentScores->where('assessment_component_detail_id', $detail->id)->first();
                    
                    if ($score && $score->percentage_score !== null) {
                        $componentWeightedScore += ($score->percentage_score * $detail->weight);
                        $componentWeight += $detail->weight;
                        $hasComponentScores = true;
                    } elseif (in_array($detail->id, $gradedDetailIds)) {
                        // Student lacks a score, but the detail has been graded for at least one other student.
                        // We treat this as a 0 score for the student.
                        $componentWeightedScore += (0 * $detail->weight);
                        $componentWeight += $detail->weight;
                        $hasComponentScores = true;
                    }
                }

                if ($hasComponentScores && $componentWeight > 0) {
                    // Calculate component percentage (average of its details)
                    $componentAverage = $componentWeightedScore / $componentWeight;
                    // Apply component weight to overall score
                    $totalWeightedScore += ($componentAverage * $component->weight);
                    $totalWeight += $component->weight;
                }
            }

            // Calculate final percentage (assuming syllabus total weight is 100 or using weighted average)
            $finalPercentage = $totalWeight > 0 ? round($totalWeightedScore / $totalWeight, 2) : 0;

            // Update or create academic record with aggregated results
            AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->where('student_id', $studentId)
                ->update([
                    'final_percentage' => $finalPercentage,
                    'final_letter_grade' => AcademicRecord::calculateLetterGrade($finalPercentage),
                ]);
        }

        Log::info('Aggregated manual grades for course offering', [
            'course_offering_id' => $courseOffering->id,
            'students_count' => count($registeredStudentIds),
        ]);
    }

    /**
     * Update course registrations with final grades and completion status
     */
    private function updateCourseRegistrations(CourseOffering $courseOffering): void
    {
        $records = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->get();

        foreach ($records as $record) {
            CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->where('student_id', $record->student_id)
                ->update([
                    'registration_status' => 'completed',
                    'final_grade' => $record->final_letter_grade,
                    'grade_points' => $record->grade_points,
                    'completion_date' => now(),
                ]);
        }

        Log::info('Course registrations updated', [
            'course_offering_id' => $courseOffering->id,
            'registrations_count' => $records->count(),
        ]);
    }

    /**
     * Validate that all registered students have academic records
     */
    private function validateAcademicRecordsExist(CourseOffering $courseOffering): void
    {
        // Get all registered student IDs
        $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->toArray();

        // Get student IDs that have academic records
        $recordStudentIds = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->pluck('student_id')
            ->toArray();

        // Find students who are registered but don't have academic records
        $missingRecordStudents = array_diff($registeredStudentIds, $recordStudentIds);

        if (count($missingRecordStudents) > 0) {
            $studentDetails = \App\Models\Student::whereIn('id', array_slice($missingRecordStudents, 0, 5))
                ->pluck('student_id')
                ->implode(', ');

            throw new \Exception(
                "Cannot complete course: " . count($missingRecordStudents) . " registered student(s) missing academic records. " .
                    "Students: {$studentDetails}" .
                    (count($missingRecordStudents) > 5 ? ' and others...' : '') .
                    " Please ensure all registered students have academic records before marking course as completed."
            );
        }

        // Note: It's OK if there are academic records for non-registered students (orphaned records)
        // These will be processed but won't affect the course completion
        $orphanedRecords = array_diff($recordStudentIds, $registeredStudentIds);
        if (count($orphanedRecords) > 0) {
            Log::warning('Orphaned academic records found', [
                'course_offering_id' => $courseOffering->id,
                'orphaned_count' => count($orphanedRecords),
                'orphaned_student_ids' => array_slice($orphanedRecords, 0, 5),
            ]);
        }
    }

    /**
     * Validate that all academic records (for registered students) have final grades
     */
    private function validateGradesExist(CourseOffering $courseOffering): void
    {
        // Get all registered student IDs
        $registeredStudentIds = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->pluck('student_id')
            ->toArray();

        // Check only academic records for registered students
        $missingGrades = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereIn('student_id', $registeredStudentIds)
            ->whereNull('final_letter_grade')
            ->count();

        if ($missingGrades > 0) {
            $studentsWithoutGrades = AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->whereIn('student_id', $registeredStudentIds)
                ->whereNull('final_letter_grade')
                ->with('student')
                ->get()
                ->pluck('student.student_id')
                ->take(5)
                ->implode(', ');

            throw new \Exception(
                "Cannot complete course: {$missingGrades} student(s) are missing final grades. " .
                    "Students: {$studentsWithoutGrades}" .
                    ($missingGrades > 5 ? ' and others...' : '')
            );
        }
    }

    /**
     * Get summary of what will happen when course is completed
     */
    public function getCompletionPreview(CourseOffering $courseOffering): array
    {
        $registeredCount = CourseRegistration::where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->count();

        $recordsCount = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->count();

        $missingGrades = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNull('final_letter_grade')
            ->count();

        $passingCount = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNotNull('final_letter_grade')
            ->where('grade_points', '>', 0)
            ->count();

        $failingCount = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNotNull('final_letter_grade')
            ->where('grade_points', '=', 0)
            ->count();

        $isEgcCourse = $courseOffering->unit->unit_type === 'egc';
        $egcStudentsCount = 0;

        if ($isEgcCourse) {
            $egcStudentsCount = AcademicRecord::where('course_offering_id', $courseOffering->id)
                ->whereHas('student', function ($q) {
                    $q->where('status', 'intake_pre_uni_gc');
                })
                ->count();
        }

        return [
            'can_complete' => $registeredCount === $recordsCount && $missingGrades === 0,
            'registered_students' => $registeredCount,
            'academic_records' => $recordsCount,
            'missing_records' => $registeredCount - $recordsCount,
            'missing_grades' => $missingGrades,
            'passing_students' => $passingCount,
            'failing_students' => $failingCount,
            'is_egc_course' => $isEgcCourse,
            'egc_students_count' => $egcStudentsCount,
            'warnings' => $this->getCompletionWarnings($courseOffering, $registeredCount, $recordsCount, $missingGrades),
        ];
    }

    /**
     * Get warnings for course completion
     */
    private function getCompletionWarnings(
        CourseOffering $courseOffering,
        int $registeredCount,
        int $recordsCount,
        int $missingGrades
    ): array {
        $warnings = [];

        if ($registeredCount !== $recordsCount) {
            $warnings[] = "Missing academic records for " . ($registeredCount - $recordsCount) . " student(s)";
        }

        if ($missingGrades > 0) {
            $warnings[] = "{$missingGrades} student(s) missing final grades";
        }

        if ($courseOffering->unit->unit_type === 'egc') {
            $warnings[] = "This is an EGC course - level progression will be processed";
        }

        return $warnings;
    }

    /**
     * Send notifications to students for non-EGC course completion
     * Returns array with pass/fail statistics
     */
    private function notifyStudentsNonEgcCompletion(CourseOffering $courseOffering): array
    {
        $courseOffering->load('unit');

        $records = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNotNull('final_letter_grade')
            ->with('student')
            ->get();

        $notifiedCount = 0;
        $passedCount = 0;
        $failedCount = 0;

        foreach ($records as $record) {
            $student = $record->student;

            if (! $student) {
                continue;
            }

            // Use the finalized pass/fail status from the record
            $isPassing = (bool) ($record->is_passed ?? false);

            if ($isPassing) {
                $passedCount++;
            } else {
                $failedCount++;
            }

            // Create notification using custom method (compatible with custom Notification model)
            $this->createNotification(
                $student,
                new CourseCompletedNotification(
                    courseCode: $courseOffering->unit->code,
                    courseName: $courseOffering->unit->name,
                    grade: $record->final_letter_grade,
                    finalPercentage: (float) ($record->final_percentage ?? 0),
                    creditPoints: (float) ($record->unit->credit_points ?? 0),
                    passed: $isPassing,
                    message: $isPassing
                        ? 'Great job! The credits have been added to your academic record.'
                        : 'Please contact your academic advisor to discuss your options.'
                )
            );

            $notifiedCount++;
        }

        Log::info('Non-EGC Course Completion Notifications Sent', [
            'course_offering_id' => $courseOffering->id,
            'unit_code' => $courseOffering->unit->code,
            'unit_name' => $courseOffering->unit->name,
            'total_notifications' => $notifiedCount,
            'passed' => $passedCount,
            'failed' => $failedCount,
        ]);

        return [
            'total_notified' => $notifiedCount,
            'passed' => $passedCount,
            'failed' => $failedCount,
        ];
    }

    /**
     * Create notification compatible with custom Notification model
     * (Same pattern as EgcLevelProgressionService)
     */
    private function createNotification($notifiable, $notification): void
    {
        $data = $notification->toDatabase($notifiable);

        Notification::create([
            'type' => get_class($notification),
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'],
            'channels' => $data['channels'],
            'is_important' => $data['is_important'] ?? false,
        ]);
    }
}
