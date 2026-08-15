<?php

declare(strict_types=1);

use App\Http\Middleware\ParentStudentAccess;
use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Identity\Actions\ParentLoginAction;
use App\Modules\Identity\Actions\ParentRefreshTokenAction;
use App\Modules\Identity\Queries\GetParentContextQuery;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Support\Enums\UserType;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

it('changes and revokes Guardian access without changing the Registry relationship', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Registry Guardian',
        'relationship_type' => 'mother',
        'email' => 'registry-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $relationshipBefore = DB::table('student_guardian_relationships')->find($relationship->id);

    $writer = app(GuardianAccessGrantWriter::class);
    $grant = $writer->grant($relationship, isPrimaryPortalAccount: true);
    $changedGrant = $writer->changeAccessLevel($relationship->id, 'financial_read');
    $revokedGrant = $writer->revoke($relationship->id);

    expect($grant->accessLevel)->toBe('read_only')
        ->and($changedGrant->accessLevel)->toBe('financial_read')
        ->and($revokedGrant->status)->toBe('revoked')
        ->and(DB::table('student_guardian_relationships')->find($relationship->id))
        ->toEqual($relationshipBefore);
});

it('does not grant portal access to a Guardian without an email', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Offline Guardian',
        'relationship_type' => 'guardian',
        'email' => null,
        'is_primary' => true,
    ]])[0];

    expect(fn () => app(GuardianAccessGrantWriter::class)->grant($relationship))
        ->toThrow(DomainException::class)
        ->and(DB::table('student_guardian_relationships')->where('id', $relationship->id)->exists())->toBeTrue()
        ->and(DB::table('guardian_access_grants')->count())->toBe(0);
});

it('rejects grant provisioning for an account linked to another student only by the legacy projection', function (): void {
    $semester = Semester::factory()->create();
    $firstStudent = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $secondStudent = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $user = User::factory()->create([
        'email' => 'legacy-linked@example.test',
        'type' => UserType::PARENT,
    ]);
    $profile = ParentProfile::factory()->create(['user_id' => $user->id]);
    DB::table('parent_student')->insert([
        'parent_id' => $profile->id,
        'student_id' => $firstStudent->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $secondStudent->id, [[
        'full_name' => 'Legacy Linked Guardian',
        'email' => $user->email,
        'is_primary' => true,
    ]])[0];

    expect(fn () => app(GuardianAccessGrantWriter::class)->grant($relationship))
        ->toThrow(DomainException::class, 'This Guardian account already has a Student access grant.');
});

it('evaluates active Student access from Identity grants', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Access Guardian',
        'email' => 'access-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $userId = (int) DB::table('parents')->where('id', $grant->parentId)->value('user_id');
    $reader = app(GuardianAccessGrantReader::class);

    expect($reader->hasActiveGrant($userId, (int) $student->id))->toBeTrue()
        ->and($reader->activeStudentIdsForUser($userId))->toBe([(int) $student->id]);

    app(GuardianAccessGrantWriter::class)->revoke($relationship->id);

    expect($reader->hasActiveGrant($userId, (int) $student->id))->toBeFalse()
        ->and($reader->activeStudentIdsForUser($userId))->toBe([]);
});

