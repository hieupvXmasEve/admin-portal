<?php

declare(strict_types=1);

use App\Http\Middleware\ParentStudentAccess;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Identity\DTO\ImpersonationAdministrator;
use App\Shared\Contracts\StudentRegistry\StudentImpersonationTokenIssuer;
use App\Shared\Contracts\StudentRegistry\StudentPortalTokenRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
});

/**
 * A withdrawn-by-enrollment student: the legacy `students.status` column
 * still says a financial status (so a bug here would silently keep them
 * active), but the live primary enrollment says withdrawn.
 */
function withdrawnByEnrollmentStudent(object $ctx, string $code): Student
{
    $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
    $student = Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'status' => 'intake_course',
            'user_id' => $user->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $ctx->semester->id,
        ])
        ->create();

    ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => 'withdrawn',
        'study_stage' => null,
        'source_type' => 'test_program_enrollment',
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);

    return $student->fresh();
}

it('denies API access via student.api.auth middleware for a withdrawn-by-enrollment student', function (): void {
    $student = withdrawnByEnrollmentStudent($this, 'GATE001');

    Sanctum::actingAs($student);
    $response = getJson(route('v1.student.scholarship-adjustment-confirmation.index'));

    $response->assertForbidden();
});

it('denies ParentStudentAccess::isStudentAccessible for a withdrawn-by-enrollment student', function (): void {
    $student = withdrawnByEnrollmentStudent($this, 'GATE002');

    $middleware = app(ParentStudentAccess::class);
    $method = new ReflectionMethod($middleware, 'isStudentAccessible');

    expect($method->invoke($middleware, $student))->toBeFalse();
});

it('refuses portal token refresh for a withdrawn-by-enrollment student', function (): void {
    $student = withdrawnByEnrollmentStudent($this, 'GATE003');

    expect(fn () => app(StudentPortalTokenRefresher::class)->refresh((int) $student->id))
        ->toThrow(DomainException::class);
});

it('refuses impersonation for a withdrawn-by-enrollment student', function (): void {
    $student = withdrawnByEnrollmentStudent($this, 'GATE004');
    $admin = User::factory()->create();

    $administrator = new ImpersonationAdministrator(
        id: $admin->id,
        name: $admin->name,
        email: $admin->email,
        ipAddress: '127.0.0.1',
        userAgent: null,
        deviceName: null,
        purpose: 'test',
    );

    expect(fn () => app(StudentImpersonationTokenIssuer::class)->issue($student->student_id, $administrator))
        ->toThrow(InvalidArgumentException::class);
});
