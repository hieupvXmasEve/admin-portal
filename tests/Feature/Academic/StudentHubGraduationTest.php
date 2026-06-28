<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\StudentAcademicSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Issue 04 — the (re-enabled) Graduation tab.
 *
 * Locks the Query-seam contract that backs the tab: requirements and progress
 * toward the degree (credit summary, per-requirement status, readiness/risk,
 * and the projection timeline), scoped to this one student.
 */

/**
 * Add a curriculum unit of the given type carrying a unit with credit points.
 */
function addCurriculumUnit(CurriculumVersion $version, string $code, string $name, float $creditPoints, string $type): Unit
{
    $unit = Unit::factory()->create(['code' => $code, 'name' => $name, 'credit_points' => $creditPoints]);

    CurriculumUnit::factory()->create([
        'curriculum_version_id' => $version->id,
        'unit_id' => $unit->id,
        'type' => $type,
    ]);

    return $unit;
}

/**
 * Record a passed, completed academic record so the unit counts as earned
 * (getGraduationTracker counts completion_status=completed + is_passed).
 */
function recordPassingCredit(Student $student, Unit $unit, Semester $semester, float $creditsEarned): void
{
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $student->campus_id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'program_id' => $student->program_id,
        'campus_id' => $student->campus_id,
        'course_offering_id' => $offering->id,
        'credit_points' => $creditsEarned,
        'credit_points_earned' => $creditsEarned,
        'completion_status' => 'completed',
        'grade_status' => 'final',
        'is_passed' => true,
    ]);
}

/**
 * A student on a curriculum with 100 required credits (70 core incl. English,
 * 30 elective), having passed the 60-credit core unit and the 10-credit
 * English unit — 70 earned, internship + thesis still outstanding.
 *
 * @return array{0: Student, 1: Semester}
 */
function graduationTrackerStudent(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create(['code' => 'GRAD2026', 'name' => 'Grad 2026']);
    $version = CurriculumVersion::factory()->forProgram($program)->withEffectiveSemester($semester)->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => 'GRAD00001',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
            'curriculum_version_id' => $version->id,
        ]);

    $core = addCurriculumUnit($version, 'CORE101', 'Core Foundations', 60.0, 'core');
    $english = addCurriculumUnit($version, 'ENG101', 'Academic English', 10.0, 'core');
    addCurriculumUnit($version, 'ELEC201', 'Elective Topics', 30.0, 'elective');

    recordPassingCredit($student, $core, $semester, 60.0);
    recordPassingCredit($student, $english, $semester, 10.0);

    return [$student, $semester];
}

it('builds the graduation contract: credits, per-requirement status, readiness and timeline', function () {
    [$student] = graduationTrackerStudent();

    $grad = app(StudentAcademicSummaryService::class)->getGraduationData($student);

    // Credit summary: required = all curriculum credit, earned = passing records.
    expect((float) $grad['credit_summary']['total_required'])->toBe(100.0)
        ->and((float) $grad['credit_summary']['total_earned'])->toBe(70.0)
        ->and((float) $grad['credit_summary']['remaining'])->toBe(30.0)
        ->and((float) $grad['credit_summary']['completion_percentage'])->toBe(70.0);

    // Per-requirement: core (incl. English) fully earned, electives untouched.
    expect((float) $grad['requirements']['core_credits']['required'])->toBe(70.0)
        ->and((float) $grad['requirements']['core_credits']['earned'])->toBe(70.0)
        ->and((float) $grad['requirements']['elective_credits']['required'])->toBe(30.0)
        ->and((float) $grad['requirements']['elective_credits']['earned'])->toBe(0.0);

    // English passed (unit code carries ENG); internship + thesis still pending.
    expect($grad['requirements']['english_requirement']['completed'])->toBeTrue()
        ->and($grad['requirements']['english_requirement']['status'])->toBe('completed')
        ->and($grad['requirements']['internship']['completed'])->toBeFalse()
        ->and($grad['requirements']['internship']['status'])->toBe('pending')
        ->and($grad['requirements']['thesis']['completed'])->toBeFalse();

    // Readiness + risk: not ready (internship/thesis missing), two risks → medium.
    expect($grad['graduation_status']['ready_to_graduate'])->toBeFalse()
        ->and($grad['graduation_status']['risks'])->toContain('internship_pending')
        ->and($grad['graduation_status']['risks'])->toContain('thesis_pending')
        ->and($grad['graduation_status']['risk_level'])->toBe('medium');

    // Projection timeline is present for the tab to render.
    expect($grad['progress_timeline'])->toHaveKeys(['current_semester', 'projected_completion']);
});

it('counts only this student credits toward graduation, never another student', function () {
    [$student, $semester] = graduationTrackerStudent();

    $otherCampus = Campus::factory()->create();
    $other = Student::factory()->forCampus($otherCampus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);

    // Another student passes the same-named curriculum unit; must not leak in.
    $strayUnit = Unit::factory()->create(['code' => 'CORE101X', 'credit_points' => 99.0]);
    recordPassingCredit($other, $strayUnit, $semester, 99.0);

    $grad = app(StudentAcademicSummaryService::class)->getGraduationData($student);

    expect((float) $grad['credit_summary']['total_earned'])->toBe(70.0);
});

/**
 * Bind a user + campus session and mock the resolved permission set.
 */
function actAsGraduationUser(array $permissions): User
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

it('renders the enabled Graduation tab through the Hub for a permitted viewer', function () {
    [$student] = graduationTrackerStudent();
    $user = actAsGraduationUser(['view_student_summary']);

    actingAs($user)
        ->get(route('students.academic-summary.graduation', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('students/AcademicSummary/Graduation')
            ->has('graduation.credit_summary')
            ->has('graduation.requirements')
            ->has('graduation.graduation_status')
            ->where('graduation.credit_summary.total_required', fn ($value): bool => (float) $value === 100.0));
});

it('forbids the Graduation tab without view_student_summary', function () {
    [$student] = graduationTrackerStudent();
    $user = actAsGraduationUser([]);

    actingAs($user)
        ->get(route('students.academic-summary.graduation', $student))
        ->assertForbidden();
});
