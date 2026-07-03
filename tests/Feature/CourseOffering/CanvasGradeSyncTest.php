<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CanvasCourseMapping;
use App\Models\CanvasIntegration;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Services\Canvas\CanvasApiService;
use App\Services\Canvas\CanvasGradeSyncService;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

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

    $this->grantPermissions = function (array $permissions): void {
        $permissionService = Mockery::mock(PermissionService::class);
        $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);

        app()->forgetInstance(PermissionService::class);
        app()->singleton(PermissionService::class, fn () => $permissionService);
    };

    ($this->grantPermissions)(['view_course_offering', 'sync_course_grades']);
});

/**
 * Builds a course offering with a Canvas-synced assessment detail, a mapped
 * Canvas course, and one registered student whose student_id matches the
 * mocked Canvas SIS user id "AUS001".
 */
function makeSyncableOffering(object $context, array $offeringOverrides = [], array $syllabusOverrides = []): array
{
    $syllabus = SyllabusTemplate::factory()->create(array_merge([
        'unit_id' => $context->unit->id,
    ], $syllabusOverrides));

    $offering = CourseOffering::factory()->create(array_merge([
        'semester_id' => $context->semester->id,
        'unit_id' => $context->unit->id,
        'campus_id' => $context->campus->id,
        'syllabus_template_id' => $syllabus->id,
        'course_status' => 'in_progress',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ], $offeringOverrides));

    $component = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'is_canvas_synced' => true,
        'canvas_assignment_group_id' => 'group-1',
    ]);

    $detail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $component->id,
        'canvas_assignment_id' => 'assign-1',
        'max_points' => 100,
    ]);

    $integration = CanvasIntegration::query()->create([
        'canvas_url' => 'https://canvas.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);

    $mapping = CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $integration->id,
        'canvas_course_id' => 'canvas-course-'.$offering->id,
        'canvas_course_name' => 'Canvas Course '.$offering->id,
        'course_offering_id' => $offering->id,
        'sync_status' => 'mapped',
    ]);

    $student = Student::factory()->forCampus($context->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $context->semester->id,
        'student_id' => 'AUS001',
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $context->semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);

    return compact('offering', 'syllabus', 'component', 'detail', 'mapping', 'student');
}

/**
 * Mocks CanvasApiService so grade sync sees one Canvas student (matching
 * "AUS001"), one graded submission of $score/100, and a course total of
 * $courseTotal — deterministic, no real Canvas calls.
 */
function mockCanvasApi(float $score, float $courseTotal): void
{
    $api = Mockery::mock(CanvasApiService::class);

    $api->shouldReceive('getCourseStudents')->andReturn([
        ['id' => 'canvas-user-1', 'sis_user_id' => 'AUS001', 'login_id' => null],
    ]);

    $api->shouldReceive('getAllStudentSubmissions')->andReturn([
        [
            'user_id' => 'canvas-user-1',
            'assignment_id' => 'assign-1',
            'score' => $score,
            'grade' => 'B',
            'workflow_state' => 'graded',
            'submitted_at' => '2026-01-10T00:00:00Z',
            'graded_at' => '2026-01-11T00:00:00Z',
        ],
    ]);

    $api->shouldReceive('getStudentEnrollment')->andReturn([
        'grades' => ['current_score' => $courseTotal],
    ]);

    app()->forgetInstance(CanvasApiService::class);
    app()->instance(CanvasApiService::class, $api);
}

