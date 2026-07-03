<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\CanvasCourseMapping;
use App\Models\CanvasIntegration;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Services\Canvas\CanvasApiService;
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

    ($this->grantPermissions)(['view_course_offering', 'recalculate_course_offering']);
});

function makePreviewOffering(object $context, array $overrides = []): CourseOffering
{
    return CourseOffering::factory()->create(array_merge([
        'semester_id' => $context->semester->id,
        'unit_id' => $context->unit->id,
        'campus_id' => $context->campus->id,
        'course_status' => 'in_progress',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ], $overrides));
}

function registerPreviewStudent(object $context, CourseOffering $courseOffering, float $finalPercentage, array $studentOverrides = []): Student
{
    $student = Student::factory()->forCampus($context->campus)->create(array_merge([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $context->semester->id,
    ], $studentOverrides));

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $context->semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $context->semester->id,
        'unit_id' => $courseOffering->unit_id,
        'campus_id' => $context->campus->id,
        'final_percentage' => $finalPercentage,
        'total_present' => 0,
        'total_late' => 0,
        'total_absences' => 0,
        'total_not_recorded' => 0,
        'total_class_sessions' => 0,
    ]);

    return $student;
}

/**
 * Builds a non-Canvas offering with one manually-graded assessment detail,
 * so aggregateManualGrades() genuinely recomputes final_percentage from a
 * score edit (rather than a test poking AcademicRecord.final_percentage
 * directly, which the dry run has no way to distinguish from a no-op).
 */
function makeManuallyGradedOffering(object $context): array
{
    $syllabus = SyllabusTemplate::factory()->create([
        'unit_id' => $context->unit->id,
        'min_grade_threshold' => 60,
    ]);
    $offering = makePreviewOffering($context, ['syllabus_template_id' => $syllabus->id]);
    $component = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'weight' => 100,
    ]);
    $detail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $component->id,
        'weight' => 100,
        'max_points' => 100,
    ]);
    $session = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'status' => 'completed',
        'session_date' => '2026-01-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'session_type' => 'lecture',
        'delivery_mode' => 'in_person',
    ]);

    return compact('offering', 'syllabus', 'component', 'detail', 'session');
}

function gradeStudent(CourseOffering $offering, AssessmentComponentDetail $detail, Student $student, float $percentage): void
{
    AssessmentComponentDetailScore::updateOrCreate(
        ['assessment_component_detail_id' => $detail->id, 'student_id' => $student->id, 'course_offering_id' => $offering->id],
        ['points_earned' => $percentage, 'percentage_score' => $percentage, 'score_status' => 'final', 'score_excluded' => false],
    );
}

it('previews a recalculate without persisting, dispatching, or flipping course_status', function () {
    config(['notification.v2_enabled' => true, 'notification.write_mode' => 'v2']);

    ['offering' => $offering, 'detail' => $detail, 'session' => $session] = makeManuallyGradedOffering($this);
    $student = registerPreviewStudent($this, $offering, 0);
    gradeStudent($offering, $detail, $student, 85);
    Attendance::create([
        'class_session_id' => $session->id,
        'student_id' => $student->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);

    MarkCourseOfferingCompletedAction::run($offering);
    expect(AcademicRecord::where('student_id', $student->id)->first()->is_passed)->toBeTrue();

    // Correct the underlying score after completion — this is what a preview should surface.
    gradeStudent($offering, $detail, $student, 50);

    $publishSpy = Mockery::mock(PublishDomainEventAction::class);
    app()->instance(PublishDomainEventAction::class, $publishSpy);
    $publishSpy->shouldNotReceive('runAfterCommit');

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_RECALCULATE_PREVIEW, $offering));

    $response->assertOk();
    $response->assertJsonPath('data.dry_run', true);
    $response->assertJsonPath('data.record_changes.0.old_final_percentage', 85);
    $response->assertJsonPath('data.record_changes.0.new_final_percentage', 50);
    $response->assertJsonPath('data.record_changes.0.old_is_passed', true);
    $response->assertJsonPath('data.record_changes.0.new_is_passed', false);
    $response->assertJsonPath('data.notification_tiers.0.tier', 'strong');

    // Nothing persisted: the record still shows the pre-recalculate 85%/pass, not the previewed 50%/fail.
    $record = AcademicRecord::where('course_offering_id', $offering->id)->where('student_id', $student->id)->first();
    expect((float) $record->final_percentage)->toBe(85.0)
        ->and($record->is_passed)->toBeTrue();
    expect($offering->fresh()->course_status)->toBe('completed');
});

