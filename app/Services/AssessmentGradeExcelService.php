<?php

declare(strict_types=1);

namespace App\Services;

use App\Exports\GradeTemplateExport;
use App\Imports\GradeImport;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AssessmentGradeExcelService
{
    /**
     * Export grade template for an assessment component detail
     */
    public function exportGradeTemplate(
        AssessmentComponentDetail $assessmentComponentDetail,
        CourseOffering $courseOffering
    ): string {
        try {
            // Increase memory limit temporarily for Excel generation
            $originalMemoryLimit = ini_get('memory_limit');
            $configuredMemoryLimit = config('excel-memory.memory_limit', '512M');
            ini_set('memory_limit', $configuredMemoryLimit);
            
            // Log memory usage if enabled
            if (config('excel-memory.log_memory_usage', false)) {
                Log::info('Excel export memory usage', [
                    'original_limit' => $originalMemoryLimit,
                    'new_limit' => $configuredMemoryLimit,
                    'current_usage' => memory_get_usage(true),
                    'peak_usage' => memory_get_peak_usage(true)
                ]);
            }
            
            // Generate filename first
            $fileName = $this->generateExportFileName($assessmentComponentDetail, $courseOffering);
            $filePath = 'temp/' . $fileName;
            
            // Use optimized export with memory management
            $export = new \App\Exports\OptimizedGradeTemplateExport(
                $assessmentComponentDetail, 
                $courseOffering
            );

            // Store the file with memory optimization
            Excel::store($export, $filePath, 'local');
            
            // Restore original memory limit
            ini_set('memory_limit', $originalMemoryLimit);
            
            // Force garbage collection
            gc_collect_cycles();

            // Return the full file path
            return Storage::disk('local')->path($filePath);

        } catch (\Exception $e) {
            // Restore memory limit on error
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }
            
            Log::error('Failed to export grade template', [
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'course_offering_id' => $courseOffering->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to generate grade template: ' . $e->getMessage());
        }
    }

    /**
     * Import grades from Excel file
     */
    public function importGrades(
        AssessmentComponentDetail $assessmentComponentDetail,
        CourseOffering $courseOffering,
        UploadedFile $file,
        array $options = [],
        int $lecturerId = 0
    ): array {
        try {
            // Validate file
            $this->validateImportFile($file);

            // Create import instance
            $import = new GradeImport(
                $assessmentComponentDetail,
                $courseOffering,
                $options,
                $lecturerId
            );

            // Process the import
            Excel::import($import, $file);

            // Get results
            $results = $import->getResults();

            // Log import activity
            Log::info('Grade import completed', [
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturerId,
                'results' => $results
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to import grades', [
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'course_offering_id' => $courseOffering->id,
                'lecturer_id' => $lecturerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to import grades: ' . $e->getMessage());
        }
    }

    /**
     * Preview import data without processing
     */
    public function previewImport(
        AssessmentComponentDetail $assessmentComponentDetail,
        CourseOffering $courseOffering,
        UploadedFile $file,
        int $previewRows = 10
    ): array {
        try {
            // Validate file
            $this->validateImportFile($file);

            // Create import instance with validation only
            $import = new GradeImport(
                $assessmentComponentDetail,
                $courseOffering,
                ['validate_only' => true]
            );

            // Process the import for validation
            Excel::import($import, $file);

            // Get validation results
            $results = $import->getResults();

            // Add preview data
            $results['preview_note'] = 'This is a preview only. No data has been imported.';
            $results['assessment_info'] = [
                'name' => $assessmentComponentDetail->name,
                'max_points' => $assessmentComponentDetail->max_points,
                'component_name' => $assessmentComponentDetail->assessmentComponent->name ?? 'Unknown'
            ];

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to preview import', [
                'assessment_component_detail_id' => $assessmentComponentDetail->id,
                'course_offering_id' => $courseOffering->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to preview import: ' . $e->getMessage());
        }
    }

    /**
     * Get students enrolled in course with their current scores for the assessment
     * Optimized for memory efficiency
     */
    public function getStudentsWithScores(
        AssessmentComponentDetail $assessmentComponentDetail,
        CourseOffering $courseOffering
    ): \Illuminate\Database\Eloquent\Builder {
        return Student::select(
                'students.id',
                'students.student_id', 
                'students.full_name', 
                'students.email'
            )
            ->join('course_registrations', 'students.id', '=', 'course_registrations.student_id')
            ->where('course_registrations.course_offering_id', $courseOffering->id)
            ->whereIn('course_registrations.registration_status', ['registered', 'confirmed'])
            ->orderBy('students.student_id');
    }
    
    /**
     * Get chunked students for memory-efficient processing
     */
    public function getStudentsChunked(
        AssessmentComponentDetail $assessmentComponentDetail,
        CourseOffering $courseOffering,
        int $chunkSize = 500
    ): \Generator {
        $query = $this->getStudentsWithScores($assessmentComponentDetail, $courseOffering);
        
        $query->chunk($chunkSize, function ($students) use ($assessmentComponentDetail, $courseOffering) {
            foreach ($students as $student) {
                // Load score only when needed
                $student->current_score = $this->getStudentScore($student, $assessmentComponentDetail, $courseOffering);
                yield $student;
            }
        });
    }
    
    /**
     * Get individual student score efficiently
     */
    public function getStudentScore($student, AssessmentComponentDetail $assessmentComponentDetail, CourseOffering $courseOffering)
    {
        return \App\Models\AssessmentComponentDetailScore::select(
                'points_earned', 'percentage_score', 'letter_grade', 'status', 
                'score_status', 'instructor_feedback', 'bonus_points', 'bonus_reason',
                'late_penalty_applied', 'private_notes', 'id'
            )
            ->where('student_id', $student->id)
            ->where('assessment_component_detail_id', $assessmentComponentDetail->id)
            ->where('course_offering_id', $courseOffering->id)
            ->first();
    }

    /**
     * Generate export filename
     */
    protected function generateExportFileName(
        AssessmentComponentDetail $assessmentComponentDetail,
        CourseOffering $courseOffering
    ): string {
        $assessmentName = str_replace(['/', '\\', '?', '*', '[', ']', ':', ';', '|', '=', ','], '_', $assessmentComponentDetail->name);
        $courseCode = $courseOffering->curriculum_unit->unit->code ?? 'UNKNOWN';
        $timestamp = now()->format('Y-m-d_H-i-s');

        return "grade_template_{$courseCode}_{$assessmentName}_{$timestamp}.xlsx";
    }

    /**
     * Validate import file
     */
    protected function validateImportFile(UploadedFile $file): void
    {
        // Check file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \Exception('File size cannot exceed 10MB');
        }

        // Check file extension
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception('File must be an Excel file (.xlsx, .xls) or CSV file (.csv)');
        }

        // Check if file is readable
        if (!$file->isValid()) {
            throw new \Exception('The uploaded file is not valid or is corrupted');
        }

        // Check if file is empty
        if ($file->getSize() === 0) {
            throw new \Exception('The uploaded file is empty');
        }
    }

    /**
     * Get total student count efficiently
     */
    protected function getTotalStudentCount(CourseOffering $courseOffering): int
    {
        return Student::join('course_registrations', 'students.id', '=', 'course_registrations.student_id')
            ->where('course_registrations.course_offering_id', $courseOffering->id)
            ->whereIn('course_registrations.registration_status', ['registered', 'confirmed'])
            ->count();
    }
    
    /**
     * Get import statistics for an assessment component detail
     */
    public function getImportStatistics(
        AssessmentComponentDetail $assessmentComponentDetail,
        CourseOffering $courseOffering
    ): array {
        $totalStudents = $this->getTotalStudentCount($courseOffering);

        $gradedStudents = DB::table('assessment_component_detail_scores')
            ->where('assessment_component_detail_id', $assessmentComponentDetail->id)
            ->where('course_offering_id', $courseOffering->id)
            ->whereNotNull('points_earned')
            ->count();

        $ungradedStudents = $totalStudents - $gradedStudents;

        return [
            'total_students' => $totalStudents,
            'graded_students' => $gradedStudents,
            'ungraded_students' => $ungradedStudents,
            'grading_completion_percentage' => $totalStudents > 0 ? round(($gradedStudents / $totalStudents) * 100, 2) : 0,
            'assessment_info' => [
                'name' => $assessmentComponentDetail->name,
                'max_points' => $assessmentComponentDetail->max_points,
                'weight' => $assessmentComponentDetail->weight,
                'due_date' => $assessmentComponentDetail->due_date,
                'component_name' => $assessmentComponentDetail->assessmentComponent->name ?? 'Unknown'
            ]
        ];
    }

    /**
     * Clean up temporary files older than 24 hours
     */
    public function cleanupTempFiles(): int
    {
        $tempPath = storage_path('app/temp');
        $deletedCount = 0;

        if (!is_dir($tempPath)) {
            return $deletedCount;
        }

        $files = glob($tempPath . '/grade_template_*.xlsx');
        $cutoffTime = time() - (24 * 60 * 60); // 24 hours ago

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        Log::info('Cleaned up temporary grade template files', [
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }
}
