<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for the student directory create form, export, and
 * the three API endpoints, none of which were covered before the controller's
 * validation, response envelopes, and Catalog reads were reworked.
 */
beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
        Authorize::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HN']);
    $this->program = Program::factory()->create(['name' => 'Software Engineering']);
    $this->semester = Semester::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_student', 'create_student', 'edit_student']);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    actingAs($this->user);
});

function directoryStudent(Campus $campus, Program $program, Semester $semester, array $overrides = []): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'intake' => 1,
            'intake_semester_id' => $semester->id,
            ...$overrides,
        ]);
}

describe('create form', function (): void {
    it('redirects to campus selection when no campus is selected', function (): void {
        session()->forget('current_campus_id');

        get(route(StudentRoutes::CREATE))
            ->assertRedirect(route('select-campus.index'));
    });

    it('renders the program, specialization, and curriculum-version options', function (): void {
        $specialization = Specialization::factory()->create([
            'program_id' => $this->program->id,
            'name' => 'Backend',
        ]);
        CurriculumVersion::factory()->create([
            'program_id' => $this->program->id,
            'specialization_id' => $specialization->id,
        ]);

        get(route(StudentRoutes::CREATE))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Students/Create')
                ->has('programs')
                ->has('specializations')
                ->has('curriculumVersions')
                ->where('current_campus_id', $this->campus->id)
            );
    });
});

describe('export', function (): void {
    it('rejects a missing format or scope', function (): void {
        get(route(StudentRoutes::EXPORT))
            ->assertSessionHasErrors(['format', 'scope']);
    });

    it('downloads a workbook named after the campus code', function (): void {
        directoryStudent($this->campus, $this->program, $this->semester);

        $response = get(route(StudentRoutes::EXPORT, ['format' => 'xlsx', 'scope' => 'all']));

        $response->assertOk();
        expect($response->headers->get('content-disposition'))
            ->toContain('students_HN_');
    });

    it('does not export when no campus is selected', function (): void {
        session()->forget('current_campus_id');

        $response = get(route(StudentRoutes::EXPORT, ['format' => 'xlsx', 'scope' => 'all']));

        $response->assertRedirect();
        expect($response->headers->get('content-disposition'))->toBeNull();
    });
});

describe('api search', function (): void {
    it('returns matching campus students in the flat success envelope', function (): void {
        directoryStudent($this->campus, $this->program, $this->semester, [
            'student_id' => 'SE600001',
            'full_name' => 'Pham Minh D',
        ]);
        directoryStudent($this->campus, $this->program, $this->semester, [
            'student_id' => 'SE600002',
            'full_name' => 'Hoang Van E',
        ]);

        $response = get(route('api.admin.students.apiSearch', ['query' => 'Pham Minh']));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Students retrieved successfully')
            ->assertJsonPath('data.items.0.student_id', 'SE600001')
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.total', 1);
    });

    it('rejects an unsupported status filter', function (): void {
        get(route('api.admin.students.apiSearch', ['status' => 'not-a-status']))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'status');
    });

    it('reflects the live study_stage, not the stale students.status column', function (): void {
        $student = directoryStudent($this->campus, $this->program, $this->semester, [
            'student_id' => 'SE600010',
            'status' => 'intake_pre_uni_gc',
        ]);
        MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);
        ProgramEnrollment::query()->where('student_id', $student->id)->update(['study_stage' => 'intake_course']);

        get(route('api.admin.students.apiSearch', ['query' => 'SE600010']))
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'intake_course');
    });
});

describe('api show', function (): void {
    it('returns a single student in the flat success envelope', function (): void {
        $student = directoryStudent($this->campus, $this->program, $this->semester, [
            'student_id' => 'SE600003',
        ]);

        get(route('api.admin.students.apiShow', ['student' => $student->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Student retrieved successfully')
            ->assertJsonPath('data.student_id', 'SE600003')
            ->assertJsonPath('data.program.id', $this->program->id);
    });

    it('reflects the live study_stage, not the stale students.status column', function (): void {
        $student = directoryStudent($this->campus, $this->program, $this->semester, [
            'student_id' => 'SE600011',
            'status' => 'intake_pre_uni_gc',
        ]);
        MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);
        ProgramEnrollment::query()->where('student_id', $student->id)->update(['study_stage' => 'intake_course']);

        get(route('api.admin.students.apiShow', ['student' => $student->id]))
            ->assertOk()
            ->assertJsonPath('data.status', 'intake_course');
    });
});

describe('api lookup by student codes', function (): void {
    it('returns template variables for the requested codes', function (): void {
        directoryStudent($this->campus, $this->program, $this->semester, [
            'student_id' => 'SE600004',
            'full_name' => 'Do Thi F',
        ]);

        post(route('api.admin.students.getByStudentIds'), ['student_ids' => ['SE600004']])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.students.0.student_id', 'SE600004')
            ->assertJsonPath('data.students.0.fullname', 'DO THI F')
            ->assertJsonPath('data.students.0.template_variables.program', 'Software Engineering');
    });

    it('reflects the live study_stage, not the stale students.status column', function (): void {
        $student = directoryStudent($this->campus, $this->program, $this->semester, [
            'student_id' => 'SE600012',
            'status' => 'intake_pre_uni_gc',
        ]);
        MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);
        ProgramEnrollment::query()->where('student_id', $student->id)->update(['study_stage' => 'intake_course']);

        post(route('api.admin.students.getByStudentIds'), ['student_ids' => ['SE600012']])
            ->assertOk()
            ->assertJsonPath('data.students.0.status', 'intake_course')
            ->assertJsonPath('data.students.0.template_variables.status', 'intake_course');
    });

    it('never reaches the lookup when no campus is selected', function (): void {
        // The web middleware stack redirects to campus selection first, so the
        // controller's own "No campus selected" 400 branch stays unreachable.
        session()->forget('current_campus_id');

        post(route('api.admin.students.getByStudentIds'), ['student_ids' => ['SE600004']])
            ->assertRedirect(route('select-campus.index'));
    });

    it('requires the student code list', function (): void {
        post(route('api.admin.students.getByStudentIds'), [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.field', 'student_ids');
    });
});