it('denies Guardian login when only relationship metadata remains after access revocation', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Login Guardian',
        'email' => 'login-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $profile = ParentProfile::query()->findOrFail($grant->parentId);
    $user = User::query()->findOrFail($profile->user_id);
    $user->update(['password' => Hash::make('secret-password')]);

    $this->postJson(route('api.student.parent.auth.login'), [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertOk();

    app(GuardianAccessGrantWriter::class)->revoke($relationship->id);
    ParentProfile::query()->whereKey($profile->id)->update(['status' => 'active']);

    expect($profile->fresh()->status)->toBe('active')
        ->and(DB::table('parent_student')->where('parent_id', $profile->id)->exists())->toBeTrue()
        ->and(fn () => ParentLoginAction::run([
            'email' => $user->email,
            'password' => 'secret-password',
            'ip' => '127.0.0.2',
        ]))->toThrow(Exception::class, 'Guardian access has been revoked.');
});

it('authorizes Guardian Student context from the active Identity grant only', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $otherStudent = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Context Guardian',
        'email' => 'context-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $profile = ParentProfile::query()->findOrFail($grant->parentId);
    $user = User::query()->findOrFail($profile->user_id);
    $middleware = app(ParentStudentAccess::class);

    $allowedRequest = Request::create('/student-context', 'GET', ['student_id' => $student->student_id]);
    $allowedRequest->setUserResolver(static fn (): User => $user);
    $allowedResponse = $middleware->handle($allowedRequest, static fn (): Response => new Response(status: 204));

    $otherRequest = Request::create('/student-context', 'GET', ['student_id' => $otherStudent->student_id]);
    $otherRequest->setUserResolver(static fn (): User => $user);
    $otherResponse = $middleware->handle($otherRequest, static fn (): Response => new Response(status: 204));

    expect($allowedResponse->getStatusCode())->toBe(204)
        ->and($otherResponse->getStatusCode())->toBe(403);

    app(GuardianAccessGrantWriter::class)->revoke($relationship->id);
    ParentProfile::query()->whereKey($profile->id)->update(['status' => 'active']);

    $revokedRequest = Request::create('/student-context', 'GET', ['student_id' => $student->student_id]);
    $revokedRequest->setUserResolver(static fn (): User => $user);
    $revokedResponse = $middleware->handle($revokedRequest, static fn (): Response => new Response(status: 204));

    expect(DB::table('parent_student')->where('parent_id', $profile->id)->exists())->toBeTrue()
        ->and($revokedResponse->getStatusCode())->toBe(403);
});

it('allows Guardian access to a deferred Student but blocks a suspended one', function (): void {
    $semester = Semester::factory()->create();
    $deferredStudent = Student::factory()->state([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
        'status' => 'deferred',
    ])->create();
    $suspendedStudent = Student::factory()->state([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
        'status' => 'suspended',
    ])->create();

    $deferredRelationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $deferredStudent->id, [[
        'full_name' => 'Deferred Guardian',
        'email' => 'deferred-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $deferredGrant = app(GuardianAccessGrantWriter::class)->grant($deferredRelationship, isPrimaryPortalAccount: true);
    $deferredProfile = ParentProfile::query()->findOrFail($deferredGrant->parentId);
    $deferredUser = User::query()->findOrFail($deferredProfile->user_id);

    $suspendedRelationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $suspendedStudent->id, [[
        'full_name' => 'Suspended Guardian',
        'email' => 'suspended-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $suspendedGrant = app(GuardianAccessGrantWriter::class)->grant($suspendedRelationship, isPrimaryPortalAccount: true);
    $suspendedProfile = ParentProfile::query()->findOrFail($suspendedGrant->parentId);
    $suspendedUser = User::query()->findOrFail($suspendedProfile->user_id);

    $middleware = app(ParentStudentAccess::class);

    $deferredRequest = Request::create('/student-context', 'GET', ['student_id' => $deferredStudent->student_id]);
    $deferredRequest->setUserResolver(static fn (): User => $deferredUser);
    $deferredResponse = $middleware->handle($deferredRequest, static fn (): Response => new Response(status: 204));

    $suspendedRequest = Request::create('/student-context', 'GET', ['student_id' => $suspendedStudent->student_id]);
    $suspendedRequest->setUserResolver(static fn (): User => $suspendedUser);
    $suspendedResponse = $middleware->handle($suspendedRequest, static fn (): Response => new Response(status: 204));

    expect($deferredResponse->getStatusCode())->toBe(204)
        ->and($suspendedResponse->getStatusCode())->toBe(403);
});

it('refreshes tokens and filters Parent context using active Identity grants', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->state(['intake' => $semester->id, 'intake_semester_id' => $semester->id])->create();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Refresh Guardian',
        'email' => 'refresh-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $profile = ParentProfile::query()->findOrFail($grant->parentId);
    $user = User::query()->findOrFail($profile->user_id);

    $refresh = ParentRefreshTokenAction::run($user);
    $activeContext = app(GetParentContextQuery::class)->handle($profile);

    expect($refresh['token'])->not->toBeEmpty()
        ->and(collect($activeContext['user']['children'])->pluck('id')->all())->toBe([(int) $student->id]);

    app(GuardianAccessGrantWriter::class)->revoke($relationship->id);
    ParentProfile::query()->whereKey($profile->id)->update(['status' => 'active']);

    expect(fn () => ParentRefreshTokenAction::run($user->fresh()))
        ->toThrow(Exception::class, 'Guardian access has been revoked.');

    $revokedContext = app(GetParentContextQuery::class)->handle($profile->fresh());

    expect(collect($revokedContext['user']['children']))->toBeEmpty();
});
