<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Student;

use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\CurriculumVersion;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\AcademicHold;
use App\Models\GpaCalculation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Student $student;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $campus->id]);
        $curriculumVersion = CurriculumVersion::factory()->create(['program_id' => $program->id]);
        
        $this->student = Student::factory()->create([
            'status' => 'active',
            'campus_id' => $campus->id,
            'program_id' => $program->id,
            'curriculum_version_id' => $curriculumVersion->id,
        ]);

        $this->token = $this->student->createToken('Test Token', ['student'])->plainTextToken;
    }

    public function test_authenticated_student_can_access_dashboard(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'current_semester',
                        'gpa_data',
                        'credit_progress',
                        'academic_holds',
                        'upcoming_assessments',
                        'enrollment_status',
                        'quick_stats',
                        'last_updated',
                    ],
                    'timestamp',
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Dashboard data retrieved successfully',
                ]);
    }

    public function test_unauthenticated_request_to_dashboard_fails(): void
    {
        $response = $this->getJson('/api/v1/student/dashboard');

        $response->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'error_code' => 'AUTHENTICATION_ERROR',
                ]);
    }

    public function test_dashboard_includes_current_semester_data(): void
    {
        $semester = Semester::factory()->create([
            'is_active' => true,
            'name' => 'Spring 2024',
            'code' => 'SP24',
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'semester_id' => $semester->id,
            'status' => 'in_progress',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard');

        $response->assertOk()
                ->assertJsonPath('data.current_semester.semester.name', 'Spring 2024')
                ->assertJsonPath('data.current_semester.semester.code', 'SP24')
                ->assertJsonPath('data.current_semester.enrollment.status', 'in_progress');
    }

    public function test_dashboard_includes_academic_holds(): void
    {
        AcademicHold::factory()->create([
            'student_id' => $this->student->id,
            'status' => 'active',
            'hold_type' => 'financial',
            'title' => 'Outstanding Tuition',
        ]);

        AcademicHold::factory()->create([
            'student_id' => $this->student->id,
            'status' => 'active',
            'hold_type' => 'academic',
            'title' => 'Missing Documents',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard');

        $response->assertOk()
                ->assertJsonPath('data.academic_holds.summary.total_holds', 2)
                ->assertJsonCount(2, 'data.academic_holds.holds');
    }

    public function test_student_can_access_gpa_endpoint(): void
    {
        GpaCalculation::factory()->create([
            'student_id' => $this->student->id,
            'gpa' => 3.5,
            'quality_points' => 105,
            'credit_hours_attempted' => 30,
            'credit_hours_earned' => 30,
            'academic_standing' => 'good',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard/gpa');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'current_gpa',
                        'gpa_trend',
                        'grade_distribution',
                        'academic_standing',
                        'calculated_at',
                    ],
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'GPA data retrieved successfully',
                ]);
    }

    public function test_student_can_access_credit_progress_endpoint(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard/credit-progress');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'overall',
                        'by_category',
                        'semester_breakdown',
                        'remaining_requirements',
                        'graduation_readiness',
                    ],
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Credit progress retrieved successfully',
                ]);
    }

    public function test_student_can_access_academic_holds_endpoint(): void
    {
        AcademicHold::factory()->create([
            'student_id' => $this->student->id,
            'status' => 'active',
            'hold_type' => 'financial',
            'title' => 'Outstanding Balance',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard/academic-holds');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'total_holds',
                        'blocking_registration',
                        'blocking_graduation',
                        'holds',
                    ],
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Academic holds retrieved successfully',
                ]);
    }

    public function test_student_can_access_upcoming_assessments_endpoint(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard/upcoming-assessments');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'assessments',
                    ],
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Upcoming assessments retrieved successfully',
                ]);
    }

    public function test_inactive_student_cannot_access_dashboard(): void
    {
        $this->student->update(['status' => 'inactive']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard');

        $response->assertForbidden()
                ->assertJson([
                    'success' => false,
                    'error_code' => 'AUTHORIZATION_ERROR',
                ]);
    }

    public function test_dashboard_response_includes_proper_timestamps(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard');

        $response->assertOk()
                ->assertJsonStructure([
                    'data' => [
                        'last_updated',
                    ],
                    'timestamp',
                ]);

        $data = $response->json();
        $this->assertNotNull($data['data']['last_updated']);
        $this->assertNotNull($data['timestamp']);
    }

    public function test_dashboard_handles_no_active_semester_gracefully(): void
    {
        // Ensure no active semester exists
        Semester::query()->update(['is_active' => false]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/student/dashboard');

        $response->assertOk()
                ->assertJsonPath('data.current_semester.semester', null)
                ->assertJsonPath('data.current_semester.enrollment', null)
                ->assertJsonPath('data.current_semester.course_summary.registered_courses', 0);
    }
}
