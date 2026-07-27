<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Http\Web\Admin\CourseRegistrationController;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('keeps every staff course-registration route contract on the Delivery controller', function (): void {
    $contracts = [
        'course-registrations.index' => ['GET', 'course-registrations'],
        'course-registrations.create' => ['GET', 'course-registrations/create'],
        'course-registrations.store' => ['POST', 'course-registrations'],
        'course-registrations.show' => ['GET', 'course-registrations/{adminCourseRegistration}'],
        'course-registrations.edit' => ['GET', 'course-registrations/{adminCourseRegistration}/edit'],
        'course-registrations.update' => ['PUT', 'course-registrations/{adminCourseRegistration}'],
        'course-registrations.destroy' => ['DELETE', 'course-registrations/{adminCourseRegistration}'],
        'course-registrations.drop' => ['PATCH', 'course-registrations/{adminCourseRegistration}/drop'],
        'course-registrations.withdraw' => ['PATCH', 'course-registrations/{adminCourseRegistration}/withdraw'],
        'api.course-registrations.bulk-delete' => ['DELETE', 'api/course-registrations/bulk-delete'],
        'api.course-registrations.available-courses' => ['GET', 'api/course-registrations/available-courses'],
        'api.course-registrations.available-units' => ['GET', 'api/course-registrations/available-units'],
        'api.course-registrations.check-eligibility' => ['GET', 'api/course-registrations/check-eligibility'],
        'api.course-registrations.student-registrations' => ['GET', 'api/course-registrations/student-registrations'],
    ];

    $abilities = [
        'course-registrations.index' => 'view_course_registration',
        'course-registrations.create' => 'create_course_registration',
        'course-registrations.store' => 'create_course_registration',
        'course-registrations.show' => 'view_course_registration',
        'course-registrations.edit' => 'edit_course_registration',
        'course-registrations.update' => 'edit_course_registration',
        'course-registrations.destroy' => 'delete_course_registration',
        'course-registrations.drop' => 'edit_course_registration',
        'course-registrations.withdraw' => 'edit_course_registration',
        'api.course-registrations.bulk-delete' => 'delete_course_registration',
        'api.course-registrations.available-courses' => 'view_course_registration',
        'api.course-registrations.available-units' => 'view_course_registration',
        'api.course-registrations.check-eligibility' => 'view_course_registration',
        'api.course-registrations.student-registrations' => 'view_course_registration',
    ];

    foreach ($contracts as $name => [$method, $uri]) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)
            ->not->toBeNull("Expected {$name} to remain registered.")
            ->and($route?->uri())->toBe($uri)
            ->and($route?->methods())->toContain($method)
            ->and($route?->getActionName())->toContain(CourseRegistrationController::class)
            ->and($route?->middleware())->toContain('auth', 'verified', 'campus.selected', 'can:'.$abilities[$name]);
    }

    foreach (['course-registrations.drop', 'course-registrations.withdraw'] as $name) {
        expect(Route::getRoutes()->getByName($name)?->middleware())
            ->toContain('can:manage_course_registration');
    }
});

it('registers an active student through the preserved staff route and Delivery action', function (): void {
    bypassCourseRegistrationMiddleware($this);
    $campus = Campus::factory()->create();
    $this->withSession(['current_campus_id' => $campus->id]);
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'active',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 0,
        'max_capacity' => 20,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);

    $this->from(route('course-registrations.create'))
        ->post(route('course-registrations.store'), [
            'student_id' => $student->id,
            'unit_ids' => [$unit->id],
            'notes' => 'Staff enrollment',
        ])
        ->assertRedirect(route('course-registrations.index'))
        ->assertSessionHas('inertia.flash_data.success', 'Successfully registered student for 1 unit(s).');

    expect(CourseRegistration::query()
        ->where('student_id', $student->id)
        ->where('course_offering_id', $offering->id)
        ->value('registration_status'))->toBe('confirmed')
        ->and($offering->fresh()->current_enrollment)->toBe(1);
});

