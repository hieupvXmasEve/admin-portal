<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * ADR 0013 phase C: the Course Statistics per-offering assessment-scores
 * page is retired. Old links/bookmarks redirect to the Course Offering
 * Cockpit's scores tab, which renders the same GetCourseOfferingScoresQuery
 * grid the retired page used.
 */
beforeEach(function () {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->unit = Unit::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn([
        'view_course_offering',
        'view_attendance',
    ]);
    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    $this->syllabus = SyllabusTemplate::factory()->create([
        'unit_id' => $this->unit->id,
    ]);

    $this->offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
        'syllabus_template_id' => $this->syllabus->id,
        'course_status' => 'in_progress',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ]);

    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    CourseRegistration::create([
        'student_id' => $this->student->id,
        'course_offering_id' => $this->offering->id,
        'semester_id' => $this->offering->semester_id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3.0,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0.00,
        'is_retake_paid' => 'no',
    ]);

    $component = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $this->syllabus->id,
        'weight' => 100,
        'code' => 'EXAM',
    ]);
    $this->detail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $component->id,
        'name' => 'Final Exam',
        'weight' => 1,
    ]);
    AssessmentComponentDetailScore::create([
        'student_id' => $this->student->id,
        'course_offering_id' => $this->offering->id,
        'assessment_component_detail_id' => $this->detail->id,
        'percentage_score' => 82.5,
        'score_status' => 'final',
        'score_excluded' => false,
    ]);
});

it('redirects the retired assessment-scores route to the cockpit scores tab', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get("/course-statistics/{$this->offering->id}/assessment-scores")
        ->assertRedirect(route(CourseOfferingRoutes::SHOW, [
            'courseOffering' => $this->offering,
            'tab' => 'scores',
        ]));
});

it('renders the same scores grid in the cockpit scores tab that the retired page used', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $this->offering), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
            'X-Inertia-Partial-Component' => 'course-offerings/Show',
            'X-Inertia-Partial-Data' => 'scoresData',
        ])
        ->assertOk()
        ->assertJsonPath('component', 'course-offerings/Show')
        ->assertJsonPath('props.scoresData.statistics.total_students', 1)
        ->assertJsonPath('props.scoresData.assessment_details.0.detail_name', 'Final Exam')
        ->assertJsonPath('props.scoresData.scores_grid.0.student_id', $this->student->student_id)
        ->assertJsonPath('props.scoresData.scores_grid.0.scores.0.percentage_score', '82.50')
        ->assertJsonPath('props.scoresData.scores_grid.0.component_totals.0.percentage_score', 82.5);
});