it('previews a Canvas grade sync without writing anything', function () {
    ['offering' => $offering, 'mapping' => $mapping, 'student' => $student] = makeSyncableOffering($this);
    mockCanvasApi(score: 80, courseTotal: 85);

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW, $offering), [
            'student_ids' => [$student->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.summary.total_changes', 2); // 1 cell + 1 course total
    $response->assertJsonPath('data.summary.students_changed', 1);
    $response->assertJsonPath('data.changes.0.new_points', 80);
    $response->assertJsonPath('data.changes.0.old_points', null);
    $response->assertJsonPath('data.course_totals.0.new_percentage', 85);
    $response->assertJsonPath('data.course_totals.0.changed', true);

    expect(AssessmentComponentDetailScore::count())->toBe(0);
    expect(AcademicRecord::where('student_id', $student->id)->count())->toBe(0);
});

it('still reports the Canvas course total when it matches the current record, flagged unchanged', function () {
    ['offering' => $offering, 'mapping' => $mapping, 'student' => $student] = makeSyncableOffering($this);
    mockCanvasApi(score: 80, courseTotal: 85);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $offering->unit_id,
        'campus_id' => $this->campus->id,
        'final_percentage' => 85,
    ]);

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW, $offering), [
            'student_ids' => [$student->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.course_totals.0.old_percentage', 85);
    $response->assertJsonPath('data.course_totals.0.new_percentage', 85);
    $response->assertJsonPath('data.course_totals.0.changed', false);
    // The course total didn't move, but the cell still did — student counts as changed.
    $response->assertJsonPath('data.summary.students_changed', 1);
});

it('badges a changed cell whose current score_status is disputed but still includes it', function () {
    ['offering' => $offering, 'mapping' => $mapping, 'student' => $student, 'detail' => $detail] = makeSyncableOffering($this);
    mockCanvasApi(score: 90, courseTotal: 90);

    AssessmentComponentDetailScore::create([
        'assessment_component_detail_id' => $detail->id,
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'points_earned' => 70,
        'percentage_score' => 70,
        'status' => 'graded',
        'score_status' => 'disputed',
    ]);

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW, $offering), [
            'student_ids' => [$student->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.changes.0.is_disputed', true);
    $response->assertJsonPath('data.changes.0.old_points', 70);
    $response->assertJsonPath('data.changes.0.new_points', 90);
});

it('lists unmatched students with a reason instead of dropping them', function () {
    ['offering' => $offering, 'mapping' => $mapping, 'student' => $student] = makeSyncableOffering($this);

    // Canvas has no student matching this one's student_id.
    $api = Mockery::mock(CanvasApiService::class);
    $api->shouldReceive('getCourseStudents')->andReturn([]);
    $api->shouldReceive('getAllStudentSubmissions')->andReturn([]);
    app()->forgetInstance(CanvasApiService::class);
    app()->instance(CanvasApiService::class, $api);

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW, $offering), [
            'student_ids' => [$student->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.summary.students_unmatched', 1);
    $response->assertJsonPath('data.unmatched.0.student_id', $student->id);
    expect($response->json('data.unmatched.0.reason'))->not->toBeEmpty();
});

it('omits the course total change when the syllabus uses a custom grading engine', function () {
    ['offering' => $offering, 'mapping' => $mapping, 'student' => $student] = makeSyncableOffering(
        $this,
        syllabusOverrides: ['grading_scheme' => ['engine' => 'custom_affine']]
    );
    mockCanvasApi(score: 80, courseTotal: 85);

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW, $offering), [
            'student_ids' => [$student->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.course_totals', []);
    $response->assertJsonPath('data.summary.total_changes', 1); // cell change only
});

it('applies a Canvas grade sync by re-fetching Canvas and writing only selected students', function () {
    ['offering' => $offering, 'mapping' => $mapping, 'student' => $student] = makeSyncableOffering($this);
    mockCanvasApi(score: 80, courseTotal: 85);

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_APPLY, $offering), [
            'student_ids' => [$student->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.students_synced', 1);

    $score = AssessmentComponentDetailScore::where('student_id', $student->id)->first();
    expect((float) $score->points_earned)->toBe(80.0);

    $record = AcademicRecord::where('student_id', $student->id)->where('course_offering_id', $offering->id)->first();
    expect((float) $record->final_percentage)->toBe(85.0);
});

it('leaves unselected students untouched when applying a filtered sync', function () {
    ['offering' => $offering, 'mapping' => $mapping, 'student' => $studentA, 'detail' => $detail] = makeSyncableOffering($this);

    $studentB = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
        'student_id' => 'AUS002',
    ]);
    CourseRegistration::create([
        'student_id' => $studentB->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);

    $api = Mockery::mock(CanvasApiService::class);
    $api->shouldReceive('getCourseStudents')->andReturn([
        ['id' => 'canvas-user-1', 'sis_user_id' => 'AUS001', 'login_id' => null],
        ['id' => 'canvas-user-2', 'sis_user_id' => 'AUS002', 'login_id' => null],
    ]);
    $api->shouldReceive('getAllStudentSubmissions')->andReturn([
        ['user_id' => 'canvas-user-1', 'assignment_id' => 'assign-1', 'score' => 80, 'grade' => 'B', 'workflow_state' => 'graded'],
        ['user_id' => 'canvas-user-2', 'assignment_id' => 'assign-1', 'score' => 60, 'grade' => 'D', 'workflow_state' => 'graded'],
    ]);
    $api->shouldReceive('getStudentEnrollment')->andReturn(['grades' => ['current_score' => 80]]);
    app()->forgetInstance(CanvasApiService::class);
    app()->instance(CanvasApiService::class, $api);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_APPLY, $offering), [
            'student_ids' => [$studentA->id],
        ])
        ->assertOk();

    expect(AssessmentComponentDetailScore::where('student_id', $studentA->id)->exists())->toBeTrue();
    expect(AssessmentComponentDetailScore::where('student_id', $studentB->id)->exists())->toBeFalse();
});

it('rejects sync on a completed offering', function () {
    ['offering' => $offering, 'student' => $student] = makeSyncableOffering($this, ['course_status' => 'completed']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW, $offering), ['student_ids' => [$student->id]])
        ->assertStatus(409);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_APPLY, $offering), ['student_ids' => [$student->id]])
        ->assertStatus(409);
});

