<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\Campus;
use App\Models\Lecture;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardNoSemesterTest extends TestCase
{
    use RefreshDatabase;

    protected Lecture $lecturer;
    protected Campus $campus;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required models
        $this->campus = Campus::factory()->create();
        $this->lecturer = Lecture::factory()->create(['campus_id' => $this->campus->id]);
        
        // Disable middleware for tests
        $this->withoutMiddleware([
            'lecturer.api.auth',
            'lecturer.api.rate',
        ]);
    }

    /**
     * Test dashboard returns empty data when no active semester exists
     */
    public function test_dashboard_returns_empty_data_when_no_active_semester(): void
    {
        // Ensure no semesters are active
        Semester::query()->update(['is_active' => false]);

        Sanctum::actingAs($this->lecturer);

        $response = $this->getJson('/api/v1/lecturer/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No active semester found. Dashboard showing empty data.',
                'data' => [
                    'semester' => null,
                    'teaching_summary' => [
                        'total_courses' => 0,
                        'total_students' => 0,
                        'total_sessions' => 0,
                        'completed_sessions' => 0,
                        'pending_sessions' => 0,
                        'average_class_size' => 0,
                        'courses' => [],
                    ],
                    'attendance_overview' => [
                        'total_sessions' => 0,
                        'sessions_with_attendance' => 0,
                        'pending_attendance_marking' => 0,
                        'average_attendance_rate' => 0,
                        'sessions_requiring_attention' => [],
                        'attendance_trends' => [
                            'weekly_average' => 0,
                            'trend' => 'stable',
                            'last_week_change' => 0,
                        ],
                    ],
                    'student_alerts' => [
                        'total_alerts' => 0,
                        'low_attendance_students' => [],
                        'recently_absent_students' => [],
                        'critical_alerts' => [],
                    ],
                    'upcoming_sessions' => [],
                    'recent_activities' => [],
                ],
            ]);
    }

    /**
     * Test teaching summary returns empty data when no semester provided
     */
    public function test_teaching_summary_returns_empty_data_when_no_semester(): void
    {
        // Ensure no semesters are active
        Semester::query()->update(['is_active' => false]);

        Sanctum::actingAs($this->lecturer);

        $response = $this->getJson('/api/v1/lecturer/dashboard/teaching-summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No active semester found. Showing empty teaching summary.',
                'data' => [
                    'total_courses' => 0,
                    'total_students' => 0,
                    'total_sessions' => 0,
                    'completed_sessions' => 0,
                    'pending_sessions' => 0,
                    'average_class_size' => 0,
                    'courses' => [],
                ],
            ]);
    }

    /**
     * Test attendance overview returns empty data when no semester provided
     */
    public function test_attendance_overview_returns_empty_data_when_no_semester(): void
    {
        // Ensure no semesters are active
        Semester::query()->update(['is_active' => false]);

        Sanctum::actingAs($this->lecturer);

        $response = $this->getJson('/api/v1/lecturer/dashboard/attendance-overview');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No active semester found. Showing empty attendance overview.',
                'data' => [
                    'total_sessions' => 0,
                    'sessions_with_attendance' => 0,
                    'pending_attendance_marking' => 0,
                    'average_attendance_rate' => 0,
                    'sessions_requiring_attention' => [],
                    'attendance_trends' => [
                        'weekly_average' => 0,
                        'trend' => 'stable',
                        'last_week_change' => 0,
                    ],
                ],
            ]);
    }

    /**
     * Test student alerts returns empty data when no semester provided
     */
    public function test_student_alerts_returns_empty_data_when_no_semester(): void
    {
        // Ensure no semesters are active
        Semester::query()->update(['is_active' => false]);

        Sanctum::actingAs($this->lecturer);

        $response = $this->getJson('/api/v1/lecturer/dashboard/student-alerts');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'No active semester found. Showing empty student alerts.',
                'data' => [
                    'total_alerts' => 0,
                    'low_attendance_students' => [],
                    'recently_absent_students' => [],
                    'critical_alerts' => [],
                ],
            ]);
    }

    /**
     * Test dashboard works normally when active semester exists
     */
    public function test_dashboard_works_normally_with_active_semester(): void
    {
        // Create an active semester
        Semester::factory()->create(['is_active' => true]);

        Sanctum::actingAs($this->lecturer);

        $response = $this->getJson('/api/v1/lecturer/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
            ]);

        // Ensure semester data is present
        $responseData = $response->json('data');
        $this->assertNotNull($responseData['semester']);
        $this->assertIsArray($responseData['teaching_summary']);
        $this->assertIsArray($responseData['attendance_overview']);
        $this->assertIsArray($responseData['student_alerts']);
        $this->assertIsArray($responseData['upcoming_sessions']);
        $this->assertIsArray($responseData['recent_activities']);
    }
}