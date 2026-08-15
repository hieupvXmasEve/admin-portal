<?php

declare(strict_types=1);

use App\Http\Middleware\ParentOrStudentAccess;
use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

function makeParentOrStudentAccessStudent(Semester $semester, string $status = 'active'): Student
{
    return Student::factory()->state([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
        'status' => $status,
    ])->create();
}

function grantParentOrStudentAccess(Student $student, string $email): User
{
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Matrix Guardian',
        'email' => $email,
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);

    return User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);
}

function callParentOrStudentAccess(Request $request): Response
{
    return app(ParentOrStudentAccess::class)->handle($request, static fn (): Response => new Response(status: 204));
}

it('allows an active Student token straight through', function (): void {
    $semester = Semester::factory()->create();
    $student = makeParentOrStudentAccessStudent($semester);

    $request = Request::create('/matrix', 'GET');
    $request->setUserResolver(static fn (): Student => $student);

    expect(callParentOrStudentAccess($request)->getStatusCode())->toBe(204);
});

it('rejects a blocked-status Student token', function (): void {
    $semester = Semester::factory()->create();
    $student = makeParentOrStudentAccessStudent($semester, 'inactive');

    $request = Request::create('/matrix', 'GET');
    $request->setUserResolver(static fn (): Student => $student);

    expect(callParentOrStudentAccess($request)->getStatusCode())->toBe(403);
});

it('allows a Parent with an active grant when student_id is provided', function (): void {
    $semester = Semester::factory()->create();
    $student = makeParentOrStudentAccessStudent($semester);
    $parent = grantParentOrStudentAccess($student, 'matrix-parent-ok@example.test');

    $request = Request::create('/matrix', 'GET', ['student_id' => $student->student_id]);
    $request->setUserResolver(static fn (): User => $parent);

    expect(callParentOrStudentAccess($request)->getStatusCode())->toBe(204);
});

it('rejects a Parent request missing student_id', function (): void {
    $semester = Semester::factory()->create();
    $student = makeParentOrStudentAccessStudent($semester);
    $parent = grantParentOrStudentAccess($student, 'matrix-parent-missing-id@example.test');

    $request = Request::create('/matrix', 'GET');
    $request->setUserResolver(static fn (): User => $parent);

    expect(callParentOrStudentAccess($request)->getStatusCode())->toBe(422);
});

it('rejects a Parent proxying to a Student they have no grant for', function (): void {
    $semester = Semester::factory()->create();
    $grantedStudent = makeParentOrStudentAccessStudent($semester);
    $otherStudent = makeParentOrStudentAccessStudent($semester);
    $parent = grantParentOrStudentAccess($grantedStudent, 'matrix-parent-wrong-student@example.test');

    $request = Request::create('/matrix', 'GET', ['student_id' => $otherStudent->student_id]);
    $request->setUserResolver(static fn (): User => $parent);

    expect(callParentOrStudentAccess($request)->getStatusCode())->toBe(403);
});

it('lets a Student with a blocking academic hold log out', function (): void {
    $semester = Semester::factory()->create();
    $student = makeParentOrStudentAccessStudent($semester);
    $student->academicHolds()->create([
        'hold_type' => 'financial',
        'hold_category' => 'all',
        'title' => 'Matrix logout hold',
        'status' => 'active',
        'placed_date' => now(),
    ]);

    Sanctum::actingAs($student, ['student']);

    $this->postJson(route('api.student.auth.logout'))->assertOk();
});

it('rejects a Parent whose grant has been revoked', function (): void {
    $semester = Semester::factory()->create();
    $student = makeParentOrStudentAccessStudent($semester);
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Revoked Matrix Guardian',
        'email' => 'matrix-parent-revoked@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);
    app(GuardianAccessGrantWriter::class)->revoke($relationship->id);

    $request = Request::create('/matrix', 'GET', ['student_id' => $student->student_id]);
    $request->setUserResolver(static fn (): User => $parent);

    expect(callParentOrStudentAccess($request)->getStatusCode())->toBe(403);
});
