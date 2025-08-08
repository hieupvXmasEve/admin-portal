<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Services\AutomatedEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomatedEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private AutomatedEnrollmentService $service;

    private Campus $campus;

    private Program $program;

    private Semester $semester;

    private CurriculumVersion $curriculumVersion;

    private Unit $unit1;

    private Unit $unit2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AutomatedEnrollmentService;

        // Create test data
        $this->campus = Campus::factory()->create();
        $this->program = Program::factory()->create();
        $this->semester = Semester::factory()->create(['is_active' => true]);

        $this->curriculumVersion = CurriculumVersion::factory()->create([
            'program_id' => $this->program->id,
        ]);

        $this->unit1 = Unit::factory()->create(['code' => 'TEST101', 'credit_points' => 3]);
        $this->unit2 = Unit::factory()->create(['code' => 'TEST102', 'credit_points' => 3]);

        // Create curriculum units for first semester
        CurriculumUnit::factory()->create([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'unit_id' => $this->unit1->id,
            'semester_number' => 1,
            'year_level' => 1,
        ]);

        CurriculumUnit::factory()->create([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'unit_id' => $this->unit2->id,
            'semester_number' => 1,
            'year_level' => 1,
        ]);
    }

    public function test_automated_enrollment_creates_enrollments_successfully()
    {
        // Create test students
        $students = Student::factory()->count(3)->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'status' => 'active',
        ]);

        $studentIds = $students->pluck('id')->toArray();

        // Process automated enrollment
        $results = $this->service->processAutomatedEnrollment(
            $studentIds,
            $this->semester->id,
            $this->campus->id
        );

        // Assert enrollments were created
        $this->assertEquals(3, $results['enrollments']['created']);
        $this->assertEmpty($results['enrollments']['errors']);

        // Verify enrollments exist in database
        $this->assertEquals(3, Enrollment::where('semester_id', $this->semester->id)->count());

        // Verify each enrollment has correct data
        foreach ($students as $student) {
            $enrollment = Enrollment::where('student_id', $student->id)
                ->where('semester_id', $this->semester->id)
                ->first();

            $this->assertNotNull($enrollment);
            $this->assertEquals($this->curriculumVersion->id, $enrollment->curriculum_version_id);
            $this->assertEquals(1, $enrollment->semester_number);
            $this->assertEquals('in_progress', $enrollment->status);
        }
    }

    public function test_automated_enrollment_creates_course_offerings()
    {
        // Create test students
        $students = Student::factory()->count(2)->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'status' => 'active',
        ]);

        $studentIds = $students->pluck('id')->toArray();

        // Process automated enrollment
        $results = $this->service->processAutomatedEnrollment(
            $studentIds,
            $this->semester->id,
            $this->campus->id
        );

        // Assert course offerings were created
        $this->assertEquals(2, $results['course_offerings']['created']);
        $this->assertEmpty($results['course_offerings']['errors']);

        // Verify course offerings exist in database
        $this->assertEquals(2, CourseOffering::where('semester_id', $this->semester->id)->count());

        // Verify course offerings have correct data
        $offering1 = CourseOffering::where('unit_id', $this->unit1->id)
            ->where('semester_id', $this->semester->id)
            ->first();

        $this->assertNotNull($offering1);
        $this->assertEquals(30, $offering1->max_capacity); // Minimum capacity
        $this->assertEquals('in_person', $offering1->delivery_mode);
        $this->assertEquals('open', $offering1->enrollment_status);
        $this->assertTrue($offering1->is_active);
    }

    public function test_automated_enrollment_creates_course_registrations()
    {
        // Create test students
        $students = Student::factory()->count(2)->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'status' => 'active',
        ]);

        $studentIds = $students->pluck('id')->toArray();

        // Process automated enrollment
        $results = $this->service->processAutomatedEnrollment(
            $studentIds,
            $this->semester->id,
            $this->campus->id
        );

        // Assert course registrations were created (2 students × 2 units = 4 registrations)
        $this->assertEquals(4, $results['course_registrations']['created']);
        $this->assertEmpty($results['course_registrations']['errors']);

        // Verify course registrations exist in database
        $this->assertEquals(4, CourseRegistration::where('semester_id', $this->semester->id)->count());

        // Verify each student is registered for both units
        foreach ($students as $student) {
            $registrations = CourseRegistration::where('student_id', $student->id)
                ->where('semester_id', $this->semester->id)
                ->count();

            $this->assertEquals(2, $registrations);
        }
    }

    public function test_automated_enrollment_handles_existing_enrollments()
    {
        // Create test student
        $student = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'status' => 'active',
        ]);

        // Create existing enrollment
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'semester_number' => 1,
            'status' => 'in_progress',
        ]);

        // Process automated enrollment
        $results = $this->service->processAutomatedEnrollment(
            [$student->id],
            $this->semester->id,
            $this->campus->id
        );

        // Assert no new enrollments were created (skipped existing)
        $this->assertEquals(0, $results['enrollments']['created']);
        $this->assertEquals(1, $results['enrollments']['skipped']);

        // Verify only one enrollment exists
        $this->assertEquals(1, Enrollment::where('semester_id', $this->semester->id)->count());
    }

    public function test_automated_enrollment_calculates_correct_semester_number()
    {
        // Create test student
        $student = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'status' => 'active',
        ]);

        // Create previous semester enrollment
        $previousSemester = Semester::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'semester_id' => $previousSemester->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'semester_number' => 2,
            'status' => 'completed',
        ]);

        // Process automated enrollment
        $results = $this->service->processAutomatedEnrollment(
            [$student->id],
            $this->semester->id,
            $this->campus->id
        );

        // Assert enrollment was created with correct semester number
        $this->assertEquals(1, $results['enrollments']['created']);

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('semester_id', $this->semester->id)
            ->first();

        $this->assertEquals(3, $enrollment->semester_number); // Should be 2 + 1 = 3
    }

    public function test_automated_enrollment_generates_correct_summary()
    {
        // Create test students
        $students = Student::factory()->count(3)->create([
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'status' => 'active',
        ]);

        $studentIds = $students->pluck('id')->toArray();

        // Process automated enrollment
        $results = $this->service->processAutomatedEnrollment(
            $studentIds,
            $this->semester->id,
            $this->campus->id
        );

        // Assert summary is correct
        $summary = $results['summary'];
        $this->assertEquals(3, $summary['total_students_processed']);
        $this->assertEquals(3, $summary['enrollments_created']);
        $this->assertEquals(2, $summary['course_offerings_created']);
        $this->assertEquals(6, $summary['registrations_created']); // 3 students × 2 units
        $this->assertEquals(0, $summary['total_errors']);
        $this->assertEquals(100.0, $summary['success_rate']); // 3/3 = 100%
    }
}
