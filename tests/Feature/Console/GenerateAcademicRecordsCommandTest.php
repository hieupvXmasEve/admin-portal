<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateAcademicRecordsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Command runs successfully with default (original) service
     */
    public function test_command_runs_successfully_with_original_service(): void
    {
        // Arrange
        $this->createTestData(students: 3, sessionsPerCourse: 5);

        // Act
        $this->artisan('academic-records:generate')
            ->expectsOutput('Starting academic record generation...')
            ->expectsOutput('✓ Successfully created 3 academic records')
            ->assertExitCode(0);

        // Assert
        $this->assertDatabaseCount('academic_records', 3);
    }

    /**
     * Test: Command runs successfully with optimized service
     */
    public function test_command_runs_successfully_with_optimized_service(): void
    {
        // Arrange
        $this->createTestData(students: 5, sessionsPerCourse: 10);

        // Act
        $this->artisan('academic-records:generate --optimized')
            ->expectsOutputToContain('🚀 Using OPTIMIZED version for large datasets')
            ->expectsOutputToContain('Successfully created')
            ->assertExitCode(0);

        // Assert
        $this->assertDatabaseCount('academic_records', 5);
    }

    /**
     * Test: Dry run mode does not create records
     */
    public function test_dry_run_does_not_create_records(): void
    {
        // Arrange
        $this->createTestData(students: 2, sessionsPerCourse: 5);

        // Act
        $this->artisan('academic-records:generate --dry-run')
            ->expectsOutput('DRY RUN MODE - No changes will be made')
            ->assertExitCode(0);

        // Assert: No records created
        $this->assertDatabaseCount('academic_records', 0);
    }

    /**
     * Test: Update existing records flag works
     */
    public function test_update_existing_records_flag_works(): void
    {
        // Arrange: Create initial records
        $testData = $this->createTestData(students: 2, sessionsPerCourse: 10);
        $this->artisan('academic-records:generate')->assertExitCode(0);

        $student = $testData['students']->first();
        $record = AcademicRecord::where('student_id', $student->id)->first();
        $initialPercentage = $record->attendance_percentage;

        // Add more sessions and attendances
        $newSessions = ClassSession::factory()->count(5)->create([
            'course_offering_id' => $testData['courseOffering']->id,
        ]);
        foreach ($newSessions as $session) {
            Attendance::factory()->create([
                'student_id' => $student->id,
                'class_session_id' => $session->id,
                'status' => 'present',
            ]);
        }

        // Act: Update existing
        $this->artisan('academic-records:generate --update-existing')
            ->expectsOutputToContain('Updating attendance statistics')
            ->assertExitCode(0);

        // Assert: Record updated
        $record->refresh();
        $this->assertGreaterThan($initialPercentage, $record->attendance_percentage);
        $this->assertEquals(15, $record->total_class_sessions);
    }

    /**
     * Test: Update existing with optimized flag
     */
    public function test_update_existing_with_optimized_flag(): void
    {
        // Arrange
        $this->createTestData(students: 10, sessionsPerCourse: 5);
        $this->artisan('academic-records:generate --optimized')->assertExitCode(0);

        // Act
        $this->artisan('academic-records:generate --update-existing --optimized')
            ->expectsOutputToContain('🚀 Using OPTIMIZED version')
            ->expectsOutputToContain('Successfully updated')
            ->assertExitCode(0);

        // Assert
        $this->assertEquals(10, AcademicRecord::count());
    }

    /**
     * Test: Command shows memory usage stats
     */
    public function test_command_shows_memory_usage_stats(): void
    {
        // Arrange
        $this->createTestData(students: 5, sessionsPerCourse: 5);

        // Act & Assert
        $this->artisan('academic-records:generate')
            ->expectsOutputToContain('Completed in')
            ->expectsOutputToContain('Memory used:')
            ->expectsOutputToContain('Peak:')
            ->assertExitCode(0);
    }

    /**
     * Test: Command handles no attendance data gracefully
     */
    public function test_command_handles_no_attendance_data(): void
    {
        // Arrange: Create students but no attendances
        $campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $campus->id]);
        Student::factory()->count(3)->create([
            'campus_id' => $campus->id,
            'program_id' => $program->id,
        ]);

        // Act & Assert
        $this->artisan('academic-records:generate')
            ->assertExitCode(0);

        $this->assertDatabaseCount('academic_records', 0);
    }

    /**
     * Test: Command shows statistics table
     */
    public function test_command_shows_statistics_table(): void
    {
        // Arrange
        $this->createTestData(students: 3, sessionsPerCourse: 5);

        // Act
        $output = $this->artisan('academic-records:generate')
            ->expectsTable(
                ['Academic Records Generation', 'Count'],
                [
                    ['Created/Updated', 3],
                    ['Skipped', 0],
                    ['Errors', 0],
                ]
            )
            ->assertExitCode(0);
    }

    /**
     * Test: Command with optimized flag handles large dataset
     */
    public function test_optimized_command_handles_large_dataset(): void
    {
        // Arrange: Create more than chunk size (100)
        $this->createTestData(students: 150, sessionsPerCourse: 3);

        // Act
        $startMemory = memory_get_usage(true);

        $this->artisan('academic-records:generate --optimized')
            ->assertExitCode(0);

        $endMemory = memory_get_usage(true);
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;

        // Assert
        $this->assertDatabaseCount('academic_records', 150);

        // Memory should be reasonable (less than 500MB)
        $this->assertLessThan(500, $memoryUsed);
    }

    /**
     * Test: Dry run with optimized flag
     */
    public function test_dry_run_with_optimized_flag(): void
    {
        // Arrange
        $this->createTestData(students: 5, sessionsPerCourse: 5);

        // Act
        $this->artisan('academic-records:generate --optimized --dry-run')
            ->expectsOutput('🚀 Using OPTIMIZED version for large datasets')
            ->expectsOutput('DRY RUN MODE - No changes will be made')
            ->assertExitCode(0);

        // Assert
        $this->assertDatabaseCount('academic_records', 0);
    }

    /**
     * Test: Command handles database errors gracefully
     */
    public function test_command_handles_database_errors_gracefully(): void
    {
        // This test would require mocking database failures
        // For now, we'll test with invalid data

        // Arrange: Create test data
        $testData = $this->createTestData(students: 2, sessionsPerCourse: 5);

        // Make one student invalid (this might still succeed in the service,
        // but demonstrates error handling structure)
        $student = $testData['students']->first();
        $student->update(['program_id' => null]);

        // Act & Assert: Command should complete even with errors
        $this->artisan('academic-records:generate')
            ->assertExitCode(0); // Should exit successfully even with some errors
    }

    /**
     * Test: Multiple runs are idempotent (second run skips existing)
     */
    public function test_multiple_runs_are_idempotent(): void
    {
        // Arrange
        $this->createTestData(students: 3, sessionsPerCourse: 5);

        // Act: First run
        $this->artisan('academic-records:generate')
            ->expectsOutputToContain('Successfully created 3 academic records')
            ->assertExitCode(0);

        // Act: Second run
        $this->artisan('academic-records:generate')
            ->expectsOutputToContain('Skipped 3 records')
            ->assertExitCode(0);

        // Assert: Still only 3 records
        $this->assertDatabaseCount('academic_records', 3);
    }

    /**
     * Test: Command execution time is displayed
     */
    public function test_command_execution_time_displayed(): void
    {
        // Arrange
        $this->createTestData(students: 5, sessionsPerCourse: 5);

        // Act
        $this->artisan('academic-records:generate')
            ->expectsOutputToContain('Completed in')
            ->expectsOutputToContain('seconds')
            ->assertExitCode(0);
    }

    /**
     * Test: Optimized version with update-existing shows progress
     */
    public function test_optimized_update_existing_shows_progress(): void
    {
        // Arrange
        $this->createTestData(students: 10, sessionsPerCourse: 5);
        $this->artisan('academic-records:generate --optimized')->assertExitCode(0);

        // Act
        $this->artisan('academic-records:generate --update-existing --optimized')
            ->expectsOutputToContain('Updating attendance statistics')
            ->assertExitCode(0);
    }

    // ==================== Helper Methods ====================

    /**
     * Create test data with students, course offering, sessions, and attendances
     */
    private function createTestData(int $students, int $sessionsPerCourse): array
    {
        $campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $campus->id]);
        $semester = Semester::factory()->create();
        $unit = Unit::factory()->create();

        $courseOffering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'campus_id' => $campus->id,
        ]);

        $sessions = ClassSession::factory()->count($sessionsPerCourse)->create([
            'course_offering_id' => $courseOffering->id,
        ]);

        $studentModels = Student::factory()->count($students)->create([
            'campus_id' => $campus->id,
            'program_id' => $program->id,
        ]);

        // Create attendances for each student
        foreach ($studentModels as $student) {
            foreach ($sessions as $session) {
                Attendance::factory()->create([
                    'student_id' => $student->id,
                    'class_session_id' => $session->id,
                    'status' => 'present',
                ]);
            }
        }

        return [
            'students' => $studentModels,
            'courseOffering' => $courseOffering,
            'sessions' => $sessions,
            'campus' => $campus,
            'program' => $program,
        ];
    }
}
