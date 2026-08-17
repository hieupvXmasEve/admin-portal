<?php

declare(strict_types=1);

use App\Models\ApplicationGuardian;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Modules\Admissions\Support\Crm\CrmMappingSettings;
use App\Shared\Support\Enums\UserType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const GUARD_CSRF = 'guardian-test-csrf';

const GUARD_PERMISSIONS = [
    'view_student_application',
    'create_student_application',
    'edit_student_application',
    'delete_student_application',
    'approve_student_application',
    'reject_student_application',
    'revoke_student_application',
];

beforeEach(function () {
    Cache::flush();

    $campus = Campus::factory()->create();
    $this->campus = $campus;

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn(GUARD_PERMISSIONS);
    $this->app->singleton(CampusPermissionReader::class, fn () => $permissionService);

    $this->app->singleton('campus', fn () => $campus);
    session([
        '_token' => GUARD_CSRF,
        'current_campus_id' => $campus->id,
    ]);

    $this->staff = User::factory()->create(['type' => UserType::STAFF]);

    app(CrmMappingSettings::class)->setIntakeCohort(1);
});

function guardianApplication(Campus $campus, array $overrides = []): StudentApplication
{
    return StudentApplication::factory()->pending()->create(array_merge([
        'campus_code' => $campus->code,
        'intended_program' => 'IT',
        'intake' => 'FA25',
        'student_code' => 'S'.fake()->unique()->numerify('#######'),
    ], $overrides));
}

/**
 * Program/curriculum/role fixtures so an application's CRM codes resolve on approve.
 */
function guardianApprovalMapping(): void
{
    $program = Program::factory()->create(['code' => 'IT']);
    $semester = Semester::factory()->create(['code' => 'FA25']);
    CurriculumVersion::factory()->forProgram($program)->create([
        'semester_id' => $semester->id,
        'version_code' => 'IT2025.v1',
    ]);
    Role::factory()->create(['code' => 'sinh_vien', 'name' => 'Sinh viên']);
}

function postGuardian(StudentApplication $application, User $staff, array $payload)
{
    return test()->actingAs($staff)
        ->withHeader('X-CSRF-TOKEN', GUARD_CSRF)
        ->from(route('student-applications.show', $application))
        ->post(route('student-applications.guardians.store', $application), $payload);
}

it('adds a guardian to a pending application and makes the first one primary', function () {
    $application = guardianApplication($this->campus);

    postGuardian($application, $this->staff, [
        'full_name' => 'Jane Doe',
        'relationship' => 'mother',
        'phone' => '0901112222',
        'email' => 'jane@example.com',
        'occupation' => 'Engineer',
    ])->assertRedirect(route('student-applications.show', $application));

    $guardian = $application->guardians()->first();
    expect($guardian)->not->toBeNull();
    expect($guardian->full_name)->toBe('Jane Doe');
    expect($guardian->relationship)->toBe('mother');
    expect($guardian->is_primary)->toBeTrue();
});

it('keeps a second guardian non-primary by default', function () {
    $application = guardianApplication($this->campus);
    ApplicationGuardian::factory()->primary()->forApplication($application)->create();

    postGuardian($application, $this->staff, [
        'full_name' => 'Second Guardian',
        'relationship' => 'father',
    ])->assertRedirect();

    expect($application->guardians()->where('is_primary', true)->count())->toBe(1);
    expect($application->guardians()->count())->toBe(2);
    expect($application->guardians()->where('full_name', 'Second Guardian')->value('is_primary'))->toBeFalsy();
});

it('demotes the previous primary when a new guardian is added as primary', function () {
    $application = guardianApplication($this->campus);
    $first = ApplicationGuardian::factory()->primary()->forApplication($application)->create();

    postGuardian($application, $this->staff, [
        'full_name' => 'New Primary',
        'relationship' => 'guardian',
        'is_primary' => true,
    ])->assertRedirect();

    expect($application->guardians()->where('is_primary', true)->count())->toBe(1);
    expect($first->fresh()->is_primary)->toBeFalse();
    expect($application->guardians()->where('full_name', 'New Primary')->value('is_primary'))->toBeTruthy();
});