it('returns 403 when the user lacks sync_course_grades', function () {
    ($this->grantPermissions)(['view_course_offering']);
    ['offering' => $offering, 'student' => $student] = makeSyncableOffering($this);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW, $offering), ['student_ids' => [$student->id]])
        ->assertForbidden();
});

it('rejects student_ids that are not on the offering roster', function () {
    ['offering' => $offering] = makeSyncableOffering($this);
    $outsider = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
        'student_id' => 'AUS999',
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_SYNC_GRADES_PREVIEW, $offering), [
            'student_ids' => [$outsider->id],
        ])
        ->assertStatus(422);
});

it('exposes the sync_grades action only for a non-completed, mapped, permitted offering', function () {
    ['offering' => $offering] = makeSyncableOffering($this);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $offering))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('operational_state.available_actions', fn ($actions) => collect($actions)->contains('action', 'sync_grades')));
});

it('hides the sync_grades action on a completed offering', function () {
    ['offering' => $offering] = makeSyncableOffering($this, ['course_status' => 'completed']);
    ($this->grantPermissions)(['view_course_offering', 'sync_course_grades', 'recalculate_course_offering']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $offering))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('operational_state.available_actions', fn ($actions) => ! collect($actions)->contains('action', 'sync_grades')));
});

it('hides the sync_grades action when the Canvas course is not mapped', function () {
    ['offering' => $offering, 'mapping' => $mapping] = makeSyncableOffering($this);
    $mapping->update(['sync_status' => 'pending']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $offering))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('operational_state.available_actions', fn ($actions) => ! collect($actions)->contains('action', 'sync_grades')));
});

it('hides the sync_grades action for a user lacking sync_course_grades', function () {
    ['offering' => $offering] = makeSyncableOffering($this);
    ($this->grantPermissions)(['view_course_offering']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $offering))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('operational_state.available_actions', fn ($actions) => ! collect($actions)->contains('action', 'sync_grades')));
});

it('leaves the bulk sync path (no student filter) behaviour unchanged', function () {
    ['offering' => $offering, 'mapping' => $mapping, 'student' => $student] = makeSyncableOffering($this);
    mockCanvasApi(score: 80, courseTotal: 85);

    $result = app(CanvasGradeSyncService::class)->syncCourseGrades($mapping->fresh());

    expect($result['success'])->toBeTrue();
    expect($result['students_synced'])->toBe(1);
    expect(AssessmentComponentDetailScore::where('student_id', $student->id)->exists())->toBeTrue();
});
