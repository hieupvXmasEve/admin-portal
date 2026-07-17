<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\StudentService;
use App\Shared\Support\Enums\UserType;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
    $this->withoutMiddleware([
        Authenticate::class,
        Authorize::class,
        EnsureEmailIsVerified::class,
    ]);

    if (! Route::has(StudentRoutes::ACADEMIC_SUMMARY_SHOW)) {
        Route::get('/testing/students/{student}/academic-summary', fn () => 'ok')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_SHOW);
    }

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['edit_student']);

    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('syncs the linked user email when updating a student email', function () {
    $studentUser = User::factory()->create([
        'email' => 'student.old@example.com',
        'name' => 'student old',
    ]);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'user_id' => $studentUser->id,
            'full_name' => 'student old',
            'email' => 'student.old@example.com',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $updatedStudent = app(StudentService::class)->updateStudent($student, [
        'full_name' => 'student old',
        'email' => 'student.new@example.com',
    ]);

    expect($updatedStudent->email)->toBe('student.new@example.com')
        ->and($student->fresh()->email)->toBe('student.new@example.com')
        ->and($studentUser->fresh()->email)->toBe('student.new@example.com');
});

it('rejects a student email that is already used by another user account', function () {
    User::factory()->create([
        'email' => 'taken@example.com',
    ]);

    $studentUser = User::factory()->create([
        'email' => 'student.old@example.com',
    ]);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'user_id' => $studentUser->id,
            'full_name' => 'student old',
            'email' => 'student.old@example.com',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $csrfToken = 'student-update-csrf-token';

    $response = actingAs($this->authorizedUser)
        ->from(route(StudentRoutes::EDIT, $student))
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $csrfToken,
        ])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->put(route(StudentRoutes::UPDATE, $student), [
            'full_name' => 'student old',
            'email' => 'taken@example.com',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['email']);

    expect($student->fresh()->email)->toBe('student.old@example.com')
        ->and($studentUser->fresh()->email)->toBe('student.old@example.com');
});

it('shows the primary parent after assigning a new parent email', function () {
    Notification::fake();

    $studentUser = User::factory()->create([
        'email' => 'student@example.com',
    ]);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'user_id' => $studentUser->id,
            'full_name' => 'Student',
            'email' => 'student@example.com',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $oldParent = ParentProfile::factory()->create();
    DB::table('parent_student')->insert([
        'parent_id' => $oldParent->id,
        'student_id' => $student->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(StudentService::class)->updateStudent($student, [
        'full_name' => 'Student',
        'email' => 'student@example.com',
        'parent_name' => 'New Primary Parent',
        'parent_email' => 'new-primary-parent@example.com',
    ]);

    actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::EDIT, $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/Edit')
            ->where('student.parent_user.email', 'new-primary-parent@example.com')
        );
});

it('allows saving again with the current primary parent email', function () {
    Notification::fake();

    $studentUser = User::factory()->create([
        'email' => 'student@example.com',
    ]);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'user_id' => $studentUser->id,
            'full_name' => 'Student',
            'email' => 'student@example.com',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $oldParent = ParentProfile::factory()->create();
    DB::table('parent_student')->insert([
        'parent_id' => $oldParent->id,
        'student_id' => $student->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(StudentService::class)->updateStudent($student, [
        'full_name' => 'Student',
        'email' => 'student@example.com',
        'parent_name' => 'New Primary Parent',
        'parent_email' => 'new-primary-parent@example.com',
    ]);

    $csrfToken = 'student-update-csrf-token';

    $response = actingAs($this->authorizedUser)
        ->from(route(StudentRoutes::EDIT, $student))
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $csrfToken,
        ])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->put(route(StudentRoutes::UPDATE, $student), [
            'full_name' => 'Student',
            'email' => 'student@example.com',
            'parent_name' => 'New Primary Parent',
            'parent_email' => 'new-primary-parent@example.com',
        ]);

    $response
        ->assertRedirect(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $student))
        ->assertSessionHasNoErrors();
});