it('rejects a guardian with an invalid relationship', function () {
    $application = guardianApplication($this->campus);

    postGuardian($application, $this->staff, [
        'full_name' => 'Bad Relationship',
        'relationship' => 'pet-rock',
    ])->assertSessionHasErrors('relationship');

    expect($application->guardians()->count())->toBe(0);
});

it('updates a guardian on a pending application', function () {
    $application = guardianApplication($this->campus);
    $guardian = ApplicationGuardian::factory()->primary()->forApplication($application)->create([
        'full_name' => 'Old Name',
        'relationship' => 'father',
    ]);

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', GUARD_CSRF)
        ->put(route('student-applications.guardians.update', [$application, $guardian]), [
            'full_name' => 'New Name',
            'relationship' => 'guardian',
        ])
        ->assertRedirect(route('student-applications.show', $application));

    $guardian->refresh();
    expect($guardian->full_name)->toBe('New Name');
    expect($guardian->relationship)->toBe('guardian');
});

it('promotes another guardian to primary when the current primary is updated to non-primary', function () {
    $application = guardianApplication($this->campus);
    $primary = ApplicationGuardian::factory()->primary()->forApplication($application)->create();
    $secondary = ApplicationGuardian::factory()->forApplication($application)->create();

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', GUARD_CSRF)
        ->put(route('student-applications.guardians.update', [$application, $primary]), [
            'full_name' => $primary->full_name,
            'is_primary' => false,
        ])
        ->assertRedirect();

    expect($application->guardians()->where('is_primary', true)->count())->toBe(1);
    expect($secondary->fresh()->is_primary)->toBeTrue();
});

it('removes a guardian and promotes a successor when the primary is removed', function () {
    $application = guardianApplication($this->campus);
    $primary = ApplicationGuardian::factory()->primary()->forApplication($application)->create();
    $secondary = ApplicationGuardian::factory()->forApplication($application)->create();

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', GUARD_CSRF)
        ->delete(route('student-applications.guardians.destroy', [$application, $primary]))
        ->assertRedirect(route('student-applications.show', $application));

    expect(ApplicationGuardian::find($primary->id))->toBeNull();
    expect($application->guardians()->count())->toBe(1);
    expect($secondary->fresh()->is_primary)->toBeTrue();
});

it('forbids changing guardians once the application is no longer pending', function () {
    $application = guardianApplication($this->campus, ['status' => StudentApplication::STATUS_REJECTED]);

    postGuardian($application, $this->staff, [
        'full_name' => 'Too Late',
        'relationship' => 'mother',
    ])->assertSessionHas('error');

    expect($application->guardians()->count())->toBe(0);
});

it('rejects managing a guardian that belongs to another application', function () {
    $application = guardianApplication($this->campus);
    $otherApplication = guardianApplication($this->campus);
    $foreignGuardian = ApplicationGuardian::factory()->primary()->forApplication($otherApplication)->create();

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', GUARD_CSRF)
        ->delete(route('student-applications.guardians.destroy', [$application, $foreignGuardian]))
        ->assertNotFound();

    expect(ApplicationGuardian::find($foreignGuardian->id))->not->toBeNull();
});

it('enforces at most one primary guardian per application at the database level', function () {
    $application = guardianApplication($this->campus);
    ApplicationGuardian::factory()->primary()->forApplication($application)->create();

    expect(fn () => ApplicationGuardian::factory()->primary()->forApplication($application)->create())
        ->toThrow(QueryException::class);
});

it('maps the primary guardian to the student emergency contact and links it as the primary parent on approve', function () {
    guardianApprovalMapping();
    $application = guardianApplication($this->campus, [
        'email' => 'applicant-guardian@example.com',
        'student_code' => 'SGUARD01',
    ]);

    $primary = ApplicationGuardian::factory()->primary()->forApplication($application)->create([
        'full_name' => 'Primary Guardian',
        'relationship' => 'mother',
        'phone' => '0907654321',
        'email' => 'primary-parent@example.com',
    ]);

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', GUARD_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $application->refresh();
    $student = Student::find($application->student_id);
    expect($student)->not->toBeNull();

    // Emergency contact taken from the primary guardian (real name + relationship).
    expect($student->emergency_contact_name)->toBe('Primary Guardian');
    expect($student->emergency_contact_phone)->toBe('0907654321');
    expect($student->emergency_contact_relationship)->toBe('mother');

    // A Parent account is created/linked with the guardian's real name.
    $parentUser = User::where('email', 'primary-parent@example.com')->first();
    expect($parentUser)->not->toBeNull();
    expect($parentUser->type)->toBe(UserType::PARENT);
    expect($parentUser->name)->toBe('Primary Guardian');

    $parentProfile = ParentProfile::where('user_id', $parentUser->id)->first();
    expect($parentProfile)->not->toBeNull();
    expect($parentProfile->full_name)->toBe('Primary Guardian');

    // Pivot carries the real relationship and primary flag (not a hardcoded one).
    $pivot = DB::table('parent_student')
        ->where('parent_id', $parentProfile->id)
        ->where('student_id', $student->id)
        ->first();
    expect($pivot)->not->toBeNull();
    expect($pivot->relationship)->toBe('mother');
    expect((bool) $pivot->is_primary)->toBeTrue();
});

