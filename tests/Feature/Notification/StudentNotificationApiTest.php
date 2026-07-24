<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function studentNotificationApiStudent(): Student
{
    return Student::factory()->create([
        'user_id' => User::factory()->create()->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);
}

function studentNotificationMessage(Student $student, array $attributes = []): NotificationMessage
{
    return NotificationMessage::query()->create(array_merge([
        'event_id' => (string) Str::uuid(),
        'event_name' => 'notification.test',
        'type_key' => 'manual_notification',
        'campus_id' => $student->campus_id,
        'recipient_user_id' => $student->user_id,
        'title' => 'Portal notification',
        'body' => 'Notification body',
        'data' => ['category' => 'system'],
        'status' => 'active',
    ], $attributes));
}

it('lists only the active unarchived notifications for the authenticated student', function () {
    $student = studentNotificationApiStudent();
    $otherStudent = studentNotificationApiStudent();
    $visible = studentNotificationMessage($student);
    studentNotificationMessage($student, ['archived_at' => now()]);
    studentNotificationMessage($otherStudent);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.notifications.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.category.key', 'system')
        ->assertJsonPath('meta.unread_count', 1)
        ->assertJsonCount(1, 'data');
});

it('marks only the authenticated student notification as read', function () {
    $student = studentNotificationApiStudent();
    $otherStudent = studentNotificationApiStudent();
    $notification = studentNotificationMessage($student);
    $otherNotification = studentNotificationMessage($otherStudent);

    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.notifications.mark-read', ['notification' => $notification->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.unread_count', 0);

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()->read_at)->toBeNull();
});