it('replaces the primary parent, revokes the old parent access, and sends the new parent a setup link', function () {
    Notification::fake();

    $student = Student::factory()->create([
        'email' => 'student@example.com',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $oldParentUser = User::factory()->create([
        'type' => UserType::PARENT,
    ]);
    $oldParentProfile = ParentProfile::factory()->create([
        'user_id' => $oldParentUser->id,
    ]);
    $oldParentUser->createToken('Old parent portal token');

    DB::table('parent_student')->insert([
        'parent_id' => $oldParentProfile->id,
        'student_id' => $student->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(StudentService::class)->updateStudent($student, [
        'full_name' => $student->full_name,
        'email' => $student->email,
        'parent_name' => 'New Parent',
        'parent_email' => 'new-parent@example.com',
    ]);

    $newParentUser = User::query()->where('email', 'new-parent@example.com')->firstOrFail();

    expect(DB::table('parent_student')
        ->where('parent_id', $oldParentProfile->id)
        ->where('student_id', $student->id)
        ->exists())->toBeFalse()
        ->and($oldParentProfile->fresh()->status)->toBe('inactive')
        ->and($oldParentUser->fresh()->tokens()->exists())->toBeFalse()
        ->and(DB::table('parent_student')
            ->where('parent_id', ParentProfile::query()->where('user_id', $newParentUser->id)->value('id'))
            ->where('student_id', $student->id)
            ->where('is_primary', true)
            ->exists())->toBeTrue();

    Notification::assertSentTo($newParentUser, ResetPassword::class);
});

it('removes and revokes the current primary parent when parent email is cleared', function () {
    $student = Student::factory()->create([
        'email' => 'student@example.com',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $parentUser = User::factory()->create([
        'type' => UserType::PARENT,
    ]);
    $parentProfile = ParentProfile::factory()->create([
        'user_id' => $parentUser->id,
    ]);
    $parentUser->createToken('Parent portal token');

    DB::table('parent_student')->insert([
        'parent_id' => $parentProfile->id,
        'student_id' => $student->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(StudentService::class)->updateStudent($student, [
        'full_name' => $student->full_name,
        'email' => $student->email,
        'parent_email' => null,
    ]);

    expect(DB::table('parent_student')
        ->where('parent_id', $parentProfile->id)
        ->where('student_id', $student->id)
        ->exists())->toBeFalse()
        ->and($parentProfile->fresh()->status)->toBe('inactive')
        ->and($parentUser->fresh()->tokens()->exists())->toBeFalse();
});

it('rejects using the student or a service account email as the parent email', function () {
    $studentUser = User::factory()->create([
        'email' => 'student@example.com',
    ]);
    $student = Student::factory()->create([
        'user_id' => $studentUser->id,
        'email' => 'student@example.com',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $serviceUser = User::factory()->create([
        'email' => 'service@example.com',
        'type' => UserType::SERVICE,
    ]);
    $csrfToken = 'student-update-csrf-token';

    $selfEmailResponse = actingAs($this->authorizedUser)
        ->from(route(StudentRoutes::EDIT, $student))
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $csrfToken,
        ])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->put(route(StudentRoutes::UPDATE, $student), [
            'full_name' => $student->full_name,
            'email' => $student->email,
            'parent_email' => $student->email,
        ]);

    $selfEmailResponse->assertSessionHasErrors(['parent_email']);

    $serviceAccountResponse = actingAs($this->authorizedUser)
        ->from(route(StudentRoutes::EDIT, $student))
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $csrfToken,
        ])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->put(route(StudentRoutes::UPDATE, $student), [
            'full_name' => $student->full_name,
            'email' => $student->email,
            'parent_email' => $serviceUser->email,
        ]);

    $serviceAccountResponse->assertSessionHasErrors(['parent_email']);

    expect($serviceUser->fresh()->type)->toBe(UserType::SERVICE)
        ->and(ParentProfile::query()->where('user_id', $serviceUser->id)->doesntExist())->toBeTrue();
});

it('allows an existing parent account that is not linked to another student', function () {
    $student = Student::factory()->create([
        'email' => 'student@example.com',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $parentUser = User::factory()->create([
        'email' => 'unlinked-parent@example.com',
        'type' => UserType::PARENT,
    ]);
    $csrfToken = 'student-update-csrf-token';

    $response = actingAs($this->authorizedUser)
        ->from(route(StudentRoutes::EDIT, $student))
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $csrfToken,
        ])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->put(route(StudentRoutes::UPDATE, $student), [
            'full_name' => $student->full_name,
            'email' => $student->email,
            'parent_email' => $parentUser->email,
        ]);

    $response->assertSessionHasNoErrors();

    expect(DB::table('parent_student')
        ->where('parent_id', ParentProfile::query()->where('user_id', $parentUser->id)->value('id'))
        ->where('student_id', $student->id)
        ->where('is_primary', true)
        ->exists())->toBeTrue();
});

it('rejects a parent account that is linked to another student', function () {
    $student = Student::factory()->create([
        'email' => 'student@example.com',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $otherStudent = Student::factory()->create([
        'email' => 'other-student@example.com',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $parentUser = User::factory()->create([
        'email' => 'linked-parent@example.com',
        'type' => UserType::PARENT,
    ]);
    $parentProfile = ParentProfile::factory()->create([
        'user_id' => $parentUser->id,
    ]);
    DB::table('parent_student')->insert([
        'parent_id' => $parentProfile->id,
        'student_id' => $otherStudent->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $csrfToken = 'student-update-csrf-token';

    $response = actingAs($this->authorizedUser)
        ->from(route(StudentRoutes::EDIT, $student))
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $csrfToken,
        ])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->put(route(StudentRoutes::UPDATE, $student), [
            'full_name' => $student->full_name,
            'email' => $student->email,
            'parent_email' => $parentUser->email,
        ]);

    $response->assertSessionHasErrors(['parent_email']);

    expect(DB::table('parent_student')
        ->where('parent_id', $parentProfile->id)
        ->where('student_id', $student->id)
        ->doesntExist())->toBeTrue();
});

it('restores a soft-deleted parent profile when assigning its email', function () {
    $student = Student::factory()->create([
        'email' => 'student@example.com',
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $parentUser = User::factory()->create([
        'email' => 'parent@example.com',
        'type' => UserType::PARENT,
    ]);
    $parentProfile = ParentProfile::factory()->create([
        'user_id' => $parentUser->id,
    ]);
    $parentProfile->delete();

    app(StudentService::class)->updateStudent($student, [
        'full_name' => $student->full_name,
        'email' => $student->email,
        'parent_email' => $parentUser->email,
    ]);

    expect($parentProfile->fresh())->not->toBeNull()
        ->and($parentProfile->fresh()->trashed())->toBeFalse()
        ->and(DB::table('parent_student')
            ->where('parent_id', $parentProfile->id)
            ->where('student_id', $student->id)
            ->where('is_primary', true)
            ->exists())->toBeTrue();
});

it('enforces at most one primary parent per student in the database', function () {
    $student = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $firstParent = ParentProfile::factory()->create();
    $secondParent = ParentProfile::factory()->create();

    DB::table('parent_student')->insert([
        'parent_id' => $firstParent->id,
        'student_id' => $student->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('parent_student')->insert([
        'parent_id' => $secondParent->id,
        'student_id' => $student->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('enforces that one parent can be linked to only one student in the database', function () {
    $firstStudent = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $secondStudent = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'program_id' => $this->program->id,
        'curriculum_version_id' => $this->curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $parent = ParentProfile::factory()->create();

    DB::table('parent_student')->insert([
        'parent_id' => $parent->id,
        'student_id' => $firstStudent->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('parent_student')->insert([
        'parent_id' => $parent->id,
        'student_id' => $secondStudent->id,
        'relationship' => 'guardian',
        'is_primary' => false,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
