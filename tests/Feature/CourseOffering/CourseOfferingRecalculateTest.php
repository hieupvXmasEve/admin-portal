<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\AcademicRecord;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\CanvasCourseMapping;
use App\Models\CanvasIntegration;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Identity\CampusPermissionReader;
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
        $permissionService = Mockery::mock(CampusPermissionReader::class);
        $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);

        app()->forgetInstance(CampusPermissionReader::class);
        app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
    };

    ($this->grantPermissions)([
        'view_course_offering',
        'recalculate_course_offering',
    ]);
});

function makeRecalcOffering(object $context, array $overrides = []): CourseOffering
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

function makeRecalcSession(CourseOffering $courseOffering, array $overrides = []): ClassSession
{
    return ClassSession::factory()->create(array_merge([
        'course_offering_id' => $courseOffering->id,
        'status' => 'completed',
        'session_date' => '2026-01-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'session_type' => 'lecture',
        'delivery_mode' => 'in_person',
    ], $overrides));
}

function registerRecalcStudent(object $context, CourseOffering $courseOffering, float $finalPercentage, array $studentOverrides = []): Student
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

function mapRecalcCanvasCourse(CourseOffering $courseOffering, string $syncStatus): CanvasCourseMapping
{
    $integration = CanvasIntegration::query()->create([
        'canvas_url' => 'https://canvas.example.test',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);

    return CanvasCourseMapping::query()->create([
        'canvas_integration_id' => $integration->id,
        'canvas_course_id' => 'canvas-'.$courseOffering->id,
        'canvas_course_name' => 'Canvas Course '.$courseOffering->id,
        'course_offering_id' => $courseOffering->id,
        'sync_status' => $syncStatus,
    ]);
}

/**
 * A completed offering with one attended session and a passing student,
 * ready to be recalculated.
 */
function makeCompletedRecalcOffering(object $context, array $overrides = []): CourseOffering
{
    $offering = makeRecalcOffering($context, $overrides);
    $session = makeRecalcSession($offering);
    Attendance::create([
        'class_session_id' => $session->id,
        'student_id' => registerRecalcStudent($context, $offering, 85)->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);

    MarkCourseOfferingCompletedAction::run($offering);

    return $offering->fresh();
}

it('exposes the recalculate action with the Canvas blocker on a completed, mapped-but-unsynced offering', function () {
    $offering = makeCompletedRecalcOffering($this, ['is_canvas_synced' => false]);
    mapRecalcCanvasCourse($offering, 'mapped');

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $offering))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('operational_state.lifecycle_stage', 'completed')
            ->has('operational_state.readiness_blockers', 1)
            ->where('operational_state.readiness_blockers.0.code', 'canvas_unsynced')
            ->where('operational_state.available_actions.0.action', 'recalculate')
            ->where('operational_state.available_actions.0.allowed', false)
            ->where('operational_state.available_actions.0.blocked_by', ['canvas_unsynced']));
});

it('omits the recalculate action for users lacking recalculate_course_offering', function () {
    ($this->grantPermissions)(['view_course_offering']);
    $offering = makeCompletedRecalcOffering($this);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::SHOW, $offering))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('operational_state.available_actions', []));
});

it('notifies only students whose pass/fail status changed on recalculate', function () {
    config([
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
    ]);

    $offering = makeRecalcOffering($this);
    $session = makeRecalcSession($offering);

    $userA = User::factory()->create();
    $studentA = registerRecalcStudent($this, $offering, 85, ['user_id' => $userA->id]);
    Attendance::create([
        'class_session_id' => $session->id,
        'student_id' => $studentA->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);

    $userB = User::factory()->create();
    $studentB = registerRecalcStudent($this, $offering, 85, ['user_id' => $userB->id]);

    MarkCourseOfferingCompletedAction::run($offering);

    // Correct student B's grade to failing; student A is unchanged.
    AcademicRecord::where('course_offering_id', $offering->id)
        ->where('student_id', $studentB->id)
        ->update(['final_percentage' => 50]);

    $publishSpy = Mockery::mock(DomainEventPublisher::class);
    app()->instance(DomainEventPublisher::class, $publishSpy);

    $publishSpy->shouldReceive('publishAfterCommit')
        ->once()
        ->withArgs(function (DomainEvent $event) use ($studentB) {
            return $event->name === 'academic.course_completed'
                && $event->payload['student_id'] === $studentB->id;
        });

    MarkCourseOfferingCompletedAction::run($offering, recalculate: true);
});
