<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Facilities\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseOfferingCockpitController;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseOfferingDeletionController;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseOfferingDuplicationController;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseOfferingInstructorAssignmentController;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseOfferingRegistrationController;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseOfferingRoomController;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseOfferingRosterController;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('keeps staff roster and instructor-assignment route names while dispatching through Delivery', function (): void {
    $routeNames = [
        'course-offerings.delete-student-registration',
        'course-offerings.move-student',
        'api.course-offerings.bulk-update-status',
        'api.course-offerings.bulk-assign-lectures',
    ];

    foreach ($routeNames as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)
            ->not->toBeNull("Expected {$routeName} to remain registered.")
            ->and($route?->getActionName())->toContain(CourseOfferingRosterController::class);
    }
});

it('keeps the course-offering deletion route name while dispatching through Delivery', function (): void {
    $route = Route::getRoutes()->getByName('course-offerings.destroy');

    expect($route)
        ->not->toBeNull()
        ->and($route?->getActionName())->toContain(CourseOfferingDeletionController::class);
});

it('keeps the cockpit route names on the Academic module controller', function (): void {
    foreach ([CourseOfferingRoutes::INDEX, CourseOfferingRoutes::SHOW] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)
            ->not->toBeNull()
            ->and($route?->getActionName())->toContain(CourseOfferingCockpitController::class);
    }
});

it('keeps the course-offering duplication route name while dispatching through Delivery', function (): void {
    $route = Route::getRoutes()->getByName('course-offerings.duplicate');

    expect($route)
        ->not->toBeNull()
        ->and($route?->getActionName())->toContain(CourseOfferingDuplicationController::class);
});

it('keeps the course-offering room-change route name while dispatching through Delivery', function (): void {
    $route = Route::getRoutes()->getByName('api.course-offerings.change-room');

    expect($route)
        ->not->toBeNull()
        ->and($route?->getActionName())->toContain(CourseOfferingRoomController::class);
});

it('keeps registration discovery and bulk enrollment routes on Delivery', function (): void {
    foreach (['api.course-offerings.search-students', 'api.course-offerings.bulk-register-students'] as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)
            ->not->toBeNull()
            ->and($route?->getActionName())->toContain(CourseOfferingRegistrationController::class);
    }
});

it('keeps instructor-assignment readiness checks on Delivery', function (): void {
    $route = Route::getRoutes()->getByName('api.course-offerings.check-instructor-assignments');

    expect($route)
        ->not->toBeNull()
        ->and($route?->getActionName())->toContain(CourseOfferingInstructorAssignmentController::class);
});

it('preserves the course-offering duplication redirect and flash response', function (): void {
    $this->withoutMiddleware([
        Authenticate::class,
        Authorize::class,
        EnsureEmailIsVerified::class,
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'section_code' => 'A',
    ]);
    app()->instance('campus', $campus);

    $this->post(route('course-offerings.duplicate', $offering), ['section_code' => 'A_copy'])
        ->assertRedirect(route('course-offerings.index'))
        ->assertSessionHas('inertia.flash_data.success', 'Course offering duplicated successfully. Please assign an instructor.');

    expect(CourseOffering::query()->where('section_code', 'A_copy')->exists())->toBeTrue();
});

it('does not expose course-offering duplication across campuses', function (): void {
    $this->withoutMiddleware([
        Authenticate::class,
        Authorize::class,
        EnsureEmailIsVerified::class,
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $currentCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $otherCampus->id,
        'semester_id' => $semester->id,
        'section_code' => 'A',
    ]);
    app()->instance('campus', $currentCampus);

    $this->post(route('course-offerings.duplicate', $offering), ['section_code' => 'A_copy'])->assertNotFound();

    expect(CourseOffering::query()->where('section_code', 'A_copy')->exists())->toBeFalse();
});

it('preserves the course-offering deletion redirect and flash response', function (): void {
    $this->withoutMiddleware([
        Authenticate::class,
        Authorize::class,
        EnsureEmailIsVerified::class,
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'current_enrollment' => 0,
    ]);
    app()->instance('campus', $campus);

    $this->delete(route('course-offerings.destroy', $offering))
        ->assertRedirect(route('course-offerings.index'))
        ->assertSessionHas('inertia.flash_data.success', 'Course offering deleted successfully.');

    expect(CourseOffering::query()->find($offering->id))->toBeNull();
});

it('does not expose course-offering deletion across campuses', function (): void {
    $this->withoutMiddleware([
        Authenticate::class,
        Authorize::class,
        EnsureEmailIsVerified::class,
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $currentCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $otherCampus->id,
        'semester_id' => $semester->id,
        'current_enrollment' => 0,
    ]);
    app()->instance('campus', $currentCampus);

    $this->delete(route('course-offerings.destroy', $offering))->assertNotFound();

    expect(CourseOffering::query()->find($offering->id))->not->toBeNull();
});

it('preserves the room-change redirect, flash, and campus boundary', function (): void {
    $this->withoutMiddleware([
        Authenticate::class,
        Authorize::class,
        EnsureEmailIsVerified::class,
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $currentCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $currentCampus->id,
        'semester_id' => $semester->id,
    ]);
    $otherOffering = CourseOffering::factory()->create([
        'campus_id' => $otherCampus->id,
        'semester_id' => $semester->id,
    ]);
    $oldRoom = Room::factory()->create(['campus_id' => $currentCampus->id]);
    $newRoom = Room::factory()->create(['campus_id' => $currentCampus->id]);
    $session = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'room_id' => $oldRoom->id,
        'session_date' => now()->addWeek()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'status' => 'scheduled',
    ]);
    app()->instance('campus', $currentCampus);

    $this->from(route('course-offerings.show', $offering))
        ->post(route('api.course-offerings.change-room', $offering), ['room_id' => $newRoom->id])
        ->assertRedirect(route('course-offerings.show', $offering))
        ->assertSessionHas('success', 'Room updated for all class sessions.');

    expect($session->fresh()->room_id)->toBe($newRoom->id);

    $this->post(route('api.course-offerings.change-room', $otherOffering), ['room_id' => $newRoom->id])
        ->assertNotFound();
});

it('preserves the roster-removal JSON envelope while delegating attempt removal to Progression', function (): void {
    $this->withoutMiddleware([
        Authenticate::class,
        Authorize::class,
        EnsureEmailIsVerified::class,
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 1,
    ]);
    $registration = CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);
    $attempt = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $offering->id,
    ]);
    app()->instance('campus', $campus);

    $this->postJson(route('course-offerings.delete-student-registration', $offering), [
        'registration_id' => $registration->id,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.deleted_registration_id', $registration->id)
        ->assertJsonPath('data.student_id', $student->student_id);

    expect(CourseRegistration::query()->find($registration->id))->toBeNull()
        ->and(AcademicRecord::withTrashed()->find($attempt->id))->toBeNull();
});