it('assigns strong/light/none notification tiers correctly on recalculate', function () {
    config(['notification.v2_enabled' => true, 'notification.write_mode' => 'v2']);

    ['offering' => $offering, 'detail' => $detail, 'session' => $session] = makeManuallyGradedOffering($this);

    $strongUser = User::factory()->create();
    $strongStudent = registerPreviewStudent($this, $offering, 0, ['user_id' => $strongUser->id]);

    $lightUser = User::factory()->create();
    $lightStudent = registerPreviewStudent($this, $offering, 0, ['user_id' => $lightUser->id]);

    $noneUser = User::factory()->create();
    $noneStudent = registerPreviewStudent($this, $offering, 0, ['user_id' => $noneUser->id]);

    foreach ([$strongStudent, $lightStudent, $noneStudent] as $student) {
        gradeStudent($offering, $detail, $student, 85);
        Attendance::create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'recording_method' => 'manual',
        ]);
    }

    MarkCourseOfferingCompletedAction::run($offering);

    // strong: pass -> fail. light: score moves but stays passing. none: unchanged.
    gradeStudent($offering, $detail, $strongStudent, 50);
    gradeStudent($offering, $detail, $lightStudent, 80);

    $tiers = [];
    $publishSpy = Mockery::mock(PublishDomainEventAction::class);
    app()->instance(PublishDomainEventAction::class, $publishSpy);
    $publishSpy->shouldReceive('runAfterCommit')
        ->twice()
        ->withArgs(function (DomainEventEnvelope $envelope) use (&$tiers) {
            $tiers[] = $envelope->eventName;

            return true;
        });

    MarkCourseOfferingCompletedAction::run($offering, recalculate: true);

    expect($tiers)->toContain('academic.course_completed')
        ->and($tiers)->toContain('academic.course_score_updated');
});

it('rejects a recalculate preview without recalculate_course_offering', function () {
    ($this->grantPermissions)(['view_course_offering']);
    $offering = makePreviewOffering($this, ['course_status' => 'completed']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_RECALCULATE_PREVIEW, $offering))
        ->assertForbidden();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_RECALCULATE_APPLY, $offering))
        ->assertForbidden();
});

it('pulls Canvas grades for the selected students then recalculates the whole offering in one transaction', function () {
    $syllabus = SyllabusTemplate::factory()->create(['unit_id' => $this->unit->id]);
    $offering = makePreviewOffering($this, [
        'syllabus_template_id' => $syllabus->id,
        'is_canvas_synced' => true,
    ]);

    $component = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'is_canvas_synced' => true,
        'canvas_assignment_group_id' => 'group-1',
    ]);
    AssessmentComponentDetail::factory()->create([
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

    $pulledStudent = registerPreviewStudent($this, $offering, 60, ['student_id' => 'AUS001']);
    $untouchedStudent = registerPreviewStudent($this, $offering, 60);

    $session = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'status' => 'completed',
        'session_date' => '2026-01-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'session_type' => 'lecture',
        'delivery_mode' => 'in_person',
    ]);
    foreach ([$pulledStudent, $untouchedStudent] as $student) {
        Attendance::create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'recording_method' => 'manual',
        ]);
    }

    $api = Mockery::mock(CanvasApiService::class);
    $api->shouldReceive('getCourseStudents')->andReturn([
        ['id' => 'canvas-user-1', 'sis_user_id' => 'AUS001', 'login_id' => null],
    ]);
    $api->shouldReceive('getAllStudentSubmissions')->andReturn([
        ['user_id' => 'canvas-user-1', 'assignment_id' => 'assign-1', 'score' => 90, 'grade' => 'A', 'workflow_state' => 'graded', 'submitted_at' => '2026-01-10T00:00:00Z', 'graded_at' => '2026-01-11T00:00:00Z'],
    ]);
    $api->shouldReceive('getStudentEnrollment')->andReturn(['grades' => ['current_score' => 92]]);
    app()->instance(CanvasApiService::class, $api);

    $response = actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->postJson(route(CourseOfferingRoutes::API_RECALCULATE_APPLY, $offering), [
            'pull_student_ids' => [$pulledStudent->id],
        ]);

    $response->assertOk();

    expect((float) AcademicRecord::where('course_offering_id', $offering->id)->where('student_id', $pulledStudent->id)->first()->final_percentage)
        ->toBe(92.0);
    expect((float) AcademicRecord::where('course_offering_id', $offering->id)->where('student_id', $untouchedStudent->id)->first()->final_percentage)
        ->toBe(60.0);
    expect($offering->fresh()->course_status)->toBe('completed');
});
