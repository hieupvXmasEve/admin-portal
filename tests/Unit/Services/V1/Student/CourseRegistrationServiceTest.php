<?php

declare(strict_types=1);

namespace Tests\Unit\Services\V1\Student;

use App\Models\Student;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Campus;
use App\Models\Program;
use App\Models\CurriculumVersion;
use App\Models\CurriculumUnit;
use App\Models\Unit;
use App\Models\Semester;
use App\Services\V1\Student\CourseRegistrationService;
use App\Services\V1\Student\ConflictDetectionService;
use App\Services\V1\Student\EnrollmentCapacityService;
use App\Services\V1\Student\PrerequisiteValidationService;
use App\Exceptions\BusinessLogicException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class CourseRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CourseRegistrationService $registrationService;
    protected ConflictDetectionService $conflictService;
    protected EnrollmentCapacityService $capacityService;
    protected PrerequisiteValidationService $prerequisiteService;
    protected Student $student;
    protected CourseOffering $courseOffering;
    protected Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock services
        $this->conflictService = Mockery::mock(ConflictDetectionService::class);
        $this->capacityService = Mockery::mock(EnrollmentCapacityService::class);
        $this->prerequisiteService = Mockery::mock(PrerequisiteValidationService::class);

        $this->registrationService = new CourseRegistrationService(
            $this->conflictService,
            $this->capacityService,
            $this->prerequisiteService
        );

        // Create test data
        $this->createTestData();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createTestData(): void
    {
        $campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $campus->id]);
        $curriculumVersion = CurriculumVersion::factory()->create(['program_id' => $program->id]);
        
        $this->student = Student::factory()->create([
            'campus_id' => $campus->id,
            'program_id' => $program->id,
            'curriculum_version_id' => $curriculumVersion->id,
        ]);

        $this->semester = Semester::factory()->create([
            'is_active' => true,
            'registration_start_date' => now()->subDays(7),
            'registration_end_date' => now()->addDays(7),
        ]);

        $unit = Unit::factory()->create();
        $curriculumUnit = CurriculumUnit::factory()->create([
            'curriculum_version_id' => $curriculumVersion->id,
            'unit_id' => $unit->id,
            'credit_hours' => 3,
        ]);

        $this->courseOffering = CourseOffering::factory()->create([
            'semester_id' => $this->semester->id,
            'curriculum_unit_id' => $curriculumUnit->id,
            'is_active' => true,
            'max_enrollment' => 30,
        ]);
    }

    public function test_get_available_courses_returns_filtered_courses(): void
    {
        $availableCourses = $this->registrationService->getAvailableCourses($this->student);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $availableCourses);
        $this->assertGreaterThan(0, $availableCourses->count());
        
        $firstCourse = $availableCourses->first();
        $this->assertArrayHasKey('id', $firstCourse);
        $this->assertArrayHasKey('unit', $firstCourse);
        $this->assertArrayHasKey('lecturer', $firstCourse);
        $this->assertArrayHasKey('schedule', $firstCourse);
        $this->assertArrayHasKey('enrollment', $firstCourse);
        $this->assertArrayHasKey('registration_eligibility', $firstCourse);
    }

    public function test_get_available_courses_excludes_already_registered_courses(): void
    {
        // Register student for the course
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'registered',
        ]);

        $availableCourses = $this->registrationService->getAvailableCourses($this->student);

        $registeredCourseIds = $availableCourses->pluck('id')->toArray();
        $this->assertNotContains($this->courseOffering->id, $registeredCourseIds);
    }

    public function test_register_for_course_creates_registration_successfully(): void
    {
        // Mock service responses for successful registration
        $this->capacityService->shouldReceive('hasAvailableCapacity')
            ->with($this->courseOffering)
            ->andReturn(true);

        $this->prerequisiteService->shouldReceive('hasMetPrerequisites')
            ->with($this->student, $this->courseOffering)
            ->andReturn(true);

        $this->conflictService->shouldReceive('detectConflicts')
            ->with($this->student, $this->courseOffering)
            ->andReturn(collect());

        $this->capacityService->shouldReceive('updateEnrollmentCount')
            ->with($this->courseOffering)
            ->once();

        $registration = $this->registrationService->registerForCourse($this->student, $this->courseOffering->id);

        $this->assertInstanceOf(CourseRegistration::class, $registration);
        $this->assertEquals($this->student->id, $registration->student_id);
        $this->assertEquals($this->courseOffering->id, $registration->course_offering_id);
        $this->assertEquals('registered', $registration->registration_status);
        
        $this->assertDatabaseHas('course_registrations', [
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'registration_status' => 'registered',
        ]);
    }

    public function test_register_for_course_fails_when_no_capacity(): void
    {
        $this->capacityService->shouldReceive('hasAvailableCapacity')
            ->with($this->courseOffering)
            ->andReturn(false);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('This course is full');

        $this->registrationService->registerForCourse($this->student, $this->courseOffering->id);
    }

    public function test_register_for_course_fails_when_prerequisites_not_met(): void
    {
        $this->capacityService->shouldReceive('hasAvailableCapacity')
            ->with($this->courseOffering)
            ->andReturn(true);

        $this->prerequisiteService->shouldReceive('hasMetPrerequisites')
            ->with($this->student, $this->courseOffering)
            ->andReturn(false);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Prerequisites not met for this course');

        $this->registrationService->registerForCourse($this->student, $this->courseOffering->id);
    }

    public function test_register_for_course_fails_when_schedule_conflicts(): void
    {
        $this->capacityService->shouldReceive('hasAvailableCapacity')
            ->with($this->courseOffering)
            ->andReturn(true);

        $this->prerequisiteService->shouldReceive('hasMetPrerequisites')
            ->with($this->student, $this->courseOffering)
            ->andReturn(true);

        $this->conflictService->shouldReceive('detectConflicts')
            ->with($this->student, $this->courseOffering)
            ->andReturn(collect(['conflict'])); // Non-empty collection indicates conflicts

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Schedule conflict detected with existing registrations');

        $this->registrationService->registerForCourse($this->student, $this->courseOffering->id);
    }

    public function test_drop_course_updates_registration_status(): void
    {
        $registration = CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'registered',
        ]);

        $this->capacityService->shouldReceive('updateEnrollmentCount')
            ->with($this->courseOffering)
            ->once();

        $result = $this->registrationService->dropCourse($this->student, $registration);

        $this->assertTrue($result);
        
        $registration->refresh();
        $this->assertEquals('dropped', $registration->registration_status);
        $this->assertNotNull($registration->drop_date);
    }

    public function test_drop_course_fails_for_wrong_student(): void
    {
        $otherStudent = Student::factory()->create();
        $registration = CourseRegistration::factory()->create([
            'student_id' => $otherStudent->id,
            'course_offering_id' => $this->courseOffering->id,
            'registration_status' => 'registered',
        ]);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('You can only drop your own registrations');

        $this->registrationService->dropCourse($this->student, $registration);
    }

    public function test_drop_course_fails_for_invalid_status(): void
    {
        $registration = CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'registration_status' => 'completed',
        ]);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Cannot drop course with status: completed');

        $this->registrationService->dropCourse($this->student, $registration);
    }

    public function test_get_student_registrations_returns_formatted_data(): void
    {
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'registered',
        ]);

        $registrations = $this->registrationService->getStudentRegistrations($this->student);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $registrations);
        $this->assertEquals(1, $registrations->count());
        
        $registration = $registrations->first();
        $this->assertArrayHasKey('id', $registration);
        $this->assertArrayHasKey('status', $registration);
        $this->assertArrayHasKey('unit', $registration);
        $this->assertArrayHasKey('lecturer', $registration);
        $this->assertArrayHasKey('schedule', $registration);
        $this->assertArrayHasKey('semester', $registration);
    }

    public function test_registration_fails_when_registration_closed(): void
    {
        // Set registration period to past
        $this->semester->update([
            'registration_start_date' => now()->subDays(14),
            'registration_end_date' => now()->subDays(7),
        ]);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Registration is not currently open');

        $this->registrationService->registerForCourse($this->student, $this->courseOffering->id);
    }

    public function test_registration_fails_when_course_inactive(): void
    {
        $this->courseOffering->update(['is_active' => false]);

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('This course is not available for registration');

        $this->registrationService->registerForCourse($this->student, $this->courseOffering->id);
    }

    public function test_registration_fails_when_already_registered(): void
    {
        // Create existing registration
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'registration_status' => 'registered',
        ]);

        $this->capacityService->shouldReceive('hasAvailableCapacity')->andReturn(true);
        $this->prerequisiteService->shouldReceive('hasMetPrerequisites')->andReturn(true);
        $this->conflictService->shouldReceive('detectConflicts')->andReturn(collect());

        $this->expectException(BusinessLogicException::class);
        $this->expectExceptionMessage('Already registered for this course');

        $this->registrationService->registerForCourse($this->student, $this->courseOffering->id);
    }
}