it('promotes an email-bearing guardian to primary parent when the primary guardian has no email', function () {
    guardianApprovalMapping();
    $application = guardianApplication($this->campus, [
        'email' => 'no-email-primary@example.com',
        'student_code' => 'SGUARD03',
    ]);

    // Primary guardian carries the emergency contact but has no email, so it
    // cannot own a Parent login.
    ApplicationGuardian::factory()->primary()->forApplication($application)->create([
        'full_name' => 'No Email Primary',
        'relationship' => 'mother',
        'phone' => '0900000001',
        'email' => null,
    ]);
    ApplicationGuardian::factory()->forApplication($application)->create([
        'full_name' => 'Has Email',
        'relationship' => 'father',
        'email' => 'has-email@example.com',
    ]);

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', GUARD_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $student = Student::find($application->fresh()->student_id);

    // Emergency contact still comes from the primary guardian.
    expect($student->emergency_contact_name)->toBe('No Email Primary');

    // The only linked parent is the email-bearing guardian, and it IS primary —
    // the student is never left with parent links but no primary parent.
    $links = DB::table('parent_student')->where('student_id', $student->id)->get();
    expect($links)->toHaveCount(1);
    expect((bool) $links->first()->is_primary)->toBeTrue();
    expect(User::where('email', 'has-email@example.com')->exists())->toBeTrue();

    $relationships = DB::table('student_guardian_relationships')
        ->where('student_id', $student->id)
        ->orderBy('id')
        ->get();

    expect($relationships)->toHaveCount(2)
        ->and($relationships->firstWhere('full_name', 'No Email Primary')->is_primary)->toBe(1)
        ->and($relationships->firstWhere('full_name', 'Has Email')->is_primary)->toBe(0);

    $accessGrants = DB::table('guardian_access_grants')
        ->where('student_id', $student->id)
        ->get();

    expect($accessGrants)->toHaveCount(1)
        ->and($accessGrants->first()->guardian_relationship_id)
        ->toBe($relationships->firstWhere('full_name', 'Has Email')->id)
        ->and($accessGrants->first()->status)->toBe('active')
        ->and($accessGrants->first()->access_level)->toBe('read_only');
});

it('links multiple guardians as parents on approve with exactly one primary', function () {
    guardianApprovalMapping();
    $application = guardianApplication($this->campus, [
        'email' => 'multi-guardian@example.com',
        'student_code' => 'SGUARD02',
    ]);

    ApplicationGuardian::factory()->primary()->forApplication($application)->create([
        'full_name' => 'Mum',
        'relationship' => 'mother',
        'email' => 'mum@example.com',
    ]);
    ApplicationGuardian::factory()->forApplication($application)->create([
        'full_name' => 'Dad',
        'relationship' => 'father',
        'email' => 'dad@example.com',
    ]);

    $this->actingAs($this->staff)
        ->withHeader('X-CSRF-TOKEN', GUARD_CSRF)
        ->post(route('student-applications.approve', $application), [
            'admission_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $student = Student::find($application->fresh()->student_id);

    $links = DB::table('parent_student')->where('student_id', $student->id)->get();
    expect($links)->toHaveCount(2);
    expect($links->where('is_primary', true)->count())->toBe(1);

    expect(User::where('email', 'mum@example.com')->exists())->toBeTrue();
    expect(User::where('email', 'dad@example.com')->exists())->toBeTrue();
});
