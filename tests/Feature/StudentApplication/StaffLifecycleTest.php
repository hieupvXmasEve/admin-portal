<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\User;
use App\Services\PermissionService;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

const SA_CSRF = 'student-application-test-csrf';

/**
 * The lifecycle permissions a staff member needs for the staff seam. Approve and
 * reject are gated by the campus-scoped StudentApplicationPolicy (slice 03), so
 * the mocked PermissionService grants those codes here; campus-scoping itself is
 * exercised against the real service in AuthorizationTest.
 */
const SA_PERMISSIONS = [
    'view_student_application',
    'create_student_application',
    'edit_student_application',
    'delete_student_application',
    'approve_student_application',
    'reject_student_application',
    'revoke_student_application',
];

beforeEach(function () {
    // ProgramMappingService caches campus/program/curriculum code→id lookups; the
    // array cache persists across tests in one process, so flush to avoid an
    // earlier test's (rolled-back) ids resolving for this test's fresh fixtures.
    Cache::flush();

    $campus = Campus::factory()->create();
    $this->campus = $campus;

    // HandleInertiaRequests::share() and the permission Gate both resolve
    // PermissionService — return the lifecycle permissions for any user.
    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn(SA_PERMISSIONS);
    $this->app->singleton(PermissionService::class, fn () => $permissionService);

    $this->app->singleton('campus', fn () => $campus);
    session([
        '_token' => SA_CSRF,
        'current_campus_id' => $campus->id,
    ]);
});

function makeStaff(): User
{
    return User::factory()->create(['type' => UserType::STAFF]);
}

/**
 * Set up the program/curriculum mapping fixtures so an application's CRM codes
 * (campus_code / intended_program / intake) resolve during approval.
 *
 * @return array{program: Program, curriculum: CurriculumVersion}
 */
function setupApprovalMapping(Campus $campus): array
{
    $program = Program::factory()->create(['code' => 'IT']);
    $curriculum = CurriculumVersion::factory()->forProgram($program)->create([
        'version_code' => 'FA25',
    ]);

    // assignStudentRole() looks up the student role by code.
    Role::factory()->create(['code' => 'sinh_vien', 'name' => 'Sinh viên']);

    return ['program' => $program, 'curriculum' => $curriculum];
}

function makePendingApplication(Campus $campus, array $overrides = []): StudentApplication
{
    return StudentApplication::factory()->pending()->create(array_merge([
        'campus_code' => $campus->code,
        'intended_program' => 'IT',
        'intake' => 'FA25',
        'student_code' => 'S'.fake()->unique()->numerify('#######'),
        'parent_email' => null,
    ], $overrides));
}

