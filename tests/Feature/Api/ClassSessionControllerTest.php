<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\Lecture;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Syllabus;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected CourseOffering $courseOffering;

    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        // Create necessary dependencies
        $campus = Campus::factory()->create();
        $semester = Semester::factory()->create();
        $lecture = Lecture::factory()->create(['campus_id' => $campus->id]);
        $unit = Unit::factory()->create(['campus_id' => $campus->id]);
        $syllabus = Syllabus::factory()->create([
            'unit_id' => $unit->id,
            'total_hours' => 40,
            'hours_per_session' => 2,
        ]);
        $curriculumUnit = CurriculumUnit::factory()->create([
            'unit_id' => $unit->id,
            'syllabus_id' => $syllabus->id,
        ]);
        $this->room = Room::factory()->create([
            'campus_id' => $campus->id,
            'is_bookable' => true,
            'status' => Room::STATUS_AVAILABLE,
        ]);

        $this->courseOffering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'curriculum_unit_id' => $curriculumUnit->id,
            'lecture_id' => $lecture->id,
            'schedule_days' => ['Monday', 'Wednesday'],
            'schedule_time_start' => '09:00:00',
            'schedule_time_end' => '11:00:00',
        ]);
    }

    /** @test */
    public function it_requires_room_id_to_generate_class_sessions(): void
    {
        $response = $this->postJson("/api/course-offerings/{$this->courseOffering->id}/class-sessions/generate", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    /** @test */
    public function it_validates_room_id_exists(): void
    {
        $response = $this->postJson("/api/course-offerings/{$this->courseOffering->id}/class-sessions/generate", [
            'room_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    /** @test */
    public function it_generates_class_sessions_with_valid_room_id(): void
    {
        $response = $this->postJson("/api/course-offerings/{$this->courseOffering->id}/class-sessions/generate", [
            'room_id' => $this->room->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Class sessions generated successfully',
            ]);

        // Verify sessions were created with the room_id
        $sessions = ClassSession::where('course_offering_id', $this->courseOffering->id)->get();
        expect($sessions)->toHaveCount(20); // 40 hours / 2 hours per session = 20 sessions

        foreach ($sessions as $session) {
            expect($session->room_id)->toBe($this->room->id);
        }
    }

    /** @test */
    public function it_prevents_duplicate_class_session_generation(): void
    {
        // First generation
        $response1 = $this->postJson("/api/course-offerings/{$this->courseOffering->id}/class-sessions/generate", [
            'room_id' => $this->room->id,
        ]);

        $response1->assertStatus(200);

        $initialCount = ClassSession::where('course_offering_id', $this->courseOffering->id)->count();

        // Second generation attempt
        $response2 = $this->postJson("/api/course-offerings/{$this->courseOffering->id}/class-sessions/generate", [
            'room_id' => $this->room->id,
        ]);

        $response2->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Class sessions already exist for this course offering',
            ]);

        // Verify no additional sessions were created
        $finalCount = ClassSession::where('course_offering_id', $this->courseOffering->id)->count();
        expect($finalCount)->toBe($initialCount);
    }

    /** @test */
    public function it_validates_room_is_bookable(): void
    {
        $unbookableRoom = Room::factory()->create([
            'campus_id' => $this->room->campus_id,
            'is_bookable' => false,
        ]);

        $response = $this->postJson("/api/course-offerings/{$this->courseOffering->id}/class-sessions/generate", [
            'room_id' => $unbookableRoom->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    /** @test */
    public function it_validates_room_is_available(): void
    {
        $unavailableRoom = Room::factory()->create([
            'campus_id' => $this->room->campus_id,
            'is_bookable' => true,
            'status' => Room::STATUS_OUT_OF_SERVICE,
        ]);

        $response = $this->postJson("/api/course-offerings/{$this->courseOffering->id}/class-sessions/generate", [
            'room_id' => $unavailableRoom->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }
}
