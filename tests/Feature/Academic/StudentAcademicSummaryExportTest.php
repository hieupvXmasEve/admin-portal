<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Exports\StudentAcademicSummaryExport;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Issue 04 — the context-bar Export action.
 *
 * Two seams: the export presenter (the workbook the academic-summary export
 * produces, asserted by value so it survives row reordering) and the route
 * wiring (the real file download replaces the dead "coming soon" toast).
 */

/**
 * Flatten every cell the export writes into one list of strings, so a test
 * can assert a value is present without pinning it to a row/column position.
 *
 * @param  array<int, array<int, mixed>>  $rows
 * @return array<int, string>
 */
function flattenExportCells(array $rows): array
{
    return collect($rows)
        ->flatten()
        ->map(fn ($value): string => (string) $value)
        ->all();
}

/**
 * A representative graduation contract (mirrors getGraduationData()'s shape).
 *
 * @return array<string, mixed>
 */
function sampleGraduationContract(): array
{
    return [
        'credit_summary' => [
            'total_required' => 120,
            'total_earned' => 60,
            'remaining' => 60,
            'completion_percentage' => 50.0,
        ],
        'requirements' => [
            'core_credits' => ['required' => 90, 'earned' => 50, 'status' => 'in_progress'],
            'elective_credits' => ['required' => 30, 'earned' => 10, 'status' => 'in_progress'],
            'internship' => ['required' => true, 'completed' => false, 'status' => 'pending'],
            'thesis' => ['required' => true, 'completed' => false, 'status' => 'pending'],
            'english_requirement' => ['required' => true, 'completed' => true, 'status' => 'completed'],
        ],
        'graduation_status' => [
            'ready_to_graduate' => false,
            'expected_graduation' => null,
            'risks' => ['internship_pending', 'thesis_pending'],
            'risk_level' => 'medium',
        ],
        'progress_timeline' => [
            'current_semester' => ['semester' => 'Fall 2026', 'enrolled_credits' => 12, 'status' => 'enrolled'],
            'projected_completion' => ['semesters_remaining' => 4, 'projected_date' => '2028-06-01', 'on_track' => true],
        ],
    ];
}

it('composes identity, academic standing, graduation progress and transcript into the workbook', function () {
    $export = new StudentAcademicSummaryExport(
        student: [
            'student_id' => 'AUS118115',
            'full_name' => 'Tran Thi Sinh Vien',
            'program' => 'Bachelor of IT',
            'specialization' => 'Software Engineering',
            'campus' => 'Saigon',
            'status' => 'intake_course',
            'intake' => 1,
        ],
        graduation: sampleGraduationContract(),
        cumulative: ['gpa' => 83.0, 'academic_standing' => 'normal', 'credits_earned' => 60.0],
        courses: [
            ['code' => 'CS101', 'name' => 'Intro to CS', 'semester' => 'Fall 2026', 'credits' => 3.0, 'percentage' => 78.0, 'grade' => 'D'],
            ['code' => 'ENG201', 'name' => 'Academic English', 'semester' => 'Fall 2026', 'credits' => 3.0, 'percentage' => 88.0, 'grade' => 'HD'],
        ],
    );

    $cells = flattenExportCells($export->array());

    // Identity + program context.
    expect($cells)->toContain('AUS118115')
        ->and($cells)->toContain('Tran Thi Sinh Vien')
        ->and($cells)->toContain('Bachelor of IT')
        ->and($cells)->toContain('Software Engineering');

    // Academic standing (formatted GPA + standing).
    expect($cells)->toContain('83.00')
        ->and($cells)->toContain('Normal');

    // Graduation progress.
    expect($cells)->toContain('50.0%')   // completion percentage
        ->and($cells)->toContain('No');  // ready_to_graduate=false

    // Transcript rows (one per academic record).
    expect($cells)->toContain('CS101')
        ->and($cells)->toContain('Intro to CS')
        ->and($cells)->toContain('ENG201')
        ->and($cells)->toContain('Academic English');
});

it('lists every graduation requirement with a human-readable label and status', function () {
    $export = new StudentAcademicSummaryExport(
        student: ['student_id' => 'AUS1', 'full_name' => 'A B', 'status' => 'intake_course'],
        graduation: sampleGraduationContract(),
        cumulative: null,
        courses: [],
    );

    $cells = flattenExportCells($export->array());

    expect($cells)->toContain('Core Credits')
        ->and($cells)->toContain('Elective Credits')
        ->and($cells)->toContain('Internship')
        ->and($cells)->toContain('Thesis/Capstone')
        ->and($cells)->toContain('English Proficiency');

    // Statuses are rendered in title case, not raw snake_case.
    expect($cells)->toContain('In Progress')
        ->and($cells)->toContain('Pending')
        ->and($cells)->toContain('Completed');
});

it('falls back to N/A for a student with no finalized GPA yet', function () {
    $export = new StudentAcademicSummaryExport(
        student: ['student_id' => 'AUS2', 'full_name' => 'No Gpa', 'status' => 'intake_pre_uni_gc'],
        graduation: sampleGraduationContract(),
        cumulative: null,
        courses: [],
    );

    $cells = flattenExportCells($export->array());

    expect($cells)->toContain('N/A');
});

/**
 * Build a Student with curriculum + a passing record so the export route has
 * real data to compose, and bind a user with the given permission set.
 */
function exportableStudent(): Student
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create(['code' => 'EXP2026', 'name' => 'Export 2026']);
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => 'EXP000001',
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
            'curriculum_version_id' => $curriculumVersion->id,
        ]);

    $unit = Unit::factory()->create(['code' => 'CORE101', 'name' => 'Core One', 'credit_points' => 3.0]);
    CurriculumUnit::factory()->create([
        'curriculum_version_id' => $curriculumVersion->id,
        'unit_id' => $unit->id,
        'type' => 'core',
    ]);

    return $student;
}

function actAsExportUser(array $permissions): User
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

it('downloads an academic-summary xlsx for the student', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-28 10:00:00'));
    Excel::fake();

    $student = exportableStudent();
    $user = actAsExportUser(['view_student_summary']);

    actingAs($user)
        ->get(route('students.academic-summary.export', $student))
        ->assertOk();

    Excel::assertDownloaded(
        'academic_summary_EXP000001_2026-06-28_10-00-00.xlsx',
        fn (StudentAcademicSummaryExport $export): bool => collect($export->array())
            ->flatten()
            ->map(fn ($value): string => (string) $value)
            ->contains('EXP000001'),
    );

    Carbon::setTestNow();
});

it('forbids the export without view_student_summary', function () {
    $student = exportableStudent();
    $user = actAsExportUser([]);

    // assertForbidden is sufficient: the can:view_student_summary middleware
    // short-circuits before the controller, so no workbook is ever built.
    actingAs($user)
        ->get(route('students.academic-summary.export', $student))
        ->assertForbidden();
});
