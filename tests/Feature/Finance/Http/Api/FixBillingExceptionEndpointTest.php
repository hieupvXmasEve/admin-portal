<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Support\BillingExceptionIdentifier;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const FIX_EXCEPTION_TEST_CSRF = 'fix-exception-test-csrf';

beforeEach(function () {
    $campus = Campus::factory()->create();
    app()->singleton('campus', fn () => $campus);

    session([
        '_token' => FIX_EXCEPTION_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);
});

function fixExceptionApiUser(array $permissions = ['view_finance_operations_exceptions']): User
{
    $user = User::factory()->create();
    $mock = Mockery::mock(CampusPermissionReader::class);
    $mock->shouldReceive('permissionCodesForUserId')->andReturn($permissions);
    app()->singleton(CampusPermissionReader::class, fn () => $mock);

    return $user;
}

function makeFixExceptionRegistration(Campus $campus, Semester $semester): CourseRegistration
{
    $program = Program::factory()->create();
    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'FIX-API-01',
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);

    return CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
        'retake_fee' => 1500000,
    ]);
}

it('returns 401 JSON for unauthenticated fix-exception requests', function () {
    $semester = Semester::factory()->active()->create();
    $registration = makeFixExceptionRegistration(app('campus'), $semester);
    $exceptionId = BillingExceptionIdentifier::encode('retake_no_charge', $registration->id);

    $this->withHeaders(['X-CSRF-TOKEN' => FIX_EXCEPTION_TEST_CSRF])
        ->postJson(route('api.finance.operations.fix-exception', $exceptionId))
        ->assertStatus(401)
        ->assertJson(['success' => false]);
});

it('returns 403 when the user lacks view_finance_operations_exceptions permission', function () {
    $semester = Semester::factory()->active()->create();
    $registration = makeFixExceptionRegistration(app('campus'), $semester);
    $exceptionId = BillingExceptionIdentifier::encode('retake_no_charge', $registration->id);
    $user = fixExceptionApiUser([]);

    $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => FIX_EXCEPTION_TEST_CSRF])
        ->postJson(route('api.finance.operations.fix-exception', $exceptionId))
        ->assertStatus(403);
});
