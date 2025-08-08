<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Student;

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Lecturer;
use App\Models\Program;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Student $student;

    protected CourseOffering $courseOffering;

    protected Semester $semester;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTestData();
        $this->token = $this->student->createToken('Test Token', ['student'])->plainTextToken;
    }

    protected function createTestData(): void
    {
        $campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $campus->id]);
        $curriculumVersion = CurriculumVersion::factory()->create(['program_id' => $program->id]);

        $this->student = Student::factory()->create([
            'status' => 'active',
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

        $lecturer = Lecturer::factory()->create();
        $room = Room::factory()->create();

        $this->courseOffering = CourseOffering::factory()->create([
            'semester_id' => $this->semester->id,
            'curriculum_unit_id' => $curriculumUnit->id,
            'lecturer_id' => $lecturer->id,
            'is_active' => true,
            'max_enrollment' => 30,
        ]);

        // Create class sessions
        ClassSession::factory()->create([
            'course_offering_id' => $this->courseOffering->id,
            'room_id' => $room->id,
            'day_of_week' => 'monday',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);
    }

    public function test_student_can_get_available_courses(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/course-registration/available-courses');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'unit' => [
                            'code',
                            'name',
                            'description',
                            'credit_hours',
                        ],
                        'lecturer' => [
                            'id',
                            'name',
                            'email',
                        ],
                        'schedule',
                        'enrollment',
                        'registration_eligibility',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Available courses retrieved successfully',
            ]);
    }

    public function test_student_can_filter_available_courses_by_unit_code(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/course-registration/available-courses?unit_code=TEST');

        $response->assertOk();
    }

    public function test_student_can_register_for_course(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/student/course-registration/register', [
            'course_offering_id' => $this->courseOffering->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'status',
                    'registration_date',
                    'credit_hours',
                    'unit',
                    'lecturer',
                    'schedule',
                    'semester',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Course registration successful',
            ]);

        $this->assertDatabaseHas('course_registrations', [
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'registration_status' => 'registered',
        ]);
    }

    public function test_student_cannot_register_for_invalid_course(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/student/course-registration/register', [
            'course_offering_id' => 99999,
        ]);

        $response->assertUnprocessableEntity()
            ->assertJson([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_student_cannot_register_twice_for_same_course(): void
    {
        // First registration
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'registered',
        ]);

        // Attempt second registration
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/student/course-registration/register', [
            'course_offering_id' => $this->courseOffering->id,
        ]);

        $response->assertUnprocessableEntity()
            ->assertJson([
                'success' => false,
                'error_code' => 'BUSINESS_LOGIC_ERROR',
            ]);
    }

    public function test_student_can_drop_course(): void
    {
        $registration = CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'registered',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson("/api/v1/student/course-registration/drop/{$registration->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Course dropped successfully',
            ]);

        $registration->refresh();
        $this->assertEquals('dropped', $registration->registration_status);
        $this->assertNotNull($registration->drop_date);
    }

    public function test_student_cannot_drop_other_students_registration(): void
    {
        $otherStudent = Student::factory()->create();
        $registration = CourseRegistration::factory()->create([
            'student_id' => $otherStudent->id,
            'course_offering_id' => $this->courseOffering->id,
            'registration_status' => 'registered',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->deleteJson("/api/v1/student/course-registration/drop/{$registration->id}");

        $response->assertUnprocessableEntity()
            ->assertJson([
                'success' => false,
                'error_code' => 'BUSINESS_LOGIC_ERROR',
            ]);
    }

    public function test_student_can_get_their_registrations(): void
    {
        CourseRegistration::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'semester_id' => $this->semester->id,
            'registration_status' => 'registered',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/course-registration/my-registrations');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'unit',
                        'lecturer',
                        'schedule',
                        'semester',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Student registrations retrieved successfully',
            ]);
    }

    public function test_student_can_validate_registration(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/student/course-registration/validate-registration', [
            'course_offering_id' => $this->courseOffering->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'can_register',
                    'validation_passed',
                ],
            ]);
    }

    public function test_student_can_check_schedule_conflicts(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson("/api/v1/student/course-registration/schedule-conflicts/{$this->courseOffering->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'has_conflicts',
                    'conflict_count',
                    'conflicts',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Schedule conflicts checked successfully',
            ]);
    }

    public function test_unauthenticated_request_fails(): void
    {
        $response = $this->getJson('/api/v1/student/course-registration/available-courses');

        $response->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'error_code' => 'AUTHENTICATION_ERROR',
            ]);
    }

    public function test_inactive_student_cannot_access_registration(): void
    {
        $this->student->update(['status' => 'inactive']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/course-registration/available-courses');

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'error_code' => 'AUTHORIZATION_ERROR',
            ]);
    }
}
