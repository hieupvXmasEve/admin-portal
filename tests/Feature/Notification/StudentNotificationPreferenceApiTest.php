<?php

declare(strict_types=1);

use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\UserEmailPreference;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $attributes */
function studentNotificationPreferenceApiStudent(array $attributes = []): Student
{
    $account = User::factory()->create();

    return Student::factory()->create(array_replace([
        'user_id' => $account->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
        'status' => 'active',
    ], $attributes));
}

it('lists default event preferences for the authenticated student account', function (): void {
    $student = studentNotificationPreferenceApiStudent();

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.notifications.preferences.events.get'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.event_publication.enabled', true)
        ->assertJsonPath('data.event_publication.frequency', UserEmailPreference::FREQUENCY_IMMEDIATE)
        ->assertJsonPath('data.event_publication.label', 'Event Publications');
});

it('updates event preferences only for the authenticated student account', function (): void {
    $student = studentNotificationPreferenceApiStudent();
    $otherStudent = studentNotificationPreferenceApiStudent();

    Sanctum::actingAs($student);

    $this->putJson(route('v1.student.notifications.preferences.events.update'), [
        'preferences' => [
            UserEmailPreference::TYPE_EVENT_PUBLICATION => [
                'enabled' => false,
                'frequency' => UserEmailPreference::FREQUENCY_NEVER,
            ],
        ],
    ])
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
        ]);

    expect(UserEmailPreference::getUserPreference((int) $student->user_id, UserEmailPreference::TYPE_EVENT_PUBLICATION))
        ->not->toBeNull()
        ->and(UserEmailPreference::getUserPreference((int) $student->user_id, UserEmailPreference::TYPE_EVENT_PUBLICATION)?->is_enabled)->toBeFalse()
        ->and(UserEmailPreference::getUserPreference((int) $otherStudent->user_id, UserEmailPreference::TYPE_EVENT_PUBLICATION))->toBeNull();
});

it('preserves the existing validation response for missing preferences', function (): void {
    $student = studentNotificationPreferenceApiStudent();

    Sanctum::actingAs($student);

    $this->putJson(route('v1.student.notifications.preferences.events.update'), [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
        ->assertJsonPath('errors.0.field', 'preferences');
});

it('preserves active parent proxy access to the selected student preferences', function (): void {
    $student = studentNotificationPreferenceApiStudent();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Preference Guardian',
        'relationship_type' => 'parent',
        'email' => 'preferences-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(route('v1.student.notifications.preferences.events.get'), ['X-Student-ID' => $student->student_id])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.event_registration.enabled', true);
});

it('rejects a parent proxy update for a student without an active grant', function (): void {
    $grantedStudent = studentNotificationPreferenceApiStudent();
    $ungrantedStudent = studentNotificationPreferenceApiStudent();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $grantedStudent->id, [[
        'full_name' => 'Granted Preference Guardian',
        'relationship_type' => 'parent',
        'email' => 'granted-preferences-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->putJson(
        route('v1.student.notifications.preferences.events.update'),
        ['preferences' => []],
        ['X-Student-ID' => $ungrantedStudent->student_id],
    )
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'AUTHORIZATION_ERROR');
});
