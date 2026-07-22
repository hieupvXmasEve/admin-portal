<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingDeletionController;
use App\Modules\Academic\Http\Web\Admin\CourseOfferingRosterController;
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
