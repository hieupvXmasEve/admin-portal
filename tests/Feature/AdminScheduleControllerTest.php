<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminScheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_retrieve_schedule_data(): void
    {
        // Arrange
        $lecture = Lecture::factory()->create();
        $room = Room::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'lecture_id' => $lecture->id,
        ]);

        ClassSession::factory()->count(3)->create([
            'course_offering_id' => $courseOffering->id,
            'lecture_id' => $lecture->id,
            'room_id' => $room->id,
            'session_date' => Carbon::today()->addDays(1),
        ]);

        // Act
        $response = $this->getJson('/api/admin/schedules');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'unitCode',
                        'section',
                        'lecturer',
                        'room',
                        'startTime',
                        'endTime',
                        'date',
                        'campusName',
                        'status',
                    ],
                ],
                'count',
            ])
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_retrieve_schedule_data_with_filters(): void
    {
        // Arrange
        $lecture1 = Lecture::factory()->create();
        $lecture2 = Lecture::factory()->create();

        ClassSession::factory()->create([
            'lecture_id' => $lecture1->id,
            'session_date' => Carbon::today()->addDays(1),
        ]);

        ClassSession::factory()->create([
            'lecture_id' => $lecture2->id,
            'session_date' => Carbon::today()->addDays(1),
        ]);

        // Act
        $response = $this->getJson("/api/admin/schedules?lecturer_id={$lecture1->id}");

        // Assert
        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data');
    }

    public function test_can_show_session_details(): void
    {
        // Arrange
        $session = ClassSession::factory()->create();

        // Act
        $response = $this->getJson("/api/admin/schedules/{$session->id}");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'unitCode',
                'lecturer' => [
                    'id',
                    'name',
                    'email',
                ],
                'room' => [
                    'id',
                    'name',
                    'code',
                    'building',
                    'fullCode',
                ],
                'schedule' => [
                    'date',
                    'startTime',
                    'endTime',
                    'duration',
                ],
                'details' => [
                    'sessionType',
                    'deliveryMode',
                    'status',
                ],
            ]);
    }

    public function test_can_update_session_successfully(): void
    {
        // Arrange
        $session = ClassSession::factory()->create([
            'session_date' => Carbon::today()->addDays(1),
            'start_time' => Carbon::createFromTime(9, 0),
            'end_time' => Carbon::createFromTime(11, 0),
        ]);

        $newRoom = Room::factory()->create();

        $updateData = [
            'session_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'room_id' => $newRoom->id,
        ];

        // Act
        $response = $this->patchJson("/api/admin/schedules/{$session->id}", $updateData);

        // Assert
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Session updated successfully',
            ]);

        $this->assertDatabaseHas('class_sessions', [
            'id' => $session->id,
            'session_date' => $updateData['session_date'],
            'room_id' => $newRoom->id,
        ]);
    }

    public function test_update_session_validates_required_fields(): void
    {
        // Arrange
        $session = ClassSession::factory()->create();

        // Act
        $response = $this->patchJson("/api/admin/schedules/{$session->id}", []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'session_date',
                'start_time',
                'end_time',
                'room_id',
            ]);
    }

    public function test_update_session_validates_time_format(): void
    {
        // Arrange
        $session = ClassSession::factory()->create();
        $room = Room::factory()->create();

        $invalidData = [
            'session_date' => Carbon::today()->addDays(1)->toDateString(),
            'start_time' => '25:00', // Invalid hour
            'end_time' => '9:70', // Invalid minute
            'room_id' => $room->id,
        ];

        // Act
        $response = $this->patchJson("/api/admin/schedules/{$session->id}", $invalidData);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'start_time',
                'end_time',
            ]);
    }

    public function test_update_session_validates_end_time_after_start_time(): void
    {
        // Arrange
        $session = ClassSession::factory()->create();
        $room = Room::factory()->create();

        $invalidData = [
            'session_date' => Carbon::today()->addDays(1)->toDateString(),
            'start_time' => '11:00',
            'end_time' => '09:00', // End time before start time
            'room_id' => $room->id,
        ];

        // Act
        $response = $this->patchJson("/api/admin/schedules/{$session->id}", $invalidData);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_update_session_detects_room_conflicts(): void
    {
        // Arrange
        $room = Room::factory()->create();

        // Existing session
        ClassSession::factory()->create([
            'room_id' => $room->id,
            'session_date' => Carbon::today()->addDays(1),
            'start_time' => Carbon::createFromTime(9, 0),
            'end_time' => Carbon::createFromTime(11, 0),
        ]);

        // Session to update
        $sessionToUpdate = ClassSession::factory()->create([
            'room_id' => Room::factory()->create()->id,
            'session_date' => Carbon::today()->addDays(2),
        ]);

        $conflictingData = [
            'session_date' => Carbon::today()->addDays(1)->toDateString(),
            'start_time' => '09:30', // Overlaps with existing session
            'end_time' => '11:30',
            'room_id' => $room->id,
        ];

        // Act
        $response = $this->patchJson("/api/admin/schedules/{$sessionToUpdate->id}", $conflictingData);

        // Assert
        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Schedule conflict detected',
            ]);
    }

    public function test_can_get_filter_options(): void
    {
        // Arrange
        Lecture::factory()->count(3)->create();
        Room::factory()->count(3)->create();

        // Act
        $response = $this->getJson('/api/admin/schedules/filter-options');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'semesters',
                    'lecturers' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                        ],
                    ],
                    'rooms' => [
                        '*' => [
                            'id',
                            'name',
                            'code',
                            'building',
                            'campus_id',
                        ],
                    ],
                ],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_returns_404_for_non_existent_session(): void
    {
        // Act
        $response = $this->getJson('/api/admin/schedules/999999');

        // Assert
        $response->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        // Arrange
        $this->app['auth']->forgetGuards();

        // Act
        $response = $this->getJson('/api/admin/schedules');

        // Assert
        $response->assertUnauthorized();
    }
}
