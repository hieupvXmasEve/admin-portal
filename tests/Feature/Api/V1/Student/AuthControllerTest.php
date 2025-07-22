<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Student;

use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\CurriculumVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Student $student;
    protected Campus $campus;
    protected Program $program;
    protected CurriculumVersion $curriculumVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::factory()->create();
        $this->program = Program::factory()->create(['campus_id' => $this->campus->id]);
        $this->curriculumVersion = CurriculumVersion::factory()->create(['program_id' => $this->program->id]);
        
        $this->student = Student::factory()->create([
            'email' => 'test@student.edu',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'campus_id' => $this->campus->id,
            'program_id' => $this->program->id,
            'curriculum_version_id' => $this->curriculumVersion->id,
        ]);
    }

    public function test_student_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/student/auth/login', [
            'email' => 'test@student.edu',
            'password' => 'password123',
            'device_name' => 'Test Device',
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'student' => [
                            'id',
                            'student_id',
                            'full_name',
                            'email',
                            'status',
                            'campus',
                            'program',
                        ],
                        'token',
                        'token_type',
                        'expires_at',
                    ],
                    'timestamp',
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Login successful',
                ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->student->id,
            'tokenable_type' => Student::class,
        ]);
    }

    public function test_student_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/student/auth/login', [
            'email' => 'test@student.edu',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'error_code' => 'AUTHENTICATION_ERROR',
                ]);
    }

    public function test_inactive_student_cannot_login(): void
    {
        $this->student->update(['status' => 'inactive']);

        $response = $this->postJson('/api/v1/student/auth/login', [
            'email' => 'test@student.edu',
            'password' => 'password123',
        ]);

        $response->assertForbidden()
                ->assertJson([
                    'success' => false,
                    'error_code' => 'AUTHORIZATION_ERROR',
                ]);
    }

    public function test_login_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/student/auth/login', [
            'email' => 'invalid-email',
            'password' => '123', // Too short
        ]);

        $response->assertUnprocessableEntity()
                ->assertJson([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                ])
                ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_student_can_get_profile(): void
    {
        $token = $this->student->createToken('Test Token', ['student'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/student/auth/me');

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'student' => [
                            'id',
                            'student_id',
                            'full_name',
                            'email',
                            'campus',
                            'program',
                            'curriculum_version',
                        ],
                    ],
                ]);
    }

    public function test_student_can_logout(): void
    {
        $token = $this->student->createToken('Test Token', ['student'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/student/auth/logout');

        $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully',
                ]);

        // Token should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->student->id,
            'tokenable_type' => Student::class,
        ]);
    }

    public function test_student_can_refresh_token(): void
    {
        $token = $this->student->createToken('Test Token', ['student'])->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/student/auth/refresh', [
            'device_name' => 'Refreshed Device',
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'token',
                        'token_type',
                        'expires_at',
                    ],
                ]);
    }

    public function test_unauthenticated_request_returns_error(): void
    {
        $response = $this->getJson('/api/v1/student/auth/me');

        $response->assertUnauthorized()
                ->assertJson([
                    'success' => false,
                    'error_code' => 'AUTHENTICATION_ERROR',
                ]);
    }
}
