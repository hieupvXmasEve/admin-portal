<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Services\PermissionService;
use App\Services\StudentAcademicSummaryService;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Build a Student with enough academic context for the Hub overview:
 * folded Show-page fields (additional info), emergency contacts, and
 * recent course registrations.
 */
function hubOverviewStudent(): Student
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create(['code' => 'HUB2026', 'name' => 'Hub 2026']);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => 'HUB000001',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
            'national_id' => '0123456789',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '0900000000',
            'high_school_name' => 'Hub High School',
            'high_school_graduation_year' => 2024,
            'entrance_exam_score' => 27.5,
            'admission_notes' => 'Transferred from partner college',
        ]);

    $unit = Unit::factory()->create(['code' => 'HUB101', 'name' => 'Intro to Hub', 'credit_points' => 3.0]);

    // Six registrations across distinct offerings + dates so we can assert "last 5, newest first".
    // (course_registrations are unique per student+offering+semester.)
    foreach (range(1, 6) as $i) {
        $offering = CourseOffering::query()->create([
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'section_code' => 'S'.$i,
            'max_capacity' => 30,
            'current_enrollment' => 1,
            'delivery_mode' => 'in_person',
            'is_active' => true,
            'enrollment_status' => 'open',
        ]);

        CourseRegistration::query()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $semester->id,
            'registration_status' => 'completed',
            'registration_date' => now()->subDays($i),
            'registration_method' => 'advisor',
            'credit_hours' => 3,
            'credit_points' => 3,
            'attempt_number' => 1,
            'is_retake' => false,
        ]);
    }

    return $student;
}

/**
 * Bind a user + campus session and mock the permission set the request resolves.
 */
function actAsHubUser(array $permissions): User
{
    $user = User::factory()->create();
    $campus = Campus::factory()->create();

    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    return $user;
}

it('includes the folded Show-page fields and recent registrations in the full overview contract', function () {
    $student = hubOverviewStudent();

    $overview = app(StudentAcademicSummaryService::class)->getOverviewData($student);

    // Additional info folded from the retired Show page.
    expect($overview['additional_info']['high_school_name'])->toBe('Hub High School')
        ->and($overview['additional_info']['admission_notes'])->toBe('Transferred from partner college');

    // Recent registrations: capped at 5, newest first.
    expect($overview)->toHaveKey('recent_registrations')
        ->and($overview['recent_registrations'])->toHaveCount(5);

    $dates = collect($overview['recent_registrations'])->pluck('registration_date')->all();
    expect($dates)->toBe(collect($dates)->sortDesc()->values()->all());

    $first = $overview['recent_registrations'][0];
    expect($first)->toHaveKeys(['id', 'course_offering_id', 'unit_name', 'registration_status', 'registration_date']);
});

it('drops sensitive fields from the reduced read-only overview contract', function () {
    $student = hubOverviewStudent();

    $reduced = app(StudentAcademicSummaryService::class)->getOverviewData($student, full: false);

    // Basic identity + program context survive.
    expect($reduced['student_info']['full_name'])->toBe($student->full_name)
        ->and($reduced)->toHaveKey('program_info')
        ->and($reduced)->toHaveKey('academic_stats');

    // Sensitive PII and the folded blocks are gone.
    expect($reduced['student_info'])->not->toHaveKey('national_id')
        ->and($reduced['student_info'])->not->toHaveKey('emergency_contact_name')
        ->and($reduced)->not->toHaveKey('additional_info')
        ->and($reduced)->not->toHaveKey('recent_registrations');
});

