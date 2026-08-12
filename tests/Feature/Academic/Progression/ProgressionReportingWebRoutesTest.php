<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\GpaCalculation;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for the GPA-history, performance-dashboard,
 * progression-audit, and placement surfaces before those classes move into
 * owned Academic Progression and their Campus/Semester/Program reads move
 * behind owner-side seams. The `options` assertions pin the reference payloads
 * those seams must reproduce.
 */
beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
        Authorize::class,
    ]);

    Gate::before(fn (): AccessResponse => AccessResponse::allow());

    $this->campus = Campus::factory()->create(['name' => 'Alpha campus']);
    session(['current_campus_id' => $this->campus->id]);
    actingAs(User::factory()->create());

    $this->older = Semester::factory()->create([
        'code' => '2025FA',
        'name' => 'Fall 2025',
        'start_date' => '2025-08-01',
        'end_date' => '2025-12-31',
        'is_active' => false,
    ]);
    $this->newer = Semester::factory()->create([
        'code' => '2026SP',
        'name' => 'Spring 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-05-31',
        'is_active' => false,
    ]);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function progressionReportGpa(array $attributes): GpaCalculation
{
    return GpaCalculation::query()->create(array_merge([
        'semester_gpa' => 70,
        'cumulative_gpa' => 70,
        'semester_quality_points' => 0,
        'cumulative_quality_points' => 0,
        'semester_credit_points' => 0,
        'cumulative_credit_points' => 0,
        'semester_credit_points_earned' => 0,
        'cumulative_credit_points_earned' => 0,
        'academic_standing' => 'normal',
        'is_current' => true,
    ], $attributes));
}

describe('gpa history', function (): void {
    it('lists calculations for the selected campus with newest-first reference options', function (): void {
        $student = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'intake' => 2026,
            'intake_semester_id' => $this->newer->id,
        ]);

        $calculation = progressionReportGpa([
            'student_id' => $student->id,
            'semester_id' => $this->newer->id,
            // The history report only lists finalized calculations.
            'is_finalized' => true,
        ]);

        $response = get(route('academic.gpa.history'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Academic/Gpa/History')
                ->where('gpaRecords.data.0.id', $calculation->id)
                ->where('filters.campus_id', $this->campus->id)
                ->where('filters.per_page', 15)
                ->where('options.campuses.0.name', 'Alpha campus')
                ->has('options.standings', 4)
            );

        // Newest-first, and every semester stays selectable. Asserted by
        // position rather than index because StudentFactory creates a semester
        // of its own.
        $codes = collect($response->viewData('page')['props']['options']['semesters'])
            ->pluck('code')
            ->all();

        expect(array_search('2026SP', $codes, true))
            ->toBeLessThan(array_search('2025FA', $codes, true));
    });

    it('keeps an explicit semester filter in the echoed filter state', function (): void {
        get(route('academic.gpa.history', ['semester_id' => $this->older->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Echoed straight from the query string, so it stays a string.
                ->where('filters.semester_id', (string) $this->older->id)
            );
    });

    it('rejects a semester filter that does not exist', function (): void {
        get(route('academic.gpa.history', ['semester_id' => 999999]))
            ->assertSessionHasErrors('semester_id');
    });

    it('streams the filtered export', function (): void {
        get(route('academic.gpa.history.export'))
            ->assertOk()
            ->assertDownload();
    });
});

describe('performance dashboard', function (): void {
    it('defaults to the newest semester when none is active and renders reference options', function (): void {
        get(route('academic.students.performance'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Academic/Performance/Dashboard')
                ->where('filters.campus_id', $this->campus->id)
                ->where('filters.semester_id', $this->newer->id)
                ->where('options.semesters.0.code', '2026SP')
                ->where('options.campuses.0.name', 'Alpha campus')
                ->has('stats')
            );
    });

    it('honours an explicit semester selection', function (): void {
        get(route('academic.students.performance', ['semester_id' => $this->older->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.semester_id', $this->older->id)
            );
    });
});

describe('academic progression audit', function (): void {
    it('renders the audit report with its filter options', function (): void {
        get(route('reports.academic-progression.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Reports/AcademicProgressionAudit/Index')
                ->where('statistics', null)
                ->where('filters.per_page', 25)
                ->where('options.semesters.0.code', '2026SP')
                ->where('options.semesters.0.end_date', '2026-05-31')
                ->where('options.campuses.0.name', 'Alpha campus')
                ->has('options.eventTypes')
                ->has('options.triggerSources')
                ->has('options.courseStages', 2)
            );
    });

    it('loads statistics once a semester is selected', function (): void {
        get(route('reports.academic-progression.index', ['semester_id' => $this->newer->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.semester_id', (string) $this->newer->id)
                ->has('statistics')
            );
    });

    it('renders the missing-documents report', function (): void {
        get(route('reports.academic-progression.missing-documents'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Reports/AcademicProgressionAudit/MissingDocuments')
                ->where('filters.per_page', 25)
            );
    });

    it('renders the missing-decisions report', function (): void {
        get(route('reports.academic-progression.missing-decisions'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Reports/AcademicProgressionAudit/MissingDecisions')
                ->where('filters.per_page', 25)
            );
    });

    it('renders the defer-returns watchlist report', function (): void {
        get(route('reports.academic-progression.defer-returns'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Reports/AcademicProgressionAudit/DeferReturns')
                ->where('filters.per_page', 25)
                ->where('options.semesters.0.code', '2026SP')
                ->has('counts')
            );
    });

    it('streams the defer-returns export as a spreadsheet', function (): void {
        get(route('reports.academic-progression.defer-returns.export'))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });

    it('names the CSV export after the selected semester code', function (): void {
        get(route('reports.academic-progression.export', ['semester_id' => $this->newer->id]))
            ->assertOk()
            ->assertHeader(
                'content-disposition',
                'attachment; filename=academic_progression_audit_2026SP_'.now()->format('Y-m-d').'.csv',
            );
    });

    it('requires a semester for the CSV export', function (): void {
        get(route('reports.academic-progression.export'))
            ->assertSessionHasErrors('semester_id');
    });
});

describe('student placement', function (): void {
    // ADR-0049 retired the standalone placement page; the route now redirects
    // to the Hub lifecycle tab so old bookmarks keep working.
    it('redirects the retired placement page to the Hub lifecycle tab', function (): void {
        $program = Program::factory()->create();
        $student = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'program_id' => $program->id,
            'intake' => 2026,
            'intake_semester_id' => $this->newer->id,
        ]);

        get(route('students.placement.index', $student))
            ->assertRedirect(route('students.academic-summary.lifecycle', $student->id));
    });
});
