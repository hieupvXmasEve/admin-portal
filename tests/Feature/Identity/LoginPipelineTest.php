<?php

declare(strict_types=1);

use App\Models\Lecture;
use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function loginPipelineStudent(Semester $semester): Student
{
    return Student::factory()->state([
        'user_id' => User::factory()->create()->id,
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
        'status' => 'active',
    ])->create();
}

it('logs a Parent in with an active grant', function (): void {
    $semester = Semester::factory()->create();
    $student = loginPipelineStudent($semester);
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Pipeline Parent',
        'email' => 'pipeline-parent-ok@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $user = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);
    $user->update(['password' => Hash::make('secret-password')]);

    $this->postJson(route('api.student.parent.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertOk()->assertJsonPath('success', true);
});

it('rejects a Parent login with the wrong password', function (): void {
    $user = User::factory()->create([
        'email' => 'pipeline-parent-wrong-pw@example.test',
        'password' => Hash::make('secret-password'),
        'type' => UserType::PARENT,
    ]);

    $this->postJson(route('api.student.parent.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');
});

it('rejects a Parent login for an inactive account', function (): void {
    $user = User::factory()->create([
        'email' => 'pipeline-parent-inactive@example.test',
        'password' => Hash::make('secret-password'),
        'type' => UserType::PARENT,
        'status' => 'inactive',
    ]);

    $this->postJson(route('api.student.parent.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnauthorized()->assertJsonPath('message', 'Account is not active. Please contact administration.');
});

it('rejects a Parent login once the Guardian grant is revoked', function (): void {
    $semester = Semester::factory()->create();
    $student = loginPipelineStudent($semester);
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Pipeline Revoked Parent',
        'email' => 'pipeline-parent-revoked@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $user = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);
    $user->update(['password' => Hash::make('secret-password')]);
    app(GuardianAccessGrantWriter::class)->revoke($relationship->id);
    ParentProfile::query()->whereKey($grant->parentId)->update(['status' => 'active']);

    $this->postJson(route('api.student.parent.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnauthorized()->assertJsonPath('message', 'Guardian access has been revoked.');
});

it('rate-limits repeated failed Parent login attempts', function (): void {
    $user = User::factory()->create([
        'email' => 'pipeline-parent-ratelimit@example.test',
        'password' => Hash::make('secret-password'),
        'type' => UserType::PARENT,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('api.student.parent.auth.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson(route('api.student.parent.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnauthorized()->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Too many login attempts.'));
});

it('logs a Student in with valid credentials', function (): void {
    $semester = Semester::factory()->create();
    $student = loginPipelineStudent($semester);
    $user = User::query()->findOrFail($student->user_id);
    $user->update(['password' => Hash::make('secret-password')]);

    $this->postJson(route('api.student.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertOk()->assertJsonPath('success', true);
});

it('rejects a Student login with the wrong password', function (): void {
    $semester = Semester::factory()->create();
    $student = loginPipelineStudent($semester);
    $user = User::query()->findOrFail($student->user_id);
    $user->update(['password' => Hash::make('secret-password')]);

    $this->postJson(route('api.student.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');
});

it('rejects a Student login for an inactive account', function (): void {
    $semester = Semester::factory()->create();
    $student = loginPipelineStudent($semester);
    $user = User::query()->findOrFail($student->user_id);
    $user->update(['password' => Hash::make('secret-password'), 'status' => 'inactive']);

    $this->postJson(route('api.student.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnauthorized()->assertJsonPath('message', 'Account is not active. Please contact administration.');
});

it('rate-limits repeated failed Student login attempts', function (): void {
    $semester = Semester::factory()->create();
    $student = loginPipelineStudent($semester);
    $user = User::query()->findOrFail($student->user_id);
    $user->update(['password' => Hash::make('secret-password')]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson(route('api.student.auth.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson(route('api.student.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnauthorized()->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Too many login attempts.'));
});

it('logs a Lecturer in with an active access grant', function (): void {
    $user = User::factory()->create([
        'email' => 'pipeline-lecturer-ok@example.test',
        'password' => Hash::make('secret-password'),
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    Lecture::factory()->create(['user_id' => $user->id, 'email' => $user->email]);
    DB::table('lecturer_access_grants')->where('user_id', $user->id)->update([
        'status' => 'active',
        'reason' => 'eligible_active_employment',
        'granted_at' => now(),
        'revoked_at' => null,
        'updated_at' => now(),
    ]);

    $this->postJson(route('api.lecturer.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertOk()->assertJsonPath('success', true);
});

it('rejects a Lecturer login with the wrong password', function (): void {
    $user = User::factory()->create([
        'email' => 'pipeline-lecturer-wrong-pw@example.test',
        'password' => Hash::make('secret-password'),
        'type' => UserType::LECTURER,
    ]);

    $this->postJson(route('api.lecturer.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');
});

it('rejects a Lecturer login for an inactive account', function (): void {
    $user = User::factory()->create([
        'email' => 'pipeline-lecturer-inactive@example.test',
        'password' => Hash::make('secret-password'),
        'type' => UserType::LECTURER,
        'status' => 'inactive',
    ]);

    $this->postJson(route('api.lecturer.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnauthorized()->assertJsonPath('message', 'Account is not active. Please contact administration.');
});

it('rejects a Lecturer login without an active access grant', function (): void {
    $user = User::factory()->create([
        'email' => 'pipeline-lecturer-restricted@example.test',
        'password' => Hash::make('secret-password'),
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    Lecture::factory()->create(['user_id' => $user->id, 'email' => $user->email]);
    DB::table('lecturer_access_grants')->where('user_id', $user->id)->update([
        'status' => 'revoked',
        'reason' => 'employment_terminated',
        'revoked_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson(route('api.lecturer.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnauthorized()->assertJsonPath('message', 'Account access restricted. Please contact HR.');
});
