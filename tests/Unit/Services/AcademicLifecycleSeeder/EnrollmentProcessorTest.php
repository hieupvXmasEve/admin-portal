<?php

namespace Tests\Unit\Services\AcademicLifecycleSeeder;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\AcademicLifecycleSeeder\Processors\EnrollmentProcessor;
use App\Services\AcademicLifecycleSeeder\SeederConfiguration;
use App\Models\Student;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\CurriculumVersion;
use Carbon\Carbon;

/**
 * Test the critical EnrollmentProcessor functionality
 * 
 * This tests the foundation of the enrollment-driven approach:
 * - Enrollment creation for active students
 * - Correct semester number calculation
 * - Enrollment validation logic
 */
class EnrollmentProcessorTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentProcessor $processor;
    private SeederConfiguration $configuration;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->processor = new EnrollmentProcessor();
        $this->configuration = new SeederConfiguration([
            'targetSemesters' => ['2024-S1'],
        ]);
    }

    /** @test */
    public function it_creates_enrollments_for_active_students()
    {
        // Arrange
        $curriculumVersion = CurriculumVersion::factory()->create();
        
        $semester = Semester::factory()->create([
            'code' => '2024-S1',
            'start_date' => '2024-02-01',
            'end_date' => '2024-06-30',
        ]);

        $student = Student::factory()->create([
            'academic_status' => 'active',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-01-15', // Before semester start
        ]);

        // Act
        $enrollments = $this->processor->createSemesterEnrollments($semester);

        // Assert
        $this->assertCount(1, $enrollments);
        
        $enrollment = $enrollments->first();
        $this->assertEquals($student->id, $enrollment->student_id);
        $this->assertEquals($semester->id, $enrollment->semester_id);
        $this->assertEquals($curriculumVersion->id, $enrollment->curriculum_version_id);
        $this->assertEquals('in_progress', $enrollment->status);
    }

    /** @test */
    public function it_calculates_correct_semester_number()
    {
        // Arrange
        $curriculumVersion = CurriculumVersion::factory()->create();
        
        // Create multiple semesters
        $semester1 = Semester::factory()->create([
            'code' => '2024-S1',
            'start_date' => '2024-02-01',
            'end_date' => '2024-06-30',
        ]);
        
        $semester2 = Semester::factory()->create([
            'code' => '2024-S2',
            'start_date' => '2024-07-01',
            'end_date' => '2024-11-30',
        ]);

        $student = Student::factory()->create([
            'academic_status' => 'active',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-01-15', // Before first semester
        ]);

        // Act & Assert
        $semesterNumber1 = $this->processor->calculateStudentSemesterNumber($student, $semester1);
        $this->assertEquals(1, $semesterNumber1, 'First semester should be semester 1');

        $semesterNumber2 = $this->processor->calculateStudentSemesterNumber($student, $semester2);
        $this->assertEquals(2, $semesterNumber2, 'Second semester should be semester 2');
    }

    /** @test */
    public function it_handles_late_admission_correctly()
    {
        // Arrange
        $curriculumVersion = CurriculumVersion::factory()->create();
        
        $semester1 = Semester::factory()->create([
            'code' => '2024-S1',
            'start_date' => '2024-02-01',
            'end_date' => '2024-06-30',
        ]);
        
        $semester2 = Semester::factory()->create([
            'code' => '2024-S2',
            'start_date' => '2024-07-01',
            'end_date' => '2024-11-30',
        ]);

        // Student admitted after first semester started
        $student = Student::factory()->create([
            'academic_status' => 'active',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-06-15', // During first semester
        ]);

        // Act & Assert
        $semesterNumber1 = $this->processor->calculateStudentSemesterNumber($student, $semester1);
        $this->assertEquals(1, $semesterNumber1, 'Student should still be in semester 1 if admitted during it');

        $semesterNumber2 = $this->processor->calculateStudentSemesterNumber($student, $semester2);
        $this->assertEquals(2, $semesterNumber2, 'Next semester should be semester 2');
    }

    /** @test */
    public function it_filters_eligible_students_correctly()
    {
        // Arrange
        $curriculumVersion = CurriculumVersion::factory()->create();
        
        $semester = Semester::factory()->create([
            'code' => '2024-S1',
            'start_date' => '2024-02-01',
            'end_date' => '2024-06-30',
        ]);

        // Create various students
        $activeStudent = Student::factory()->create([
            'academic_status' => 'active',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-01-15',
        ]);

        $inactiveStudent = Student::factory()->create([
            'academic_status' => 'inactive',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-01-15',
        ]);

        $futureStudent = Student::factory()->create([
            'academic_status' => 'active',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-08-01', // After semester start
        ]);

        // Act
        $eligibleStudents = $this->processor->determineEligibleStudents($semester);

        // Assert
        $this->assertCount(1, $eligibleStudents);
        $this->assertTrue($eligibleStudents->contains($activeStudent));
        $this->assertFalse($eligibleStudents->contains($inactiveStudent));
        $this->assertFalse($eligibleStudents->contains($futureStudent));
    }

    /** @test */
    public function it_does_not_create_duplicate_enrollments()
    {
        // Arrange
        $curriculumVersion = CurriculumVersion::factory()->create();
        
        $semester = Semester::factory()->create([
            'code' => '2024-S1',
            'start_date' => '2024-02-01',
            'end_date' => '2024-06-30',
        ]);

        $student = Student::factory()->create([
            'academic_status' => 'active',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-01-15',
        ]);

        // Create existing enrollment
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'curriculum_version_id' => $curriculumVersion->id,
            'semester_number' => 1,
            'status' => 'in_progress',
        ]);

        // Act
        $enrollments = $this->processor->createSemesterEnrollments($semester);

        // Assert
        $this->assertCount(0, $enrollments, 'Should not create duplicate enrollments');
        
        // Verify only one enrollment exists
        $totalEnrollments = Enrollment::where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->count();
        $this->assertEquals(1, $totalEnrollments);
    }

    /** @test */
    public function it_validates_prerequisites_correctly()
    {
        // Test with no students
        $this->assertFalse($this->processor->validatePrerequisites());

        // Add active student with curriculum version
        $curriculumVersion = CurriculumVersion::factory()->create();
        Student::factory()->create([
            'academic_status' => 'active',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-01-15',
        ]);

        // Add semester
        Semester::factory()->create([
            'code' => '2024-S1',
            'start_date' => '2024-02-01',
            'end_date' => '2024-06-30',
        ]);

        // Should now pass validation
        $this->assertTrue($this->processor->validatePrerequisites());
    }

    /** @test */
    public function it_handles_edge_case_semester_calculations()
    {
        // Arrange
        $curriculumVersion = CurriculumVersion::factory()->create();
        
        $semester = Semester::factory()->create([
            'code' => '2024-S1',
            'start_date' => '2024-02-01',
            'end_date' => '2024-06-30',
        ]);

        // Student admitted on exact semester start date
        $student = Student::factory()->create([
            'academic_status' => 'active',
            'curriculum_version_id' => $curriculumVersion->id,
            'admission_date' => '2024-02-01', // Exact semester start
        ]);

        // Act
        $semesterNumber = $this->processor->calculateStudentSemesterNumber($student, $semester);

        // Assert
        $this->assertEquals(1, $semesterNumber, 'Student admitted on semester start should be in semester 1');
    }
}
