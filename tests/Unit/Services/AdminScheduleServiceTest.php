<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Room;
use App\Models\Semester;
use App\Services\AdminScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminScheduleServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AdminScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminScheduleService();
    }

    public function test_can_retrieve_schedule_data_with_filters(): void
    {
        // Arrange
        $semester = Semester::factory()->create();
        $lecture = Lecture::factory()->create();
        $room = Room::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'lecture_id' => $lecture->id,
        ]);
        
        ClassSession::factory()->count(3)->create([
            'course_offering_id' => $courseOffering->id,
            'lecture_id' => $lecture->id,
            'room_id' => $room->id,
            'session_date' => Carbon::today()->addDays(1),
        ]);

        $filters = [
            'semester_id' => $semester->id,
            'lecturer_id' => $lecture->id,
            'room_id' => $room->id,
        ];

        // Act
        $result = $this->service->getScheduleData($filters);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);
        $this->assertEquals($courseOffering->id, $result->first()->course_offering_id);
    }

    public function test_can_retrieve_schedule_data_with_date_range_filter(): void
    {
        // Arrange
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addWeek();
        
        ClassSession::factory()->create([
            'session_date' => $startDate->copy()->addDays(2),
        ]);
        ClassSession::factory()->create([
            'session_date' => $startDate->copy()->addDays(10), // Outside range
        ]);

        $filters = [
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ];

        // Act
        $result = $this->service->getScheduleData($filters);

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_can_update_session_details(): void
    {
        // Arrange
        $session = ClassSession::factory()->create([
            'session_date' => Carbon::today()->addDays(1),
            'start_time' => Carbon::createFromTime(9, 0),
            'end_time' => Carbon::createFromTime(11, 0),
        ]);
        
        $room = Room::factory()->create();
        
        $updateData = [
            'session_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'room_id' => $room->id,
        ];

        // Act
        $updatedSession = $this->service->updateSession($session, $updateData);

        // Assert
        $this->assertEquals($updateData['session_date'], $updatedSession->session_date->toDateString());
        $this->assertEquals('10:00:00', $updatedSession->start_time->format('H:i:s'));
        $this->assertEquals('12:00:00', $updatedSession->end_time->format('H:i:s'));
        $this->assertEquals($room->id, $updatedSession->room_id);
    }

    public function test_detects_room_conflicts(): void
    {
        // Arrange
        $room = Room::factory()->create();
        $existingSession = ClassSession::factory()->create([
            'room_id' => $room->id,
            'session_date' => Carbon::today()->addDays(1),
            'start_time' => Carbon::createFromTime(9, 0),
            'end_time' => Carbon::createFromTime(11, 0),
        ]);
        
        $newSession = ClassSession::factory()->create([
            'room_id' => Room::factory()->create()->id,
        ]);
        
        $conflictingData = [
            'session_date' => Carbon::today()->addDays(1)->toDateString(),
            'start_time' => '09:30',
            'end_time' => '11:30',
            'room_id' => $room->id,
        ];

        // Act
        $conflicts = $this->service->validateScheduleConflicts($newSession, $conflictingData);

        // Assert
        $this->assertNotEmpty($conflicts);
        $this->assertArrayHasKey('room', $conflicts);
        $this->assertStringContainsString('Room is already booked', $conflicts['room']);
    }

    public function test_detects_lecturer_conflicts(): void
    {
        // Arrange
        $lecture = Lecture::factory()->create();
        $existingSession = ClassSession::factory()->create([
            'lecture_id' => $lecture->id,
            'session_date' => Carbon::today()->addDays(1),
            'start_time' => Carbon::createFromTime(9, 0),
            'end_time' => Carbon::createFromTime(11, 0),
        ]);
        
        $newSession = ClassSession::factory()->create([
            'lecture_id' => $lecture->id,
        ]);
        
        $conflictingData = [
            'session_date' => Carbon::today()->addDays(1)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
        ];

        // Act
        $conflicts = $this->service->validateScheduleConflicts($newSession, $conflictingData);

        // Assert
        $this->assertNotEmpty($conflicts);
        $this->assertArrayHasKey('lecturer', $conflicts);
        $this->assertStringContainsString('Lecturer has another session', $conflicts['lecturer']);
    }

    public function test_validates_session_time_constraints(): void
    {
        // Arrange
        $session = ClassSession::factory()->create();
        
        $invalidData = [
            'session_date' => Carbon::today()->addDays(1)->toDateString(),
            'start_time' => '11:00',
            'end_time' => '09:00', // End time before start time
        ];

        // Act
        $conflicts = $this->service->validateScheduleConflicts($session, $invalidData);

        // Assert
        $this->assertNotEmpty($conflicts);
        $this->assertArrayHasKey('time', $conflicts);
        $this->assertStringContainsString('End time must be after start time', $conflicts['time']);
    }

    public function test_returns_empty_conflicts_for_valid_update(): void
    {
        // Arrange
        $session = ClassSession::factory()->create([
            'session_date' => Carbon::today()->addDays(1),
            'start_time' => Carbon::createFromTime(9, 0),
            'end_time' => Carbon::createFromTime(11, 0),
        ]);
        
        $room = Room::factory()->create();
        
        $validData = [
            'session_date' => Carbon::today()->addDays(2)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'room_id' => $room->id,
        ];

        // Act
        $conflicts = $this->service->validateScheduleConflicts($session, $validData);

        // Assert
        $this->assertEmpty($conflicts);
    }

    public function test_formats_schedule_data_for_grid_display(): void
    {
        // Arrange
        $courseOffering = CourseOffering::factory()->create();
        $lecture = Lecture::factory()->create(['name' => 'Dr. Smith']);
        $room = Room::factory()->create(['name' => 'Room A101']);
        
        $session = ClassSession::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'lecture_id' => $lecture->id,
            'room_id' => $room->id,
            'session_title' => 'Introduction to Programming',
            'session_date' => Carbon::today()->addDays(1),
            'start_time' => Carbon::createFromTime(9, 0),
            'end_time' => Carbon::createFromTime(11, 0),
        ]);

        // Act
        $result = $this->service->getScheduleData([]);
        $formattedSession = $result->first();

        // Assert
        $this->assertObjectHasProperty('id', $formattedSession);
        $this->assertObjectHasProperty('title', $formattedSession);
        $this->assertObjectHasProperty('lecturer', $formattedSession);
        $this->assertObjectHasProperty('room', $formattedSession);
        $this->assertObjectHasProperty('startTime', $formattedSession);
        $this->assertObjectHasProperty('endTime', $formattedSession);
        $this->assertObjectHasProperty('date', $formattedSession);
        $this->assertEquals('Dr. Smith', $formattedSession->lecturer);
        $this->assertEquals('Room A101', $formattedSession->room);
    }
}