<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\Lecture;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Syllabus;
use App\Models\Unit;
use App\Services\ClassSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ClassSessionService $service;

    protected CourseOffering $courseOffering;

    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ClassSessionService;

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
    public function it_generates_class_sessions_with_room_id(): void
    {
        $sessions = $this->service->generateClassSessions($this->courseOffering, $this->room->id);

        expect($sessions)->toHaveCount(20); // 40 hours / 2 hours per session = 20 sessions

        foreach ($sessions as $session) {
            expect($session->room_id)->toBe($this->room->id);
            expect($session->course_offering_id)->toBe($this->courseOffering->id);
        }
    }

    /** @test */
    public function it_skips_generation_if_sessions_already_exist(): void
    {
        // Create existing sessions
        ClassSession::factory()->create([
            'course_offering_id' => $this->courseOffering->id,
            'room_id' => $this->room->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Class sessions already exist for this course offering');

        $this->service->generateClassSessions($this->courseOffering, $this->room->id);
    }

    /** @test */
    public function it_generates_sessions_on_correct_schedule_days(): void
    {
        $sessions = $this->service->generateClassSessions($this->courseOffering, $this->room->id);

        foreach ($sessions as $session) {
            $dayOfWeek = \Carbon\Carbon::parse($session->session_date)->dayOfWeek;
            // Monday = 1, Wednesday = 3
            expect(in_array($dayOfWeek, [1, 3]))->toBeTrue();
        }
    }

    /** @test */
    public function it_assigns_correct_schedule_times(): void
    {
        $sessions = $this->service->generateClassSessions($this->courseOffering, $this->room->id);

        foreach ($sessions as $session) {
            expect($session->start_time->format('H:i:s'))->toBe('09:00:00');
            expect($session->end_time->format('H:i:s'))->toBe('11:00:00');
        }
    }

    /** @test */
    public function it_sets_correct_session_metadata(): void
    {
        $sessions = $this->service->generateClassSessions($this->courseOffering, $this->room->id);

        $firstSession = $sessions->first();
        expect($firstSession->session_type)->toBe('lecture');
        expect($firstSession->delivery_mode)->toBe($this->courseOffering->delivery_mode);
        expect($firstSession->status)->toBe('scheduled');
        expect($firstSession->attendance_required)->toBeTrue();
        expect($firstSession->attendance_tracking_enabled)->toBeTrue();
        expect($firstSession->is_assessment)->toBeFalse();
        expect($firstSession->is_recurring)->toBeFalse();
        expect($firstSession->lecture_id)->toBe($this->courseOffering->lecture_id);
        expect($firstSession->room_id)->toBe($this->room->id);
    }

    /** @test */
    public function it_calculates_correct_duration_minutes(): void
    {
        $sessions = $this->service->generateClassSessions($this->courseOffering, $this->room->id);

        foreach ($sessions as $session) {
            expect($session->duration_minutes)->toBe(120); // 2 hours * 60 minutes
        }
    }

    /** @test */
    public function it_assigns_sequential_session_numbers(): void
    {
        $sessions = $this->service->generateClassSessions($this->courseOffering, $this->room->id);

        $sessionNumbers = $sessions->pluck('sequence_number')->toArray();
        $expectedNumbers = range(1, 20);

        expect($sessionNumbers)->toBe($expectedNumbers);
    }

    /** @test */
    public function it_generates_sessions_with_proper_titles_and_descriptions(): void
    {
        $sessions = $this->service->generateClassSessions($this->courseOffering, $this->room->id);

        foreach ($sessions as $index => $session) {
            expect($session->session_title)->toBe('Session '.($index + 1));
            expect($session->session_description)->toBe('Regular class session');
        }
    }
}
