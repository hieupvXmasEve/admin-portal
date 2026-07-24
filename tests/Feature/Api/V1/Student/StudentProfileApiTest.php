<?php

declare(strict_types=1);

use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $attributes */
function studentProfileApiStudent(array $attributes = []): Student
{
    return Student::factory()->create(array_replace([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
        'status' => 'active',
    ], $attributes));
}

it('returns the authenticated student profile through the Registry reader', function (): void {
    $student = studentProfileApiStudent([
        'full_name' => 'Registry Portal Student',
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.profile.show'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Profile retrieved successfully')
        ->assertJsonPath('data.info.id', $student->id)
        ->assertJsonPath('data.info.full_name', 'REGISTRY PORTAL STUDENT')
        ->assertJsonStructure(['data' => ['info', 'preferences', 'profile_completion', 'generated_at']]);
});

it('updates only the authenticated student Registry profile fields', function (): void {
    $student = studentProfileApiStudent();
    $otherStudent = studentProfileApiStudent();

    Sanctum::actingAs($student);

    $this->putJson(route('v1.student.profile.update'), [
        'full_name' => 'Updated Portal Student',
        'phone' => '+84 900 111 222',
        'emergency_contact_name' => 'Portal Guardian',
        'emergency_contact_phone' => '+84 900 333 444',
        'emergency_contact_relationship' => 'parent',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Profile updated successfully');

    expect($student->fresh()->full_name)->toBe('UPDATED PORTAL STUDENT')
        ->and($student->fresh()->phone)->toBe('+84 900 111 222')
        ->and($otherStudent->fresh()->full_name)->not->toBe('UPDATED PORTAL STUDENT');
});

it('preserves the avatar validation envelope', function (): void {
    $student = studentProfileApiStudent();

    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.profile.upload-avatar'))
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid avatar file')
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR')
        ->assertJsonPath('errors.0.field', 'avatar');
});

it('preserves active parent proxy access to the selected student profile', function (): void {
    $student = studentProfileApiStudent([
        'full_name' => 'Parent Visible Student',
    ]);
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Portal Guardian',
        'relationship_type' => 'parent',
        'email' => 'profile-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(route('v1.student.profile.show'), ['X-Student-ID' => $student->student_id])
        ->assertOk()
        ->assertJsonPath('data.info.id', $student->id)
        ->assertJsonPath('data.info.full_name', 'PARENT VISIBLE STUDENT');
});
