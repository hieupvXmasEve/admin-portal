<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Notification\Models\NotificationMessage;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function lecturerNotification(Lecture $lecturer, array $attributes = []): NotificationMessage
{
    return NotificationMessage::query()->create(array_merge([
        'event_id' => (string) Str::uuid(),
        'event_name' => 'notification.lecturer.test',
        'type_key' => 'manual_notification',
        'campus_id' => $lecturer->campus_id,
        'recipient_user_id' => $lecturer->user_id,
        'title' => 'Lecturer notification',
        'body' => 'Notification body',
        'data' => ['category' => 'academic'],
        'status' => 'active',
    ], $attributes));
}

function lecturerNotificationActor(): Lecture
{
    $user = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);

    return Lecture::factory()->create([
        'user_id' => $user->id,
        'campus_id' => Campus::factory()->create()->id,
        'is_active' => true,
        'employment_status' => 'active',
        'is_available_for_assignment' => true,
    ]);
}

beforeEach(function (): void {
    Semester::factory()->active()->create();
});

it('lists only the authenticated lecturer notifications', function (): void {
    $lecturer = lecturerNotificationActor();
    $otherLecturer = lecturerNotificationActor();
    $visible = lecturerNotification($lecturer);
    lecturerNotification($otherLecturer);
    lecturerNotification($lecturer, ['archived_at' => now()]);

    Sanctum::actingAs($lecturer);

    $this->getJson(route('v1.lecturer.notifications.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.category.key', 'academic')
        ->assertJsonPath('meta.unread_count', 1)
        ->assertJsonCount(1, 'data');
});

it('marks only the authenticated lecturer notification as read', function (): void {
    $lecturer = lecturerNotificationActor();
    $otherLecturer = lecturerNotificationActor();
    $notification = lecturerNotification($lecturer);
    $otherNotification = lecturerNotification($otherLecturer);

    Sanctum::actingAs($lecturer);

    $this->postJson(route('v1.lecturer.notifications.mark-read', ['notification' => $notification->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.unread_count', 0);

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()->read_at)->toBeNull();
});

it('normalizes the read-state filter and marks all scoped notifications as read', function (): void {
    $lecturer = lecturerNotificationActor();
    $unread = lecturerNotification($lecturer);
    $read = lecturerNotification($lecturer, ['read_at' => now()]);

    Sanctum::actingAs($lecturer);

    $this->getJson(route('v1.lecturer.notifications.index', ['is_read' => 'false']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $unread->id);

    $this->postJson(route('v1.lecturer.notifications.mark-all-read'))
        ->assertOk()
        ->assertJsonPath('data.updated_count', 1)
        ->assertJsonPath('data.unread_count', 0);

    expect($read->fresh()->read_at)->not->toBeNull()
        ->and($unread->fresh()->read_at)->not->toBeNull();
});
