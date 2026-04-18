<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
    $this->withoutMiddleware();

    if (! Route::has(StudentRoutes::ACADEMIC_SUMMARY_SHOW)) {
        Route::get('/testing/students/{student}/academic-summary', fn () => 'ok')
            ->name(StudentRoutes::ACADEMIC_SUMMARY_SHOW);
    }

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['edit_student']);

    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('syncs the linked user email when updating a student email', function () {
    $studentUser = User::factory()->create([
        'email' => 'student.old@example.com',
        'name' => 'student old',
    ]);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'user_id' => $studentUser->id,
            'full_name' => 'student old',
            'email' => 'student.old@example.com',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $updatedStudent = app(StudentService::class)->updateStudent($student, [
            'full_name' => 'student old',
            'email' => 'student.new@example.com',
        ]);

    expect($updatedStudent->email)->toBe('student.new@example.com')
        ->and($student->fresh()->email)->toBe('student.new@example.com')
        ->and($studentUser->fresh()->email)->toBe('student.new@example.com');
});

it('rejects a student email that is already used by another user account', function () {
    User::factory()->create([
        'email' => 'taken@example.com',
    ]);

    $studentUser = User::factory()->create([
        'email' => 'student.old@example.com',
    ]);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'user_id' => $studentUser->id,
            'full_name' => 'student old',
            'email' => 'student.old@example.com',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $response = actingAs($this->authorizedUser)
        ->from(route(StudentRoutes::EDIT, $student))
        ->withSession(['current_campus_id' => $this->campus->id])
        ->put(route(StudentRoutes::UPDATE, $student), [
            'full_name' => 'student old',
            'email' => 'taken@example.com',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['email']);

    expect($student->fresh()->email)->toBe('student.old@example.com')
        ->and($studentUser->fresh()->email)->toBe('student.old@example.com');
});