it('renders the full Hub overview for an act-capable academic officer', function () {
    $student = hubOverviewStudent();
    $user = actAsHubUser(['view_student_summary', 'change_student_status', 'view_student_action']);

    actingAs($user)
        ->get(route('students.academic-summary.overview', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/AcademicSummary/Overview')
            ->where('can_act', true)
            ->where('overview.student_info.emergency_contact_name', 'Jane Doe')
            ->has('overview.additional_info')
            ->has('overview.recent_registrations', 5));
});

it('reads program and lifecycle context from the primary Program Enrollment', function () {
    $student = hubOverviewStudent();
    $program = Program::factory()->create(['code' => 'PROG-ENROLLMENT']);
    $curriculumVersion = CurriculumVersion::factory()->create(['program_id' => $program->id]);
    $semester = Semester::factory()->create(['code' => 'PE2026', 'name' => 'Program Enrollment 2026']);
    $majorSemester = Semester::factory()->create(['code' => 'MAJOR2026', 'name' => 'Major Enrollment 2026']);
    $enrollment = new ProgramEnrollment([
        'student_id' => $student->id,
        'program_id' => $program->id,
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $semester->id,
        'intake_major_semester_id' => $majorSemester->id,
        'enrollment_status' => 'active',
        'study_stage' => 'intake_major',
        'is_primary' => true,
        'source_type' => 'test_program_enrollment',
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);
    $enrollment->save();
    $user = actAsHubUser(['view_student_summary', 'change_student_status']);

    actingAs($user)
        ->get(route('students.academic-summary.overview', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('student.status', 'intake_major')
            ->where('student.program.id', $program->id)
            ->where('overview.student_info.status', 'intake_major')
            ->where('overview.student_info.academic_status', 'active')
            ->where('overview.student_info.intake_semester.id', $semester->id)
            ->where('overview.academic_info.intake_major.code', 'MAJOR2026')
            ->where('overview.program_info.program.id', $program->id)
            ->where('overview.program_info.curriculum_version.id', $curriculumVersion->id));
});

it('shows every Registry Guardian and marks no-email Guardians as unable to receive portal access', function () {
    $student = hubOverviewStudent();
    $user = actAsHubUser(['view_student_summary', 'change_student_status']);
    $relationships = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [
        [
            'full_name' => 'Offline Primary Guardian',
            'relationship_type' => 'mother',
            'email' => null,
            'is_primary' => true,
        ],
        [
            'full_name' => 'Portal Guardian',
            'relationship_type' => 'father',
            'email' => 'portal-guardian@example.test',
            'is_primary' => false,
        ],
    ]);
    app(GuardianAccessGrantWriter::class)->grant($relationships[1], isPrimaryPortalAccount: true);

    actingAs($user)
        ->get(route('students.academic-summary.overview', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('overview.student_info.guardians', 2)
            ->where('overview.student_info.guardians.0.full_name', 'Offline Primary Guardian')
            ->where('overview.student_info.guardians.0.can_receive_access', false)
            ->where('overview.student_info.guardians.0.has_active_access', false)
            ->where('overview.student_info.guardians.1.full_name', 'Portal Guardian')
            ->where('overview.student_info.guardians.1.can_receive_access', true)
            ->where('overview.student_info.guardians.1.has_active_access', true));
});

it('treats view_student_action alone as act-capable and renders the full overview', function () {
    $student = hubOverviewStudent();
    // No change_student_status — verifies the act-capable OR branch (PRD: act gated by
    // change_student_status / view_student_action).
    $user = actAsHubUser(['view_student_summary', 'view_student_action']);

    actingAs($user)
        ->get(route('students.academic-summary.overview', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/AcademicSummary/Overview')
            ->where('can_act', true)
            ->where('overview.student_info.emergency_contact_name', 'Jane Doe')
            ->has('overview.additional_info')
            ->has('overview.recent_registrations', 5));
});

it('renders a reduced read-only Hub overview for a view-only role', function () {
    $student = hubOverviewStudent();
    $user = actAsHubUser(['view_student_summary']);

    actingAs($user)
        ->get(route('students.academic-summary.overview', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/AcademicSummary/Overview')
            ->where('can_act', false)
            ->has('overview.student_info.full_name')
            ->missing('overview.student_info.national_id')
            ->missing('overview.student_info.emergency_contact_name')
            ->missing('overview.additional_info')
            ->missing('overview.recent_registrations'));
});

it('forbids the Hub overview without view_student_summary', function () {
    $student = hubOverviewStudent();
    $user = actAsHubUser([]);

    actingAs($user)
        ->get(route('students.academic-summary.overview', $student))
        ->assertForbidden();
});
