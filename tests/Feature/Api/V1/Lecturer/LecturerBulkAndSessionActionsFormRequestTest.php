<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function assertHasValidationErrorField(TestResponse $response, string $field): void
{
    $fields = collect($response->json('errors'))->pluck('field')->all();

    expect($fields)->toContain($field);
}

function lecturerActor(): Lecture
{
    return Lecture::factory()->create([
        'user_id' => User::factory()->create([
            'type' => UserType::LECTURER,
            'status' => User::STATUS_ACTIVE,
        ])->id,
        'campus_id' => Campus::factory()->create()->id,
        'is_active' => true,
        'employment_status' => 'active',
    ]);
}

it('rejects lecturer bulk student actions missing required fields', function () {
    $lecturer = lecturerActor();
    Sanctum::actingAs($lecturer);

    $response = $this->postJson(route('v1.lecturer.students.bulk-actions'), [])
        ->assertStatus(422);

    assertHasValidationErrorField($response, 'action');
    assertHasValidationErrorField($response, 'student_ids');
    assertHasValidationErrorField($response, 'data');
});

it('accepts a valid lecturer bulk student action request', function () {
    $lecturer = lecturerActor();
    $student = Student::factory()->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);
    Sanctum::actingAs($lecturer);

    $this->postJson(route('v1.lecturer.students.bulk-actions'), [
        'action' => 'mark_for_follow_up',
        'student_ids' => [$student->id],
        'data' => ['note' => 'follow up'],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.successful_actions', 1);
});

it('rejects an out-of-range reason on session cancellation', function () {
    $lecturer = lecturerActor();
    $course = CourseOffering::factory()->create([
        'lecture_id' => $lecturer->id,
        'syllabus_template_id' => SyllabusTemplate::factory()->create()->id,
    ]);
    $session = ClassSession::factory()->create([
        'course_offering_id' => $course->id,
        'lecture_id' => $lecturer->id,
        'status' => 'scheduled',
    ]);
    Sanctum::actingAs($lecturer);

    $response = $this->deleteJson(route('v1.lecturer.sessions.cancel', $session), [
        'reason' => str_repeat('x', 501),
    ])
        ->assertStatus(422);

    assertHasValidationErrorField($response, 'reason');
});

it('cancels a session with a valid reason', function () {
    $lecturer = lecturerActor();
    $course = CourseOffering::factory()->create([
        'lecture_id' => $lecturer->id,
        'syllabus_template_id' => SyllabusTemplate::factory()->create()->id,
    ]);
    $session = ClassSession::factory()->create([
        'course_offering_id' => $course->id,
        'lecture_id' => $lecturer->id,
        'status' => 'scheduled',
    ]);
    Sanctum::actingAs($lecturer);

    $this->deleteJson(route('v1.lecturer.sessions.cancel', $session), [
        'reason' => 'Room unavailable',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($session->fresh()->status)->toBe('cancelled');
});

it('rejects a bulk session update missing required session fields', function () {
    $lecturer = lecturerActor();
    Sanctum::actingAs($lecturer);

    $response = $this->postJson(route('v1.lecturer.sessions.bulk-update'), [
        'sessions' => [['session_id' => 1]],
    ])
        ->assertStatus(422);

    assertHasValidationErrorField($response, 'sessions.0.updates');
});
