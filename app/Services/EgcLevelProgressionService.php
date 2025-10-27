<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Notifications\EgcCourseCompletedNotification;
use App\Notifications\EgcProgramCompletedNotification;
use Illuminate\Support\Facades\Log;

class EgcLevelProgressionService
{
    /**
     * Process EGC level progression for completed course
     */
    public function processEgcProgression(CourseOffering $courseOffering): array
    {
        // Check if this is an EGC course
        if ($courseOffering->unit->unit_type !== 'egc') {
            return [
                'processed' => false,
                'reason' => 'Not an EGC course',
            ];
        }

        $unitLevel = $courseOffering->unit->level;

        if ($unitLevel === null) {
            Log::error('EGC unit missing level', [
                'unit_id' => $courseOffering->unit->id,
                'unit_code' => $courseOffering->unit->code,
            ]);

            return [
                'processed' => false,
                'reason' => 'EGC unit level not defined',
            ];
        }

        // Get all students with academic records
        // Note: Records were just finalized in CourseCompletionService, so we need fresh data
        $completedRecords = AcademicRecord::where('course_offering_id', $courseOffering->id)
            ->whereNotNull('final_letter_grade')
            ->with(['student', 'unit'])
            ->get();

        // Determine passing threshold based on course type
        // EGC courses require 70%, other courses require 60%
        $isEgcCourse = $courseOffering->unit->unit_type === 'egc';
        $passingThreshold = $isEgcCourse ? 70 : 60;

        $results = [
            'processed' => true,
            'unit_level' => $unitLevel,
            'unit_code' => $courseOffering->unit->code,
            'total_students' => $completedRecords->count(),
            'progressed' => [],
            'failed_students' => [],
            'warnings' => [],
            'errors' => [],
        ];

        foreach ($completedRecords as $record) {
            $student = $record->student;

            // Only process intake_pre_uni_gc students
            if ($student->status !== 'intake_pre_uni_gc') {
                $results['warnings'][] = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'reason' => "Student status is '{$student->status}', expected 'intake_pre_uni_gc'",
                ];
                continue;
            }

            // Check if passing based on completion_status
            // completion_status is already set by CourseCompletionService which checks:
            // 1. Attendance requirement (>= 80%) - PRIORITY
            // 2. Grade threshold (EGC: >= 70%, Others: >= 60%)
            // If either fails, completion_status = 'failed'
            $isPassing = $record->completion_status === 'completed';

            // Check if student's current level matches unit level
            $levelMatch = $student->gc_current_level === $unitLevel;

            if (! $levelMatch && $isPassing) {
                // Level mismatch warning
                $warningData = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'student_level' => $student->gc_current_level,
                    'unit_level' => $unitLevel,
                    'unit_code' => $courseOffering->unit->code,
                    'final_grade' => $record->final_letter_grade,
                    'reason' => "Level mismatch: Student at level {$student->gc_current_level} passed level {$unitLevel} unit",
                    'action' => 'Grade recorded but level NOT progressed',
                ];

                $results['warnings'][] = $warningData;

                Log::warning('EGC Level Mismatch - Student Passed', [
                    'student_id' => $student->student_id,
                    'student_level' => $student->gc_current_level,
                    'unit_level' => $unitLevel,
                    'unit_code' => $courseOffering->unit->code,
                    'final_grade' => $record->final_letter_grade,
                ]);

                // Send course completion notification (pass but no progression)
                $this->createNotification(
                    $student,
                    new EgcCourseCompletedNotification(
                        courseCode: $courseOffering->unit->code,
                        courseName: $courseOffering->unit->name,
                        grade: $record->final_letter_grade,
                        passed: true,
                        levelProgressed: false,
                        currentLevel: $student->gc_current_level,
                        message: 'You passed but level mismatch detected. Please contact academic office.'
                    )
                );

                continue;
            }

