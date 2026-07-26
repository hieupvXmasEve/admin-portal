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
use App\Services\StudentService;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Contracts\StudentRegistry\StudentIdentityWriter;
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
use RuntimeException;

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

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['edit_student']);

    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
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

it('updates identity email through the staff endpoint', function () {
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
    $csrfToken = 'student-update-csrf-token';

    $response = actingAs($this->authorizedUser)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $csrfToken,
        ])
        ->withHeader('X-CSRF-TOKEN', $csrfToken)
        ->put(route(StudentRoutes::UPDATE, $student), [
            'full_name' => 'student renamed',
            'email' => 'student.new@example.com',
        ]);

    $response
        ->assertRedirect(route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $student))
        ->assertSessionHasNoErrors();

    expect($student->fresh()->full_name)->toBe('STUDENT RENAMED')
        ->and($student->fresh()->email)->toBe('student.new@example.com')
        ->and($studentUser->fresh()->email)->toBe('student.new@example.com');
});

it('rolls back profile changes when linked account email update fails', function () {
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
    $identityWriter = Mockery::mock(StudentIdentityWriter::class);
    $identityWriter->shouldReceive('updateEmail')
        ->once()
        ->andThrow(new RuntimeException('Unable to update linked account email.'));
    app()->instance(StudentIdentityWriter::class, $identityWriter);

    expect(fn () => app(StudentService::class)->updateStudent($student, [
        'full_name' => 'student renamed',
        'email' => 'student.new@example.com',
    ]))->toThrow(RuntimeException::class, 'Unable to update linked account email.');

    expect($student->fresh()->full_name)->toBe('STUDENT OLD')
        ->and($student->fresh()->email)->toBe('student.old@example.com')
        ->and($studentUser->fresh()->email)->toBe('student.old@example.com');
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
    $newParentUser = User::query()->where('email', 'new-primary-parent@example.com')->firstOrFail();

    actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::EDIT, $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/Edit')
            ->where('student.parent_user.id', $newParentUser->id)
            ->where('student.parent_user.name', 'New Primary Parent')
            ->where('student.parent_user.email', 'new-primary-parent@example.com')
        );
});

it('shows the legacy primary parent account while its Registry relationship is not materialized', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
    $parentUser = User::factory()->create([
        'name' => 'Legacy Primary Parent',
        'email' => 'legacy-parent@example.com',
        'type' => UserType::PARENT,
    ]);
    $parentProfile = ParentProfile::factory()->create(['user_id' => $parentUser->id]);
    DB::table('parent_student')->insert([
        'parent_id' => $parentProfile->id,
        'student_id' => $student->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::EDIT, $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/Edit')
            ->where('student.parent_user.id', $parentUser->id)
            ->where('student.parent_user.name', 'Legacy Primary Parent')
            ->where('student.parent_user.email', 'legacy-parent@example.com')
        );
});

it('does not expose a legacy account when the primary Registry Guardian has no access grant', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
    $legacyParentUser = User::factory()->create([
        'email' => 'legacy-parent@example.com',
        'type' => UserType::PARENT,
    ]);
    $legacyParentProfile = ParentProfile::factory()->create(['user_id' => $legacyParentUser->id]);
    DB::table('parent_student')->insert([
        'parent_id' => $legacyParentProfile->id,
        'student_id' => $student->id,
        'relationship' => 'guardian',
        'is_primary' => true,
        'access_level' => 'read_only',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Guardian Without Account',
        'email' => 'guardian-without-account@example.com',
        'is_primary' => true,
    ]]);

    actingAs($this->authorizedUser)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::EDIT, $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/Edit')
            ->where('student.parent_user', null)
        );
});

it('marks an existing Registry Guardian as primary when the staff form selects it again', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'email' => 'student@example.com',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
    app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [
        [
            'full_name' => 'Existing Primary Guardian',
            'email' => 'primary-guardian@example.com',
            'is_primary' => true,
        ],
        [
            'full_name' => 'Previous Guardian Name',
            'email' => 'selected-guardian@example.com',
            'is_primary' => false,
        ],
    ]);
    $selectedParentUser = User::factory()->create([
        'email' => 'selected-guardian@example.com',
        'type' => UserType::PARENT,
    ]);
    ParentProfile::factory()->create(['user_id' => $selectedParentUser->id]);

    app(StudentService::class)->updateStudent($student, [
        'full_name' => $student->full_name,
        'email' => $student->email,
        'parent_name' => 'Selected Guardian',
        'parent_email' => 'selected-guardian@example.com',
    ]);

    expect(DB::table('student_guardian_relationships')
        ->where('student_id', $student->id)
        ->where('email', 'selected-guardian@example.com')
        ->value('is_primary'))->toBe(1)
        ->and(DB::table('student_guardian_relationships')
            ->where('student_id', $student->id)
            ->where('email', 'selected-guardian@example.com')
            ->value('full_name'))->toBe('Selected Guardian')
        ->and(DB::table('student_guardian_relationships')
            ->where('student_id', $student->id)
            ->count())->toBe(2)
        ->and(DB::table('student_guardian_relationships')
            ->where('student_id', $student->id)
            ->where('email', 'primary-guardian@example.com')
            ->value('is_primary'))->toBe(0);
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
        'email' => 'old-parent@example.com',
    ]);
    $oldParentProfile = ParentProfile::factory()->create([
        'user_id' => $oldParentUser->id,
        'full_name' => 'Old Parent',
        'email_snapshot' => 'old-parent@example.com',
    ]);
    $oldParentUser->createToken('Old parent portal token');
    $oldRelationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Old Parent',
        'relationship_type' => 'guardian',
        'email' => 'old-parent@example.com',
        'is_primary' => true,
    ]])[0];
    app(GuardianAccessGrantWriter::class)->grant($oldRelationship, isPrimaryPortalAccount: true);

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
            ->exists())->toBeTrue()
        ->and(DB::table('student_guardian_relationships')->where('student_id', $student->id)->count())->toBe(2)
        ->and(DB::table('student_guardian_relationships')->where('id', $oldRelationship->id)->value('full_name'))->toBe('Old Parent')
        ->and(DB::table('student_guardian_relationships')
            ->where('student_id', $student->id)
            ->where('email', 'new-parent@example.com')
            ->where('is_primary', true)
            ->exists())->toBeTrue()
        ->and(DB::table('guardian_access_grants')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->count())->toBe(1)
        ->and(DB::table('guardian_access_grants')
            ->where('guardian_relationship_id', $oldRelationship->id)
            ->value('status'))->toBe('revoked');

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
    app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => $parentProfile->full_name,
        'email' => $parentUser->email,
        'is_primary' => true,
    ]]);

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

    Notification::assertNotSentTo($parentUser, ResetPassword::class);
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