it('approves a pending application, atomically creating user, student, role, and audit trail', function () {
    setupApprovalMapping($this->campus);
    $staff = makeStaff();
    $application = makePendingApplication($this->campus, [
        'email' => 'applicant@example.com',
        'student_code' => 'S7654321',
    ]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('student-applications.show', $application));

    // Application moved to enrolled and records who approved it.
    $application->refresh();
    expect($application->status)->toBe(StudentApplication::STATUS_ENROLLED);
    expect($application->approved_by)->toBe($staff->id);
    expect($application->approved_at)->not->toBeNull();
    expect($application->student_id)->not->toBeNull();

    // Student created with the CRM-issued code and applicant email.
    $student = Student::find($application->student_id);
    expect($student)->not->toBeNull();
    expect($student->student_id)->toBe('S7654321');
    expect($student->email)->toBe('applicant@example.com');

    // User created with a secure password — never the old hardcoded default.
    $user = User::where('email', 'applicant@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->type)->toBe(UserType::STUDENT);
    expect(Hash::check('123456', $user->password))->toBeFalse();

    // Student role assigned within the campus.
    expect(DB::table('campus_user_roles')->where('user_id', $user->id)->exists())->toBeTrue();

    // Activity log records the staff causer against the application.
    expect(
        DB::table('activity_log')
            ->where('subject_id', $application->id)
            ->where('subject_type', StudentApplication::class)
            ->where('causer_id', $staff->id)
            ->exists()
    )->toBeTrue();
});

it('rolls back the entire approval when student creation fails, leaving no user or student and the application pending', function () {
    setupApprovalMapping($this->campus);
    $staff = makeStaff();
    $application = makePendingApplication($this->campus, [
        'email' => 'rollback@example.com',
        'student_code' => 'SDUP0001',
    ]);

    // Force a mid-approve failure: a Student already owns this student_code, so
    // creating the new Student violates the unique constraint after the User is
    // created — the whole transaction must roll back.
    Student::factory()->create([
        'student_id' => 'SDUP0001',
        'intake' => 0,
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);
    $studentsBefore = Student::count();

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->from(route('student-applications.show', $application))
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('student-applications.show', $application));
    $response->assertSessionHas('error');

    // No half-created records.
    expect(User::where('email', 'rollback@example.com')->exists())->toBeFalse();
    expect(Student::count())->toBe($studentsBefore);
    expect(Student::where('email', 'rollback@example.com')->exists())->toBeFalse();

    // Application untouched and still pending.
    $application->refresh();
    expect($application->status)->toBe(StudentApplication::STATUS_PENDING);
    expect($application->student_id)->toBeNull();
    expect($application->approved_by)->toBeNull();
    expect($application->approved_at)->toBeNull();
});

it('rejects a pending application with a reason and creates no student', function () {
    $staff = makeStaff();
    $application = makePendingApplication($this->campus, ['email' => 'reject@example.com']);
    $studentsBefore = Student::count();

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->post(route('student-applications.reject', $application), [
            'rejected_reason' => 'Incomplete dossier.',
        ]);

    $response->assertRedirect(route('student-applications.show', $application));

    $application->refresh();
    expect($application->status)->toBe(StudentApplication::STATUS_REJECTED);
    expect($application->rejected_by)->toBe($staff->id);
    expect($application->rejected_at)->not->toBeNull();
    expect($application->rejected_reason)->toBe('Incomplete dossier.');
    expect($application->student_id)->toBeNull();

    expect(Student::count())->toBe($studentsBefore);

    expect(
        DB::table('activity_log')
            ->where('subject_id', $application->id)
            ->where('subject_type', StudentApplication::class)
            ->where('causer_id', $staff->id)
            ->exists()
    )->toBeTrue();
});

it('rejection requires a reason', function () {
    $staff = makeStaff();
    $application = makePendingApplication($this->campus);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->from(route('student-applications.show', $application))
        ->post(route('student-applications.reject', $application), [])
        ->assertSessionHasErrors('rejected_reason');

    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);
});

it('cannot approve an application that is not pending', function () {
    setupApprovalMapping($this->campus);
    $staff = makeStaff();
    $application = makePendingApplication($this->campus);
    $application->update(['status' => StudentApplication::STATUS_REJECTED]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->from(route('student-applications.show', $application))
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ]);

    $response->assertSessionHas('error');
    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_REJECTED);
    expect(Student::count())->toBe(0);
});

/**
 * Approve an application through the staff HTTP seam and return the refreshed model.
 */
function approveViaHttp(StudentApplication $application, User $staff): StudentApplication
{
    test()->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    return $application->fresh();
}

it('revokes an enrolled application within the safe window, tearing down student, user, and roles', function () {
    setupApprovalMapping($this->campus);
    $staff = makeStaff();
    $application = makePendingApplication($this->campus, [
        'email' => 'revoke-me@example.com',
        'student_code' => 'SREV0001',
    ]);

    $application = approveViaHttp($application, $staff);
    $studentId = $application->student_id;
    $userId = Student::find($studentId)->user_id;

    expect($studentId)->not->toBeNull();
    expect(DB::table('campus_user_roles')->where('user_id', $userId)->exists())->toBeTrue();

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->post(route('student-applications.revoke', $application));

    $response->assertRedirect(route('student-applications.show', $application));

    // Application returned to pending and records who revoked it.
    $application->refresh();
    expect($application->status)->toBe(StudentApplication::STATUS_PENDING);
    expect($application->student_id)->toBeNull();
    expect($application->revoked_by)->toBe($staff->id);
    expect($application->revoked_at)->not->toBeNull();
    expect($application->approved_by)->toBeNull();
    expect($application->approved_at)->toBeNull();

    // Student, User, and campus roles are torn down.
    expect(Student::find($studentId))->toBeNull();
    expect(User::find($userId))->toBeNull();
    expect(DB::table('campus_user_roles')->where('user_id', $userId)->exists())->toBeFalse();

    // Activity log records the revoking staff causer.
    expect(
        DB::table('activity_log')
            ->where('subject_id', $application->id)
            ->where('subject_type', StudentApplication::class)
            ->where('causer_id', $staff->id)
            ->exists()
    )->toBeTrue();
});

