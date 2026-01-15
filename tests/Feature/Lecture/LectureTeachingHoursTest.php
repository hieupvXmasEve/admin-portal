<?php

declare(strict_types=1);

namespace Tests\Feature\Lecture;

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LectureTeachingHoursTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Lecture $lecture;
    protected Campus $campus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::factory()->create();

        // Create a user who can view lecturers (assuming admin or similar permission)
        // Adjust permissions as necessary based on project configuration
        $this->user = User::factory()->create([
            'type' => UserType::ADMIN,
            'email' => 'admin@example.com',
        ]);

        // Mock permission check if using Spatie/Gates
        // For now assuming the user type check or basic auth passes.
        // If specific permission is needed: $this->user->givePermissionTo('view_lecturer');

        // Create a lecture linked to a user
        $lectureUser = User::factory()->create([
            'type' => UserType::LECTURER,
        ]);

        $this->lecture = Lecture::factory()->create([
            'user_id' => $lectureUser->id,
            'campus_id' => $this->campus->id,
        ]);

        $this->actingAs($this->user);
        session(['current_campus_id' => $this->campus->id]);
    }

    public function test_can_view_teaching_hours_detail_page()
    {
        $response = $this->get(route('lectures.teaching-hours.show', $this->lecture->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('lectures/TeachingHoursDetail')
            ->has('lecture')
            ->has('sessions')
            ->has('stats')
        );
    }

    public function test_can_filter_by_single_unit_type()
    {
        // Create units with different types
        $unitEgc = Unit::factory()->create(['unit_type' => 'egc', 'code' => 'EGC101']);
        $unitCs = Unit::factory()->create(['unit_type' => 'cs', 'code' => 'CS101']);

        $semester = Semester::factory()->create();

        // Create course offerings
        $offeringEgc = CourseOffering::factory()->create([
            'unit_id' => $unitEgc->id,
            'semester_id' => $semester->id,
            'campus_id' => $this->campus->id,
        ]);

        $offeringCs = CourseOffering::factory()->create([
            'unit_id' => $unitCs->id,
            'semester_id' => $semester->id,
            'campus_id' => $this->campus->id,
        ]);

        // Create sessions
        ClassSession::factory()->create([
            'lecture_id' => $this->lecture->id,
            'course_offering_id' => $offeringEgc->id,
            'session_date' => '2025-01-10',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);

        ClassSession::factory()->create([
            'lecture_id' => $this->lecture->id,
            'course_offering_id' => $offeringCs->id,
            'session_date' => '2025-01-11',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);

        // Filter by 'egc'
        $response = $this->get(route('lectures.teaching-hours.show', [
            'lecture' => $this->lecture->id,
            'unit_type' => 'egc',
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31',
        ]));

        $response->assertOk();

        // Assert that we only get the EGC session
        $response->assertInertia(fn ($page) => $page
            ->where('sessions.data.0.unit_type', 'egc')
            ->has('sessions.data', 1)
        );

        // Filter by 'cs'
        $responseCs = $this->get(route('lectures.teaching-hours.show', [
            'lecture' => $this->lecture->id,
            'unit_type' => 'cs',
            'date_from' => '2025-01-01',
            'date_to' => '2025-01-31',
        ]));

        $responseCs->assertOk();
        $responseCs->assertInertia(fn ($page) => $page
            ->where('sessions.data.0.unit_type', 'cs')
            ->has('sessions.data', 1)
        );
    }

    public function test_can_filter_with_future_dates()
    {
        $unit = Unit::factory()->create(['unit_type' => 'general']);
        $semester = Semester::factory()->create();
        $offering = CourseOffering::factory()->create([
            'unit_id' => $unit->id,
            'semester_id' => $semester->id,
            'campus_id' => $this->campus->id,
        ]);

        // Create a future session
        $futureDate = '2026-05-15';
        ClassSession::factory()->create([
            'lecture_id' => $this->lecture->id,
            'course_offering_id' => $offering->id,
            'session_date' => $futureDate,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);

        // Request including the future date
        $response = $this->get(route('lectures.teaching-hours.show', [
            'lecture' => $this->lecture->id,
            'date_from' => '2025-01-01',
            'date_to' => '2026-06-01', // Future date
        ]));

        // Should NOT be 302
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('sessions.data', 1)
            ->where('sessions.data.0.session_date', $futureDate)
        );
    }
}
