<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use App\Services\PermissionService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows charges scoped to the selected student', function () {
    /** @var User $authorizedUser */
    $authorizedUser = User::factory()->create();
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $program = Program::factory()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    session(['current_campus_id' => $campus->id]);

    app()->singleton('campus', fn () => $campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(fn ($user, $campusId) => $user->id === $authorizedUser->id ? ['view_finance_charges'] : []);

    app()->singleton(PermissionService::class, fn () => $permissionService);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_gc' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();

    $otherStudent = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_gc' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();

    FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => 1500000,
        'description' => 'Target student charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    FinanceCharge::create([
        'student_id' => $otherStudent->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => 2500000,
        'description' => 'Other student charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    /** @var Authenticatable $authenticatedUser */
    $authenticatedUser = $authorizedUser;

    $response = actingAs($authenticatedUser)
        ->get(route('finance.students.charges', $student));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Charges/Index')
        ->where('student.id', $student->id)
        ->where('filters.student_id', $student->id)
        ->has('charges.data', 1)
        ->where('charges.data.0.student_id', $student->id)
        ->where('charges.data.0.student.full_name', $student->full_name)
        ->where('charges.data.0.student.student_id', $student->student_id)
    );
});