it('keeps the registrations index Inertia contract on the Delivery query', function (): void {
    bypassCourseRegistrationMiddleware($this);

    $this->get(route('course-registrations.index', ['search' => 'none', 'per_page' => 15]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CourseRegistrations/Index')
            ->where('filters.search', 'none')
            ->where('filters.per_page', '15')
            ->has('registrations.data', 0)
            ->has('statistics')
            ->has('semesters')
            ->has('statusOptions', 5));
});

it('keeps legacy loaded-relation serialization on the registration detail route', function (): void {
    bypassCourseRegistrationMiddleware($this);
    $campus = Campus::factory()->create();
    $this->withSession(['current_campus_id' => $campus->id]);
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $unit = Unit::factory()->create();
    $lecture = Lecture::factory()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'lecture_id' => $lecture->id,
    ]);
    $registration = createCourseRegistrationForRouteTest($student, $offering, $semester);

    $this->get(route('course-registrations.show', $registration))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('registration.student', $student->fresh()->toArray())
            ->where('registration.course_offering.unit', $unit->fresh()->toArray())
            ->where('registration.course_offering.lecture', $lecture->fresh()->toArray())
            ->missing('registration.semester'));
});

it('keeps the available-courses helper envelope and filters existing registrations', function (): void {
    bypassCourseRegistrationMiddleware($this);
    $campus = Campus::factory()->create();
    $this->withSession(['current_campus_id' => $campus->id]);
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $lecture = Lecture::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $available = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'lecture_id' => $lecture->id,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);
    $registered = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $registered->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    $this->getJson(route('api.course-registrations.available-courses', [
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.offering.id', $available->id)
        ->assertJsonPath('data.0.offering.unit', $unit->fresh()->toArray())
        ->assertJsonPath('data.0.offering.lecture', $lecture->fresh()->toArray())
        ->assertJsonCount(1, 'data');
});

it('denies helper reads for students outside the selected campus', function (): void {
    bypassCourseRegistrationMiddleware($this);
    $selectedCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $this->withSession(['current_campus_id' => $selectedCampus->id]);
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($otherCampus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $otherCampus->id,
        'semester_id' => $semester->id,
        'enrollment_status' => 'open',
    ]);

    $this->getJson(route('api.course-registrations.available-courses', [
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]))->assertNotFound();

    $this->getJson(route('api.course-registrations.check-eligibility', [
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
    ]))->assertNotFound();

    $this->getJson(route('api.course-registrations.student-registrations', [
        'student_id' => $student->id,
    ]))->assertNotFound();
});

it('drops and withdraws registrations through the Delivery actions', function (): void {
    bypassCourseRegistrationMiddleware($this);
    $campus = Campus::factory()->create();
    $this->withSession(['current_campus_id' => $campus->id]);
    $semester = Semester::factory()->active()->create([
        'start_date' => now()->subWeek(),
        'end_date' => now()->addWeek(),
        'enrollment_start_date' => now()->subWeek(),
        'enrollment_end_date' => now()->addWeek(),
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'current_enrollment' => 1,
    ]);
    $withdrawalOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'current_enrollment' => 1,
    ]);
    $dropped = createCourseRegistrationForRouteTest($student, $offering, $semester);
    $withdrawn = createCourseRegistrationForRouteTest($student, $withdrawalOffering, $semester);

    $this->from(route('course-registrations.show', $dropped))
        ->patch(route('course-registrations.drop', $dropped))
        ->assertRedirect(route('course-registrations.show', $dropped))
        ->assertSessionHas('inertia.flash_data.success', 'Student dropped from course successfully.');
    $this->from(route('course-registrations.show', $withdrawn))
        ->patch(route('course-registrations.withdraw', $withdrawn))
        ->assertRedirect(route('course-registrations.show', $withdrawn))
        ->assertSessionHas('inertia.flash_data.success', 'Student withdrawn from course successfully.');

    expect($dropped->fresh()->registration_status)->toBe('dropped')
        ->and($dropped->fresh()->drop_date)->not->toBeNull()
        ->and($withdrawn->fresh()->registration_status)->toBe('withdrawn')
        ->and($withdrawn->fresh()->withdrawal_date)->not->toBeNull()
        ->and($offering->fresh()->current_enrollment)->toBe(0)
        ->and($withdrawalOffering->fresh()->current_enrollment)->toBe(0);
});

function bypassCourseRegistrationMiddleware(object $test): void
{
    $test->withoutMiddleware([
        Authenticate::class,
        Authorize::class,
        EnsureEmailIsVerified::class,
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);
}

function createCourseRegistrationForRouteTest(Student $student, CourseOffering $offering, Semester $semester): CourseRegistration
{
    return CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);
}