            if ($isPassing && $levelMatch) {
                // Progress the student to next level
                try {
                    $progressResult = $this->progressStudentLevel($student, $record, $unitLevel, $courseOffering);
                    $results['progressed'][] = $progressResult;

                    // Send success notification with level progression
                    $this->createNotification(
                        $student,
                        new EgcCourseCompletedNotification(
                            courseCode: $courseOffering->unit->code,
                            courseName: $courseOffering->unit->name,
                            grade: $record->final_letter_grade,
                            passed: true,
                            levelProgressed: true,
                            currentLevel: $student->gc_current_level,
                            message: $progressResult['completed_egc_program']
                                ? 'Congratulations! You completed all EGC levels!'
                                : "Level progressed: Level {$progressResult['from_level']} → Level {$progressResult['to_level']}"
                        )
                    );

                    // Send program completion notification if applicable
                    if ($progressResult['completed_egc_program']) {
                        $this->createNotification(
                            $student,
                            new EgcProgramCompletedNotification(
                                totalLevels: $progressResult['total_levels'],
                                newStatus: $student->status
                            )
                        );
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'student_id' => $student->student_id,
                        'student_name' => $student->full_name,
                        'error' => $e->getMessage(),
                    ];

                    Log::error('Failed to progress EGC level', [
                        'student_id' => $student->student_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                // Student failed - keep level the same
                $results['failed_students'][] = [
                    'student_id' => $student->student_id,
                    'student_name' => $student->full_name,
                    'grade' => $record->final_letter_grade,
                    'current_level' => $student->gc_current_level,
                    'action' => 'Level unchanged (failed course)',
                ];

                // Send failure notification
                $this->createNotification(
                    $student,
                    new EgcCourseCompletedNotification(
                        courseCode: $courseOffering->unit->code,
                        courseName: $courseOffering->unit->name,
                        grade: $record->final_letter_grade,
                        passed: false,
                        levelProgressed: false,
                        currentLevel: $student->gc_current_level,
                        message: 'You did not pass this course. Your level remains at Level '.$student->gc_current_level
                    )
                );

                Log::info('EGC Course Failed - Level Unchanged', [
                    'student_id' => $student->student_id,
                    'unit_code' => $courseOffering->unit->code,
                    'grade' => $record->final_letter_grade,
                    'level' => $student->gc_current_level,
                ]);
            }
        }

        return $results;
    }

    /**
     * Progress student to next EGC level
     */
    private function progressStudentLevel(
        Student $student,
        AcademicRecord $record,
        int $currentUnitLevel,
        CourseOffering $courseOffering
    ): array {
        $oldLevel = $student->gc_current_level;
        
        // IMPORTANT: Only progress if current level matches the unit level
        // This prevents duplicate progression if the course is accidentally marked as completed multiple times
        if ($oldLevel !== $currentUnitLevel) {
            Log::warning('EGC Level Already Progressed - Skipping', [
                'student_id' => $student->student_id,
                'student_current_level' => $oldLevel,
                'unit_level' => $currentUnitLevel,
                'unit_code' => $courseOffering->unit->code,
                'message' => 'Student already progressed beyond this level. No action taken.',
            ]);
            
            return [
                'student_id' => $student->student_id,
                'student_name' => $student->full_name,
                'from_level' => $oldLevel,
                'to_level' => $oldLevel, // No change
                'total_levels' => $student->gc_total_levels ?? 6,
                'completed_egc_program' => false,
                'new_status' => $student->status,
                'message' => 'Student already at level ' . $oldLevel . ', no progression needed',
                'already_progressed' => true,
            ];
        }
        
        $newLevel = $oldLevel + 1;
        $totalLevels = $student->gc_total_levels ?? 6; // Default 6 levels if not set

        // Update student's current level
        $updateData = [
            'gc_current_level' => $newLevel,
        ];

        // Check if student completed all EGC levels
        $completedProgram = false;
        if ($newLevel >= $totalLevels) {
            // Auto transition to intake_course status
            $updateData['status'] = 'intake_course';
            $completedProgram = true;

            Log::info('EGC Program Completed - Status Transitioned', [
                'student_id' => $student->student_id,
                'student_name' => $student->full_name,
                'from_status' => 'intake_pre_uni_gc',
                'to_status' => 'intake_course',
                'completed_levels' => $totalLevels,
            ]);
        }

        $student->update($updateData);

        // Update academic record with progression note
        $progressionNote = "EGC Level Progression: Level {$oldLevel} → Level {$newLevel}";
        if ($completedProgram) {
            $progressionNote .= ' | EGC Program Completed - Status changed to intake_course';
        }

        $gradeHistory = $record->grade_history ?? [];
        $gradeHistory['egc_level_progression'] = [
            'previous_level' => $oldLevel,
            'new_level' => $newLevel,
            'progressed_at' => now()->toISOString(),
            'unit_level_match' => true,
            'unit_code' => $record->unit->code,
            'completed_program' => $completedProgram,
        ];

        $record->update([
            'grade_history' => $gradeHistory,
            'administrative_notes' => ($record->administrative_notes ? $record->administrative_notes."\n" : '').$progressionNote,
        ]);

        Log::info('EGC Level Progressed', [
            'student_id' => $student->student_id,
            'student_name' => $student->full_name,
            'from_level' => $oldLevel,
            'to_level' => $newLevel,
            'unit_code' => $record->unit->code,
            'grade' => $record->final_letter_grade,
            'completed_program' => $completedProgram,
        ]);

        return [
            'student_id' => $student->student_id,
            'student_name' => $student->full_name,
            'from_level' => $oldLevel,
            'to_level' => $newLevel,
            'total_levels' => $totalLevels,
            'completed_egc_program' => $completedProgram,
            'new_status' => $completedProgram ? 'intake_course' : 'intake_pre_uni_gc',
            'message' => $completedProgram
                ? "🎉 Student completed all {$totalLevels} EGC levels and transitioned to intake_course!"
                : "Student progressed from Level {$oldLevel} to Level {$newLevel}",
        ];
    }

    /**
     * Create notification in custom notifications table
     */
    private function createNotification($notifiable, $notification): void
    {
        $data = $notification->toDatabase($notifiable);

        \App\Models\Notification::create([
            'type' => get_class($notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'],
            'channels' => $data['channels'],
            'is_important' => $data['is_important'] ?? false,
        ]);
    }

    /**
     * Get EGC progression summary for a student
     */
    public function getStudentProgressionSummary(Student $student): ?array
    {
        if ($student->status !== 'intake_pre_uni_gc') {
            return null;
        }

        $totalLevels = $student->gc_total_levels ?? 6;
        $currentLevel = $student->gc_current_level ?? 0;
        $startingLevel = $student->gc_starting_level ?? 0;

        $completedLevels = $currentLevel - $startingLevel;
        $remainingLevels = $totalLevels - $currentLevel;

        return [
            'starting_level' => $startingLevel,
            'current_level' => $currentLevel,
            'total_levels' => $totalLevels,
            'completed_levels' => max(0, $completedLevels),
            'remaining_levels' => max(0, $remainingLevels),
            'progress_percentage' => $totalLevels > 0 ? round(($currentLevel / $totalLevels) * 100, 2) : 0,
            'is_completed' => $currentLevel >= $totalLevels,
        ];
    }
}
