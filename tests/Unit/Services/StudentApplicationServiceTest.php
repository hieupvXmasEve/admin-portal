<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Services\StudentApplicationService;
use App\Services\StudentCodeGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class StudentApplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StudentApplicationService $service;

    protected StudentCodeGenerationService $codeGenerationService;

    protected Campus $campus;

    protected Program $program;

    protected Semester $semester;

    protected Specialization $specialization;

    protected CurriculumVersion $curriculumVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StudentApplicationService::class);
        $this->codeGenerationService = app(StudentCodeGenerationService::class);

        // Create test campus
        $this->campus = Campus::factory()->create([
            'code' => 'SAI',
            'name' => 'Saigon Campus',
        ]);

        // Mock the campus context
        App::instance('campus', $this->campus);

        // Create test program
        $this->program = Program::factory()->create([
            'code' => 'IT',
            'name' => 'Information Technology',
        ]);

        // Create test semester
        $this->semester = Semester::factory()->create();

        // Create test specialization
        $this->specialization = Specialization::factory()->create([
            'program_id' => $this->program->id,
            'name' => 'Software Engineering',
        ]);

        // Create test curriculum version
        $this->curriculumVersion = CurriculumVersion::factory()->create([
            'program_id' => $this->program->id,
            'semester_id' => $this->semester->id,
        ]);
    }

    /** @test */
    public function it_successfully_converts_a_valid_student_application_to_student(): void
    {
        $application = StudentApplication::factory()->create([
            'full_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '1234567890',
            'gender' => 'male',
            'ethnicity' => 'asian',
            'birth_day' => 15,
            'birth_month' => 6,
            'birth_year' => 2000,
            'national_id' => '123456789',
            'address' => '123 Test Street',
            'campus_code' => $this->campus->code,
            'intended_program' => $this->program->code,
            'intended_specialization' => $this->specialization->name,
            'parent_phone' => '0987654321',
            'parent_email' => 'parent@example.com',
            'status' => 'pending',
            'student_id' => null,
            'student_code' => 'SAI20250001',
        ]);

        $result = $this->service->convertSingleApplication($application->id, [
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'specialization_id' => $this->specialization->id,
            'admission_date' => now()->toDateString(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Student::class, $result['student']);
        $this->assertInstanceOf(StudentApplication::class, $result['application']);

        // Verify student was created with correct data
        $student = $result['student'];
        $this->assertEquals('John Doe', $student->full_name);
        $this->assertEquals('john.doe@example.com', $student->email);
        $this->assertEquals('1234567890', $student->phone);
        $this->assertEquals('male', $student->gender);
        $this->assertEquals('2000-06-15', $student->date_of_birth->format('Y-m-d'));
        $this->assertEquals('123456789', $student->national_id);
        $this->assertEquals('123 Test Street', $student->address);
        $this->assertEquals($this->campus->id, $student->campus_id);
        $this->assertEquals($this->program->id, $student->program_id);
        $this->assertEquals($this->specialization->id, $student->specialization_id);
        $this->assertEquals($this->curriculumVersion->id, $student->curriculum_version_id);
        $this->assertEquals('0987654321', $student->emergency_contact_phone);
        $this->assertEquals('active', $student->status);
        $this->assertEquals('SAI20250001', $student->student_id);

        // Verify application was updated
        $application->refresh();
        $this->assertEquals('approved', $application->status);
        $this->assertEquals($student->id, $application->student_id);
    }

    /** @test */
    public function it_fails_when_application_does_not_exist(): void
    {
        $result = $this->service->convertSingleApplication(99999, [
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Student application not found', $result['error']);
    }

    /** @test */
    public function it_fails_when_application_is_already_converted(): void
    {
        $existingStudent = Student::factory()->create(['campus_id' => $this->campus->id]);
        $application = StudentApplication::factory()->create([
            'campus_code' => $this->campus->code,
            'student_id' => $existingStudent->id,
            'status' => 'approved',
            'student_code' => 'SAI20250002',
        ]);

        $result = $this->service->convertSingleApplication($application->id, [
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Application has already been converted to a student', $result['error']);
    }

    /** @test */
    public function it_fails_when_campus_is_not_found_from_campus_code(): void
    {
        $application = StudentApplication::factory()->create([
            'campus_code' => 'INVALID',
            'student_id' => null,
            'student_code' => 'INV20250001',
        ]);

        $result = $this->service->convertSingleApplication($application->id, [
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Campus not found for code: INVALID', $result['error']);
    }

    /** @test */
    public function it_handles_date_conversion_from_birth_components_to_date_of_birth(): void
    {
        $application = StudentApplication::factory()->create([
            'full_name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'birth_day' => 25,
            'birth_month' => 12,
            'birth_year' => 1999,
            'campus_code' => $this->campus->code,
            'student_id' => null,
            'student_code' => 'SAI20250003',
        ]);

        $result = $this->service->convertSingleApplication($application->id, [
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'admission_date' => now()->toDateString(),
        ]);

        $this->assertTrue($result['success']);
        $student = $result['student'];
        $this->assertEquals('1999-12-25', $student->date_of_birth->format('Y-m-d'));
    }

    /** @test */
    public function it_successfully_converts_multiple_valid_applications(): void
    {
        $applications = StudentApplication::factory()->count(3)->create([
            'campus_code' => $this->campus->code,
            'student_id' => null,
            'status' => 'pending',
        ]);

        $applicationIds = $applications->pluck('id')->toArray();
        $conversionData = [
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'specialization_id' => $this->specialization->id,
            'admission_date' => now()->toDateString(),
        ];

        $result = $this->service->convertBatchApplications($applicationIds, $conversionData);

        $this->assertEquals(3, $result['success_count']);
        $this->assertEquals(0, $result['error_count']);
        $this->assertCount(3, $result['successful']);
        $this->assertCount(0, $result['failed']);

        // Verify all applications were converted
        foreach ($applications as $application) {
            $application->refresh();
            $this->assertEquals('approved', $application->status);
            $this->assertNotNull($application->student_id);
        }

        // Verify unique student codes were generated
        $studentCodes = collect($result['successful'])->pluck('student.student_code');
        $this->assertEquals(3, $studentCodes->unique()->count());
    }

    /** @test */
    public function it_handles_mixed_success_and_failure_scenarios_in_batch(): void
    {
        // Create valid applications
        $validApplications = StudentApplication::factory()->count(2)->create([
            'campus_code' => $this->campus->code,
            'student_id' => null,
            'status' => 'pending',
        ]);

        // Create invalid application (already converted)
        $existingStudent = Student::factory()->create(['campus_id' => $this->campus->id]);
        $invalidApplication = StudentApplication::factory()->create([
            'campus_code' => $this->campus->code,
            'student_id' => $existingStudent->id,
            'status' => 'approved',
        ]);

        $applicationIds = $validApplications->pluck('id')
            ->concat([$invalidApplication->id])
            ->toArray();

        $result = $this->service->convertBatchApplications($applicationIds, [
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'admission_date' => now()->toDateString(),
        ]);

        $this->assertEquals(2, $result['success_count']);
        $this->assertEquals(1, $result['error_count']);
        $this->assertCount(2, $result['successful']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals('Application has already been converted to a student', $result['failed'][0]['error']);
    }

    /** @test */
    public function it_correctly_maps_application_fields_to_student_fields(): void
    {
        $application = StudentApplication::factory()->create([
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'gender' => 'female',
            'ethnicity' => 'caucasian',
            'birth_day' => 10,
            'birth_month' => 3,
            'birth_year' => 1995,
            'national_id' => '987654321',
            'address' => '456 Test Ave',
            'parent_phone' => '0987654321',
            'parent_email' => 'parent@test.com',
            'campus_code' => $this->campus->code,
            'student_id' => null,
        ]);

        $mappedData = $this->service->mapApplicationToStudentData($application, [
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'admission_date' => '2025-01-15',
        ]);

        $this->assertEquals('Test User', $mappedData['full_name']);
        $this->assertEquals('test@example.com', $mappedData['email']);
        $this->assertEquals('1234567890', $mappedData['phone']);
        $this->assertEquals('female', $mappedData['gender']);
        $this->assertEquals('caucasian', $mappedData['nationality']);
        $this->assertEquals('1995-03-10', $mappedData['date_of_birth']->format('Y-m-d'));
        $this->assertEquals('987654321', $mappedData['national_id']);
        $this->assertEquals('456 Test Ave', $mappedData['address']);
        $this->assertEquals('0987654321', $mappedData['emergency_contact_phone']);
        $this->assertEquals($this->campus->id, $mappedData['campus_id']);
        $this->assertEquals($this->program->id, $mappedData['program_id']);
        $this->assertEquals($this->curriculumVersion->id, $mappedData['curriculum_version_id']);
        $this->assertEquals('2025-01-15', $mappedData['admission_date']);
        $this->assertEquals('active', $mappedData['status']);
    }

    /** @test */
    public function it_handles_missing_birth_date_components_gracefully(): void
    {
        $application = StudentApplication::factory()->create([
            'birth_day' => null,
            'birth_month' => null,
            'birth_year' => null,
            'campus_code' => $this->campus->code,
        ]);

        $mappedData = $this->service->mapApplicationToStudentData($application, [
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        $this->assertNull($mappedData['date_of_birth']);
    }

    /** @test */
    public function it_validates_required_relationships_exist(): void
    {
        $invalidProgramId = 99999;

        $result = $this->service->validateRequiredRelationships([
            'campus_id' => $this->campus->id,
            'program_id' => $invalidProgramId,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertContains("Program with ID {$invalidProgramId} not found", $result['errors']);
    }

    /** @test */
    public function it_generates_unique_student_codes_using_campus_code(): void
    {
        $application = StudentApplication::factory()->create([
            'campus_code' => $this->campus->code,
            'student_id' => null,
        ]);

        $result = $this->service->convertSingleApplication($application->id, [
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
            'admission_date' => now()->toDateString(),
        ]);

        $this->assertTrue($result['success']);
        $student = $result['student'];
        $this->assertStringStartsWith($this->campus->code, $student->student_code);
        $this->assertMatchesRegularExpression('/^'.$this->campus->code.'\d{8}$/', $student->student_code);
    }
}