it('blocks revoke once the student has downstream activity, deleting nothing', function () {
    setupApprovalMapping($this->campus);
    $staff = makeStaff();
    $application = makePendingApplication($this->campus, [
        'email' => 'has-activity@example.com',
        'student_code' => 'SREV0002',
    ]);

    $application = approveViaHttp($application, $staff);
    $student = Student::find($application->student_id);

    // Downstream activity: a recorded academic action for the student.
    $student->actionLogs()->create([
        'action_type' => 'ACADEMIC_DEFER',
        'reason' => 'Recorded activity that must block revoke.',
        'changed_by_user_id' => $staff->id,
    ]);

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->from(route('student-applications.show', $application))
        ->post(route('student-applications.revoke', $application));

    $response->assertRedirect(route('student-applications.show', $application));
    $response->assertSessionHas('error');

    // Nothing torn down; still enrolled.
    $application->refresh();
    expect($application->status)->toBe(StudentApplication::STATUS_ENROLLED);
    expect($application->student_id)->toBe($student->id);
    expect(Student::find($student->id))->not->toBeNull();
    expect(User::find($student->user_id))->not->toBeNull();
});

it('blocks revoke once the student has logged in', function () {
    setupApprovalMapping($this->campus);
    $staff = makeStaff();
    $application = makePendingApplication($this->campus, [
        'email' => 'logged-in@example.com',
        'student_code' => 'SREV0004',
    ]);

    $application = approveViaHttp($application, $staff);
    $student = Student::find($application->student_id);

    // A login is downstream activity too: the student's account has signed in.
    User::whereKey($student->user_id)->update(['last_login_at' => now()]);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->from(route('student-applications.show', $application))
        ->post(route('student-applications.revoke', $application))
        ->assertSessionHas('error');

    $application->refresh();
    expect($application->status)->toBe(StudentApplication::STATUS_ENROLLED);
    expect(Student::find($student->id))->not->toBeNull();
});

it('cannot revoke an application that is not enrolled', function () {
    $staff = makeStaff();
    $application = makePendingApplication($this->campus);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->from(route('student-applications.show', $application))
        ->post(route('student-applications.revoke', $application))
        ->assertSessionHas('error');

    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);
});

it('allows an application to be approved again after a revoke', function () {
    setupApprovalMapping($this->campus);
    $staff = makeStaff();
    $application = makePendingApplication($this->campus, [
        'email' => 'again@example.com',
        'student_code' => 'SREV0003',
    ]);

    $application = approveViaHttp($application, $staff);

    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->post(route('student-applications.revoke', $application))
        ->assertRedirect();

    expect($application->fresh()->status)->toBe(StudentApplication::STATUS_PENDING);

    // Re-approve: a new Student is created with the same CRM-issued code.
    $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('student-applications.show', $application));

    $application->refresh();
    expect($application->status)->toBe(StudentApplication::STATUS_ENROLLED);
    expect($application->student_id)->not->toBeNull();
    expect(Student::where('student_id', 'SREV0003')->count())->toBe(1);
});

it('creates a manual application as pending with no auto-approve', function () {
    $staff = makeStaff();

    $payload = [
        'full_name' => 'Manual Applicant',
        'email' => 'manual@example.com',
        'campus_code' => $this->campus->code,
        'student_code' => 'SMAN0001',
        'phone' => '0900000000',
    ];

    $response = $this->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', SA_CSRF)
        ->post(route('student-applications.store'), $payload);

    $application = StudentApplication::where('email', 'manual@example.com')->first();
    expect($application)->not->toBeNull();
    expect($application->status)->toBe(StudentApplication::STATUS_PENDING);
    expect($application->student_id)->toBeNull();

    $response->assertRedirect(route('student-applications.show', $application));
});

it('lists applications filtered by status', function () {
    $staff = makeStaff();
    makePendingApplication($this->campus);
    StudentApplication::factory()->rejected()->create([
        'campus_code' => $this->campus->code,
    ]);

    $this->actingAs($staff)
        ->get(route('student-applications.index', ['status' => StudentApplication::STATUS_PENDING]))
        ->assertOk();
});
