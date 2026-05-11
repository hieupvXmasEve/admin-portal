<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AcademicRecord;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixAcademicRecordRetakeData extends Command
{
    protected $signature = 'academic-records:fix-retake-data
                            {--student-id= : Fix specific student only (student_id field)}
                            {--unit-id= : Fix specific unit only}
                            {--dry-run : Preview changes without updating database}
                            {--show-details : Show detailed progress}';

    protected $description = 'Fix academic records to properly set is_repeat_course and attempt_number based on unit retakes, and create missing academic records';

    private int $totalProcessed = 0;
    private int $totalUpdated = 0;
    private int $totalSkipped = 0;
    private int $totalCreated = 0;
    private int $totalMissing = 0;
    private array $errors = [];

    public function handle(): int
    {
        $this->info('🔧 Starting Academic Record Retake Data Fix...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $startTime = microtime(true);

        try {
            // Step 1: Create missing academic records for course_registrations
            $this->info('📝 Step 1: Finding missing academic records...');
            $this->createMissingAcademicRecords();
            $this->newLine();

            // Step 2: Fix existing academic records retake data
            $this->info('🔧 Step 2: Fixing retake data in existing records...');
            
            // Get all students with academic records
            $query = Student::whereHas('academicRecords');

            if ($studentId = $this->option('student-id')) {
                $query->where('student_id', $studentId);
                $this->info("🎯 Filtering by student: {$studentId}");
            }

            $students = $query->get();
            $totalStudents = $students->count();

            if ($totalStudents === 0) {
                $this->warn('No students found with academic records.');
            } else {
                $this->info("📚 Found {$totalStudents} student(s) to process");
                $this->newLine();

                $progressBar = $this->output->createProgressBar($totalStudents);
                $progressBar->setFormat('verbose');

                foreach ($students as $student) {
                    $progressBar->setMessage("Processing: {$student->student_id} ({$student->full_name})");
                    
                    $this->processStudentRecords($student);
                    
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine(2);
            }

            // Display summary
            $this->displaySummary($startTime);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('FixAcademicRecordRetakeData failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    private function createMissingAcademicRecords(): void
    {
        // Find all course_registrations that don't have academic_records
        $query = \App\Models\CourseRegistration::query()
            ->with(['student', 'courseOffering.unit', 'semester'])
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->whereDoesntHave('student.academicRecords', function ($q) {
                $q->whereColumn('academic_records.course_offering_id', 'course_registrations.course_offering_id')
                  ->whereColumn('academic_records.student_id', 'course_registrations.student_id');
            });

        if ($studentId = $this->option('student-id')) {
            $query->whereHas('student', function ($q) use ($studentId) {
                $q->where('student_id', $studentId);
            });
        }

        if ($unitId = $this->option('unit-id')) {
            $query->whereHas('courseOffering', function ($q) use ($unitId) {
                $q->where('unit_id', $unitId);
            });
        }

        $missingRegistrations = $query->get();
        $this->totalMissing = $missingRegistrations->count();

        if ($this->totalMissing === 0) {
            $this->info('✅ No missing academic records found. All course_registrations have corresponding academic_records.');
            return;
        }

        $this->warn("⚠️  Found {$this->totalMissing} course_registration(s) without academic_records");
        
        if ($this->option('show-details')) {
            $this->newLine();
        }

        foreach ($missingRegistrations as $registration) {
            $student = $registration->student;
            $courseOffering = $registration->courseOffering;
            $unit = $courseOffering->unit;
            
            if (!$student || !$courseOffering || !$unit) {
                $this->errors[] = [
                    'registration_id' => $registration->id,
                    'error' => 'Missing related data (student/courseOffering/unit)',
                ];
                continue;
            }

            $studentId = $student->student_id;
            $unitCode = $unit->code;
            $semesterCode = $registration->semester->code ?? 'N/A';

            if ($this->option('show-details')) {
                $this->line("  🆕 Creating for: {$studentId} - {$unitCode} ({$semesterCode})");
            }

            // Check previous attempts to determine retake status
            $previousAttempts = AcademicRecord::where('student_id', $student->id)
                ->where('unit_id', $unit->id)
                ->orderBy('attempt_number', 'desc')
                ->get();

            $isRetake = $previousAttempts->count() > 0;
            $attemptNumber = $isRetake ? $previousAttempts->first()->attempt_number + 1 : 1;
            $originalRecordId = $isRetake ? $previousAttempts->first()->id : null;

            if ($this->option('show-details')) {
                $retakeText = $isRetake ? "Retake (attempt #{$attemptNumber})" : "First attempt";
                $this->line("     → {$retakeText}");
            }

            if (!$this->option('dry-run')) {
                try {
                    DB::beginTransaction();

                    $academicRecord = AcademicRecord::create([
                        'student_id' => $student->id,
                        'course_offering_id' => $courseOffering->id,
                        'semester_id' => $registration->semester_id,
                        'unit_id' => $unit->id,
                        'program_id' => $student->program_id,
                        'campus_id' => $courseOffering->campus_id,

                        // Academic tracking
                        'is_repeat_course' => $isRetake,
                        'attempt_number' => $attemptNumber,
                        'original_record_id' => $originalRecordId,

                        // Status & dates
                        'grade_status' => 'in_progress',
                        'completion_status' => 'in_progress',
                        'enrollment_date' => $registration->registration_date ?? now(),

                        // Credit info
                        'credit_hours' => $registration->credit_hours ?? $unit->credit_points ?? 3,
                        'credit_hours_earned' => 0.00,
                        'credit_points' => $unit->credit_points ?? $registration->credit_hours ?? 3,
                        'credit_points_earned' => 0.00,

                        // Instructor
                        'instructor_id' => $courseOffering->lecture_id,

                        // Grades (null when newly registered)
                        'final_percentage' => null,
                        'final_letter_grade' => null,
                        'grade_points' => null,
                        'quality_points' => null,
                    ]);

                    Log::info('Missing academic record created', [
                        'academic_record_id' => $academicRecord->id,
                        'registration_id' => $registration->id,
                        'student_id' => $studentId,
                        'student_name' => $student->full_name,
                        'unit_code' => $unitCode,
                        'course_offering_id' => $courseOffering->id,
                        'is_repeat_course' => $isRetake,
                        'attempt_number' => $attemptNumber,
                        'original_record_id' => $originalRecordId,
                    ]);

                    DB::commit();
                    $this->totalCreated++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    
                    $this->errors[] = [
                        'student_id' => $studentId,
                        'unit_code' => $unitCode,
                        'registration_id' => $registration->id,
                        'error' => $e->getMessage(),
                    ];
                    
                    Log::error('Failed to create missing academic record', [
                        'registration_id' => $registration->id,
                        'student_id' => $studentId,
                        'error' => $e->getMessage(),
                    ]);

                    if ($this->option('show-details')) {
                        $this->error("     ✗ Failed: {$e->getMessage()}");
                    }
                }
            } else {
                $this->totalCreated++;
            }
        }

        if ($this->option('show-details')) {
            $this->newLine();
        }

        if ($this->option('dry-run')) {
            $this->warn("📋 DRY RUN: Would create {$this->totalCreated} academic record(s)");
        } else {
            $this->info("✅ Created {$this->totalCreated} academic record(s)");
        }
    }

    private function processStudentRecords(Student $student): void
    {
        // Get all academic records for this student, grouped by unit
        $query = AcademicRecord::where('student_id', $student->id)
            ->with(['unit:id,code,name', 'semester:id,code,name', 'courseOffering:id,section_code']);

        if ($unitId = $this->option('unit-id')) {
            $query->where('unit_id', $unitId);
        }

        // Group by unit to process retakes
        $recordsByUnit = $query->get()->groupBy('unit_id');

        foreach ($recordsByUnit as $unitId => $records) {
            // Skip if only one attempt
            if ($records->count() <= 1) {
                $this->totalSkipped++;
                continue;
            }

            // Sort by enrollment_date to determine attempt order
            $sortedRecords = $records->sortBy('enrollment_date')->values();
            
            $unit = $sortedRecords->first()->unit;
            $unitCode = $unit->code ?? "Unit ID {$unitId}";

            if ($this->option('show-details')) {
                $this->newLine();
                $this->line("  📖 Processing unit: {$unitCode} ({$sortedRecords->count()} attempts)");
            }

            $this->processUnitAttempts($student, $sortedRecords, $unitCode);
        }
    }

    private function processUnitAttempts(Student $student, $sortedRecords, string $unitCode): void
    {
        $attemptNumber = 1;
        $firstRecordId = null;

        foreach ($sortedRecords as $index => $record) {
            $this->totalProcessed++;

            // First attempt
            if ($index === 0) {
                $firstRecordId = $record->id;
                $expectedData = [
                    'is_repeat_course' => false,
                    'attempt_number' => 1,
                    'original_record_id' => null,
                ];
            } else {
                // Subsequent attempts (retakes)
                $attemptNumber = $index + 1;
                $expectedData = [
                    'is_repeat_course' => true,
                    'attempt_number' => $attemptNumber,
                    'original_record_id' => $firstRecordId,
                ];
            }

            // Check if update is needed
            $needsUpdate = 
                $record->is_repeat_course !== $expectedData['is_repeat_course'] ||
                $record->attempt_number !== $expectedData['attempt_number'] ||
                $record->original_record_id !== $expectedData['original_record_id'];

            if ($needsUpdate) {
                $semester = $record->semester->code ?? 'N/A';
                $section = $record->courseOffering->section_code ?? '';
                $sectionText = $section ? " (Section: {$section})" : '';

                if ($this->option('show-details')) {
                    $this->line("    🔄 Updating attempt #{$attemptNumber} - {$semester}{$sectionText}");
                    $this->line("       Before: is_repeat={$record->is_repeat_course}, attempt={$record->attempt_number}, original_id={$record->original_record_id}");
                    $this->line("       After:  is_repeat={$expectedData['is_repeat_course']}, attempt={$expectedData['attempt_number']}, original_id={$expectedData['original_record_id']}");
                }

                if (!$this->option('dry-run')) {
                    try {
                        $record->update($expectedData);
                        
                        Log::info('Academic record retake data fixed', [
                            'student_id' => $student->student_id,
                            'student_name' => $student->full_name,
                            'unit_code' => $unitCode,
                            'academic_record_id' => $record->id,
                            'semester' => $semester,
                            'old_data' => [
                                'is_repeat_course' => $record->getOriginal('is_repeat_course'),
                                'attempt_number' => $record->getOriginal('attempt_number'),
                                'original_record_id' => $record->getOriginal('original_record_id'),
                            ],
                            'new_data' => $expectedData,
                        ]);
                    } catch (\Exception $e) {
                        $this->errors[] = [
                            'student_id' => $student->student_id,
                            'unit_code' => $unitCode,
                            'record_id' => $record->id,
                            'error' => $e->getMessage(),
                        ];
                        
                        Log::error('Failed to update academic record', [
                            'student_id' => $student->student_id,
                            'record_id' => $record->id,
                            'error' => $e->getMessage(),
                        ]);
                        
                        continue;
                    }
                }

                $this->totalUpdated++;
            } else {
                if ($this->option('show-details')) {
                    $semester = $record->semester->code ?? 'N/A';
                    $this->line("    ✓ Attempt #{$attemptNumber} - {$semester} (already correct)");
                }
                $this->totalSkipped++;
            }
        }
    }

    private function displaySummary(float $startTime): void
    {
        $executionTime = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info('📊 Summary Report');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Missing Registrations Found', $this->totalMissing],
                ['Academic Records Created', $this->totalCreated],
                ['---', '---'],
                ['Existing Records Processed', $this->totalProcessed],
                ['Existing Records Updated', $this->totalUpdated],
                ['Records Skipped (Correct)', $this->totalSkipped],
                ['---', '---'],
                ['Total Errors', count($this->errors)],
            ]
        );

        $this->newLine();
        $this->info("⏱️  Execution time: {$executionTime} seconds");

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn("📋 DRY RUN MODE - Summary:");
            if ($this->totalCreated > 0) {
                $this->line("  • Would create {$this->totalCreated} missing academic record(s)");
            }
            if ($this->totalUpdated > 0) {
                $this->line("  • Would update {$this->totalUpdated} existing record(s)");
            }
            $this->newLine();
            $this->line("Run without --dry-run to apply changes");
        } else {
            $this->newLine();
            if ($this->totalCreated > 0) {
                $this->info("✅ Created {$this->totalCreated} missing academic record(s)");
            }
            if ($this->totalUpdated > 0) {
                $this->info("✅ Updated {$this->totalUpdated} existing academic record(s)");
            }
        }

        if (count($this->errors) > 0) {
            $this->newLine();
            $this->error("❌ Encountered " . count($this->errors) . " error(s):");
            foreach (array_slice($this->errors, 0, 10) as $error) {
                $this->line("  • Student {$error['student_id']}, Unit {$error['unit_code']}, Record {$error['record_id']}: {$error['error']}");
            }
            if (count($this->errors) > 10) {
                $this->line("  ... and " . (count($this->errors) - 10) . " more. Check logs for details.");
            }
        }
    }
}
