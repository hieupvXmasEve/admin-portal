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
use App\Modules\Academic\Exports\StudentAcademicSummaryExport;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Modules\Academic\Progression\Queries\GetStudentAcademicSummaryExportQuery;
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

it('uses canonical transcript outcomes while retaining legacy-only final outcomes in the export evidence', function () {
    $student = exportableStudent();
    $semester = Semester::factory()->create(['name' => 'Export Evidence Semester']);
    $canonicalUnit = Unit::factory()->create(['code' => 'CAN101', 'name' => 'Canonical Unit']);
    $legacyUnit = Unit::factory()->create(['code' => 'LEG101', 'name' => 'Legacy Unit']);
    $transcriptOnlyUnit = Unit::factory()->create(['code' => 'TRN101', 'name' => 'Transcript Only Unit']);
    $canonicalOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $canonicalUnit->id,
        'campus_id' => $student->campus_id,
    ]);
    $legacyOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $legacyUnit->id,
        'campus_id' => $student->campus_id,
    ]);
    $transcriptOnlyOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $transcriptOnlyUnit->id,
        'campus_id' => $student->campus_id,
    ]);
    $canonicalRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $canonicalUnit->id,
        'program_id' => $student->program_id,
        'campus_id' => $student->campus_id,
        'course_offering_id' => $canonicalOffering->id,
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'final_percentage' => 70.0,
        'final_letter_grade' => 'C',
        'credit_points_earned' => 3.0,
        'is_passed' => true,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'unit_id' => $legacyUnit->id,
        'program_id' => $student->program_id,
        'campus_id' => $student->campus_id,
        'course_offering_id' => $legacyOffering->id,
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'final_percentage' => 65.0,
        'final_letter_grade' => 'D',
        'credit_points_earned' => 3.0,
        'is_passed' => true,
    ]);
    TranscriptEntry::query()->create([
        'course_result_id' => $canonicalRecord->id,
        'student_id' => $student->id,
        'course_offering_id' => $canonicalOffering->id,
        'semester_id' => $semester->id,
        'unit_id' => $canonicalUnit->id,
        'program_id' => $student->program_id,
        'campus_id' => $student->campus_id,
        'attempt_number' => 1,
        'final_percentage' => 90.0,
        'final_letter_grade' => 'A',
        'credit_points' => 3.0,
        'credit_points_earned' => 3.0,
        'quality_points' => 270.0,
        'is_passed' => true,
        'excluded_from_gpa' => false,
        'affects_academic_standing' => true,
        'affects_graduation_requirement' => true,
        'satisfies_prerequisite' => true,
        'finalized_at' => now(),
    ]);
    TranscriptEntry::query()->create([
        'course_result_id' => 999999,
        'student_id' => $student->id,
        'course_offering_id' => $transcriptOnlyOffering->id,
        'semester_id' => $semester->id,
        'unit_id' => $transcriptOnlyUnit->id,
        'program_id' => $student->program_id,
        'campus_id' => $student->campus_id,
        'attempt_number' => 1,
        'final_percentage' => 88.0,
        'final_letter_grade' => 'B',
        'credit_points' => 3.0,
        'credit_points_earned' => 3.0,
        'quality_points' => 264.0,
        'is_passed' => true,
        'excluded_from_gpa' => false,
        'affects_academic_standing' => true,
        'affects_graduation_requirement' => true,
        'satisfies_prerequisite' => true,
        'finalized_at' => now(),
    ]);

    $data = app(GetStudentAcademicSummaryExportQuery::class)->handle(
        (int) $student->id,
        [
            'student_id' => $student->student_id,
            'full_name' => $student->full_name,
            'campus' => $student->campus?->name,
            'intake' => $student->intake,
        ],
        $student->expected_graduation_date?->toDateString(),
    );

    $courses = collect($data['courses'])->keyBy('code');

    expect($courses['CAN101']['percentage'])->toBe(90.0)
        ->and($courses['CAN101']['grade'])->toBe('A')
        ->and($courses['LEG101']['percentage'])->toBe(65.0)
        ->and($courses['LEG101']['grade'])->toBe('D')
        ->and($courses['TRN101']['percentage'])->toBe(88.0)
        ->and($courses['TRN101']['grade'])->toBe('B');
});
