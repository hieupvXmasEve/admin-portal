<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Student;

use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\CurriculumVersion;
use Google\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test campus, program, and curriculum version
        $campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $campus->id]);
        $curriculumVersion = CurriculumVersion::factory()->create(['program_id' => $program->id]);
        
        // Create test student
        $this->student = Student::factory()->create([
            'email' => 'student@example.com',
            'status' => 'active',
            'campus_id' => $campus->id,
            'program_id' => $program->id,
            'curriculum_version_id' => $curriculumVersion->id,
        ]);
    }

    public function test_google_login_with_valid_id_token()
    {
        // Mock Google Client
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('verifyIdToken')
            ->once()
            ->with('valid_id_token')
            ->andReturn([
                'sub' => 'google_user_id',
                'email' => 'student@example.com',
                'name' => 'Test Student',
                'picture' => 'https://example.com/picture.jpg',
                'email_verified' => true,
            ]);

        // Replace the Google Client in the container
        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $response = $this->postJson('/api/v1/student/auth/google', [
            'id_token' => 'valid_id_token',
            'device_name' => 'Test Device',
            'remember_me' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'student' => [
                        'id',
                        'student_id',
                        'full_name',
                        'email',
                        'status',
                        'campus',
                        'program',
                        'avatar_url',
                    ],
                    'token',
                    'token_type',
                    'expires_at',
                ],
                'message',
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Google login successful',
            ]);

        // Verify student OAuth data was updated
        $this->student->refresh();
        $this->assertEquals('google', $this->student->oauth_provider);
        $this->assertEquals('google_user_id', $this->student->oauth_provider_id);
        $this->assertNotNull($this->student->last_login_at);
    }

    public function test_google_login_with_invalid_id_token()
    {
        // Mock Google Client to return null (invalid token)
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('verifyIdToken')
            ->once()
            ->with('invalid_id_token')
            ->andReturn(null);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $response = $this->postJson('/api/v1/student/auth/google', [
            'id_token' => 'invalid_id_token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid Google ID token',
            ]);
    }

    public function test_google_login_with_unverified_email()
    {
        // Mock Google Client with unverified email
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn([
                'sub' => 'google_user_id',
                'email' => 'student@example.com',
                'email_verified' => false,
            ]);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $response = $this->postJson('/api/v1/student/auth/google', [
            'id_token' => 'valid_token_unverified_email',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Google account email is not verified',
            ]);
    }

    public function test_google_login_with_nonexistent_student()
    {
        // Mock Google Client
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn([
                'sub' => 'google_user_id',
                'email' => 'nonexistent@example.com',
                'email_verified' => true,
            ]);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $response = $this->postJson('/api/v1/student/auth/google', [
            'id_token' => 'valid_token_nonexistent',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'No student account found with this email address',
            ]);
    }

    public function test_google_login_with_inactive_student()
    {
        // Update student status to inactive
        $this->student->update(['status' => 'inactive']);

        // Mock Google Client
        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn([
                'sub' => 'google_user_id',
                'email' => 'student@example.com',
                'email_verified' => true,
            ]);

        $this->app->bind(Client::class, function () use ($mockClient) {
            return $mockClient;
        });

        $response = $this->postJson('/api/v1/student/auth/google', [
            'id_token' => 'valid_token_inactive',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Student account is not active. Please contact administration.',
            ]);
    }

    public function test_google_login_validation_errors()
    {
        $response = $this->postJson('/api/v1/student/auth/google', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_token']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
