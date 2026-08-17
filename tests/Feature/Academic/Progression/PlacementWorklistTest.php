<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

function grantChangeStudentStatus(User $user, Campus $campus): void
{
    $role = Role::firstOrCreate(['code' => 'placement_worklist_test'], ['name' => 'Placement Worklist Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'change_student_status'],
        ['name' => 'change_student_status', 'display_name' => 'change_student_status', 'module' => 'students', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);
}

function makeWorklistStudent(Campus $campus, array $studentAttributes = [], ?string $studyStage = null, string $enrollmentStatus = 'pending'): Student
{
    $semester = Semester::factory()->create();
    $student = Student::factory()
        ->for($campus)
        ->for(Program::factory())
        ->create([
            'intake' => $semester->id,
            'intake_semester_id' => $semester->id,
            'intake_mode' => 'sequential',
            ...$studentAttributes,
        ]);

    // Same source shape the real approve flow materializes, so
    // MaterializeProgramEnrollmentAction reuses this row instead of
    // creating a duplicate during placement initialize.
    ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => $enrollmentStatus,
        'study_stage' => $studyStage,
        'source_type' => ProgramEnrollment::LEGACY_STUDENT_SOURCE,
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);

    return $student;
}

it('lists only unclassified current-campus students', function (): void {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $user = User::factory()->create();
    grantChangeStudentStatus($user, $campus);
    session(['current_campus_id' => $campus->id]);
    actingAs($user);

    $unclassified = makeWorklistStudent($campus);
    makeWorklistStudent($campus, [], 'intake_pre_uni_gc', 'active');
    makeWorklistStudent($campus, [], null, 'withdrawn');
    makeWorklistStudent($campus, [], null, 'graduated');
    makeWorklistStudent($otherCampus);

    get(route('students.placement-worklist.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Academic/PlacementWorklist/Index')
            ->where('students.total', 1)
            ->where('students.data.0.id', $unclassified->id)
            ->has('programs')
            ->has('placementOptions.semesters')
            ->has('placementOptions.ieltsScoreThreshold')
        );
});

it('narrows by search and program filter', function (): void {
    $campus = Campus::factory()->create();
    $user = User::factory()->create();
    grantChangeStudentStatus($user, $campus);
    session(['current_campus_id' => $campus->id]);
    actingAs($user);

    $target = makeWorklistStudent($campus, ['full_name' => 'Nguyen Van Placement', 'student_id' => 'PW0001']);
    makeWorklistStudent($campus, ['full_name' => 'Tran Thi Other', 'student_id' => 'PW0002']);

    get(route('students.placement-worklist.index', ['search' => 'PW0001']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('students.total', 1)
            ->where('students.data.0.student_id', 'PW0001')
        );

    get(route('students.placement-worklist.index', ['program_id' => $target->program_id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('students.total', 1)
            ->where('students.data.0.id', $target->id)
        );
});

it('hides a student whose tie-break-winning primary enrollment is classified despite an older unclassified primary row', function (): void {
    $campus = Campus::factory()->create();
    $user = User::factory()->create();
    grantChangeStudentStatus($user, $campus);
    session(['current_campus_id' => $campus->id]);
    actingAs($user);

    // Older primary row unclassified (deferred, so it evades the primary+active
    // unique index); newer primary row classified — highest id wins, student
    // must not reappear in the worklist.
    $student = makeWorklistStudent($campus, [], null, 'deferred');
    ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => 'active',
        'study_stage' => 'intake_course',
        'source_type' => 'test_secondary_primary',
        'source_id' => $student->id + 100000,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);

    get(route('students.placement-worklist.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('students.total', 0));
});

it('redirects to campus selection when no campus is selected', function (): void {
    $campus = Campus::factory()->create();
    $user = User::factory()->create();
    grantChangeStudentStatus($user, $campus);
    actingAs($user);

    get(route('students.placement-worklist.index'))
        ->assertRedirect(route('select-campus.index'));
});

it('forbids the worklist for a user without change_student_status', function (): void {
    $campus = Campus::factory()->create();
    session(['current_campus_id' => $campus->id]);
    actingAs(User::factory()->create());

    get(route('students.placement-worklist.index'))->assertForbidden();
});

it('drops a student from the worklist after placement initialize', function (): void {
    $campus = Campus::factory()->create();
    $user = User::factory()->create();
    grantChangeStudentStatus($user, $campus);
    session(['current_campus_id' => $campus->id]);
    actingAs($user);

    $semester = Semester::factory()->create();
    $student = makeWorklistStudent($campus, ['intake_semester_id' => $semester->id]);

    get(route('students.placement-worklist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('students.total', 1));

    post(route('students.placement.initialize', $student), [
        '_token' => csrf_token(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'has_ielts' => false,
        'english_level' => 2,
    ])->assertSessionHasNoErrors();

    expect(ProgramEnrollment::query()->sole()->study_stage)->toBe('intake_pre_uni_gc');

    get(route('students.placement-worklist.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('students.total', 0));
});
