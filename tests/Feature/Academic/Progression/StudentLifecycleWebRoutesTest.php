<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Characterization coverage for the student enrollments overview, the yearly
 * lifecycle analysis report, and the student-actions import surfaces before
 * those controllers move into owned Academic Progression.
 */
beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
        Authorize::class,
    ]);

    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    actingAs(User::factory()->create());

    $this->spring = Semester::factory()->create([
        'code' => '2026SP',
        'name' => 'Spring 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-05-31',
        // Pinned: SemesterFactory randomises is_active, and the lifecycle
        // report's default selection depends on whether one is active.
        'is_active' => false,
    ]);
    $this->fall = Semester::factory()->create([
        'code' => '2026FA',
        'name' => 'Fall 2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
        'is_active' => false,
    ]);
});

describe('student enrollments overview', function (): void {
    it('redirects to campus selection when no campus is selected', function (): void {
        session()->forget('current_campus_id');

        get(route('student-enrollments.index'))
            ->assertRedirect(route('select-campus.index'));
    });

    it('lists only enrollments of students on the selected campus', function (): void {
        $onCampus = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'intake' => 1,
            'intake_semester_id' => $this->spring->id,
            'student_id' => 'S-ON-CAMPUS',
            'full_name' => 'Nguyen Van A',
        ]);
        $offCampus = Student::factory()->create([
            'campus_id' => $this->otherCampus->id,
            'intake' => 1,
            'intake_semester_id' => $this->spring->id,
            'student_id' => 'S-OFF-CAMPUS',
        ]);
        Enrollment::factory()->create([
            'student_id' => $onCampus->id,
            'semester_id' => $this->spring->id,
        ]);
        Enrollment::factory()->create([
            'student_id' => $offCampus->id,
            'semester_id' => $this->spring->id,
        ]);

        get(route('student-enrollments.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Students/enrollments/Index')
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.student.student_id', 'S-ON-CAMPUS')
            );
    });

    it('filters by semester and echoes the sanitized filter state', function (): void {
        $student = Student::factory()->create(['campus_id' => $this->campus->id, 'intake' => 1, 'intake_semester_id' => $this->spring->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'semester_id' => $this->spring->id,
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'semester_id' => $this->fall->id,
        ]);

        get(route('student-enrollments.index', ['semester_id' => $this->fall->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.semester_id', $this->fall->id)
                ->where('filters.semester_id', (string) $this->fall->id)
            );
    });

    it('filters by student name or code search', function (): void {
        $match = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'intake' => 1,
            'intake_semester_id' => $this->spring->id,
            'student_id' => 'S-MATCH',
            'full_name' => 'Tran Thi B',
        ]);
        $other = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'intake' => 1,
            'intake_semester_id' => $this->spring->id,
            'student_id' => 'S-OTHER',
            'full_name' => 'Le Van C',
        ]);
        Enrollment::factory()->create(['student_id' => $match->id, 'semester_id' => $this->spring->id]);
        Enrollment::factory()->create(['student_id' => $other->id, 'semester_id' => $this->spring->id]);

        get(route('student-enrollments.index', ['search' => 'Tran Thi']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('enrollments.data', 1)
                ->where('enrollments.data.0.student.student_id', 'S-MATCH')
                ->where('filters.search', 'Tran Thi')
            );
    });

    it('rejects an unknown semester filter', function (): void {
        get(route('student-enrollments.index', ['semester_id' => 999999]))
            ->assertSessionHasErrors('semester_id');
    });
});

describe('student lifecycle yearly analysis', function (): void {
    it('renders the cohort matrix plus the semester and status option lists', function (): void {
        Student::factory()->create([
            'campus_id' => $this->campus->id,
            'intake' => 1,
            'intake_semester_id' => $this->spring->id,
            'status' => 'intake_course',
        ]);

        get(route('reports.student-lifecycle-yearly.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Reports/StudentLifecycleYearlyAnalysis/Index')
                ->has('matrix.semesters')
                ->has('matrix.cohorts')
                ->has('matrix.events')
                ->has('matrix.totals')
                ->has('statusOptions.semesters')
                ->has('statusOptions.statuses')
                ->where('meta.campus_id', $this->campus->id)
            );
    });

    it('defaults the selected semester to the latest one when none is active', function (): void {
        get(route('reports.student-lifecycle-yearly.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('statusFilters.selected_semester_id', $this->fall->id)
                ->where('statusFilters.status_per_page', 25)
            );
    });

    it('honours an explicitly selected semester and status filter', function (): void {
        get(route('reports.student-lifecycle-yearly.index', [
            'selected_semester_id' => $this->spring->id,
            'current_status' => 'graduated',
            'status_per_page' => 10,
        ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('statusFilters.selected_semester_id', $this->spring->id)
                ->where('statusFilters.current_status', 'graduated')
                ->where('statusFilters.status_per_page', 10)
            );
    });

    it('rejects an unknown semester on index and a missing semester on export', function (): void {
        get(route('reports.student-lifecycle-yearly.index', ['selected_semester_id' => 999999]))
            ->assertSessionHasErrors('selected_semester_id');

        get(route('reports.student-lifecycle-yearly.export'))
            ->assertSessionHasErrors('selected_semester_id');
    });

    it('downloads the lifecycle status export for a semester', function (): void {
        $response = get(route('reports.student-lifecycle-yearly.export', [
            'selected_semester_id' => $this->spring->id,
        ]));

        $response->assertOk();
        expect($response->headers->get('content-disposition'))
            ->toContain('student_lifecycle_status_2026SP');
    });
});

describe('student actions import surfaces', function (): void {
    it('renders the import page', function (): void {
        get(route('reports.student-actions.import'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Reports/StudentActionsImport')
            );
    });

    it('downloads the import template workbook', function (): void {
        $response = get(route('reports.student-actions.import.template'));

        $response->assertOk();
        expect($response->headers->get('content-disposition'))
            ->toContain('student_actions_import_template');
    });
});
