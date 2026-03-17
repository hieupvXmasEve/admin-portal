<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Event;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('includes campus events in weekly schedule without affecting class sessions', function () {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]);

    $student = Student::factory()->forCampus($campus)->create();
    Sanctum::actingAs($student);

    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
    ]);

    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'registration_method' => 'online',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0,
        'is_retake_paid' => 'no',
    ]);

    ClassSession::factory()->create([
        'course_offering_id' => $courseOffering->id,
        'session_date' => '2026-03-16',
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'duration_minutes' => 120,
        'session_type' => 'lecture',
    ]);

    $event = Event::factory()->published()->create([
        'campus_id' => $campus->id,
        'title' => 'Student Welcome Event',
        'start_time' => '2026-03-17 13:00:00',
        'end_time' => '2026-03-17 15:00:00',
        'requires_registration' => true,
    ]);

    Event::factory()->published()->create([
        'campus_id' => $otherCampus->id,
        'title' => 'Other Campus Event',
        'start_time' => '2026-03-17 13:00:00',
        'end_time' => '2026-03-17 15:00:00',
    ]);

    Event::factory()->draft()->create([
        'campus_id' => $campus->id,
        'title' => 'Draft Event',
        'start_time' => '2026-03-17 16:00:00',
        'end_time' => '2026-03-17 18:00:00',
    ]);

    $response = $this->getJson(route('v1.student.timetable.index', [
        'semester_id' => $semester->id,
        'week_start' => '2026-03-16',
        'week_end' => '2026-03-22',
    ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.weekly_schedule.monday.session_count', 1);
    $response->assertJsonPath('data.weekly_schedule.monday.events', []);
    $response->assertJsonPath('data.weekly_schedule.tuesday.event_count', 1);
    $response->assertJsonPath('data.weekly_schedule.tuesday.events.0.id', $event->id);
    $response->assertJsonPath('data.weekly_schedule.tuesday.events.0.title', 'Student Welcome Event');
    $response->assertJsonPath('data.weekly_schedule.tuesday.events.0.item_type', 'event');
    $response->assertJsonPath('data.weekly_schedule.tuesday.events.0.color', '#0F766E');
    $response->assertJsonPath('data.weekly_schedule.tuesday.events.0.requires_registration', true);
});

it('duplicates multi day events into each overlapping weekday bucket', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create([
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
    ]);

    $student = Student::factory()->forCampus($campus)->create();
    Sanctum::actingAs($student);

    $event = Event::factory()->published()->create([
        'campus_id' => $campus->id,
        'title' => 'Overnight Event',
        'start_time' => '2026-03-18 22:00:00',
        'end_time' => '2026-03-19 01:00:00',
    ]);

    $response = $this->getJson(route('v1.student.timetable.index', [
        'semester_id' => $semester->id,
        'week_start' => '2026-03-16',
        'week_end' => '2026-03-22',
    ]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.weekly_schedule.wednesday.event_count', 1);
    $response->assertJsonPath('data.weekly_schedule.wednesday.events.0.id', $event->id);
    $response->assertJsonPath('data.weekly_schedule.wednesday.events.0.time.start', '22:00:00');
    $response->assertJsonPath('data.weekly_schedule.thursday.event_count', 1);
    $response->assertJsonPath('data.weekly_schedule.thursday.events.0.id', $event->id);
    $response->assertJsonPath('data.weekly_schedule.thursday.events.0.time.end', '01:00:00');
    $response->assertJsonPath('data.weekly_schedule.thursday.events.0.is_multi_day', true);
});
