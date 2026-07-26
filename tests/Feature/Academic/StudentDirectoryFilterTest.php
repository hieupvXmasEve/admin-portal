<?php

declare(strict_types=1);

use App\Constants\StudentRoutes;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_student']);

    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

it('returns selected student codes in the Inertia filter contract', function () {
    Student::factory()->forCampus($this->campus)->forProgram($this->program)->create([
        'student_id' => 'SE500001',
        'intake' => 1,
        'intake_semester_id' => $this->semester->id,
    ]);
    Student::factory()->forCampus($this->campus)->forProgram($this->program)->create([
        'student_id' => 'SE500002',
        'intake' => 1,
        'intake_semester_id' => $this->semester->id,
    ]);
    Student::factory()->forCampus($this->campus)->forProgram($this->program)->create([
        'student_id' => 'SE500003',
        'intake' => 1,
        'intake_semester_id' => $this->semester->id,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::INDEX, [
            'student_ids' => ['SE500001', 'SE500002'],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/Index')
            ->where('filters.student_ids', ['SE500001', 'SE500002'])
            ->has('students.data', 2)
            ->where('students.data.0.student_id', 'SE500002')
            ->where('students.data.1.student_id', 'SE500001'));
});

it('rejects more than one hundred student codes', function () {
    $studentIds = collect(range(1, 101))
        ->map(fn (int $index) => sprintf('SE%06d', $index))
        ->all();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::INDEX, [
            'student_ids' => $studentIds,
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors(['student_ids']);
});

it('rejects student codes longer than the database identifier limit', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::INDEX, [
            'student_ids' => [str_repeat('S', 21)],
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors(['student_ids.0']);
});

it('returns advanced filters and lookup options in the Inertia contract', function () {
    $specialization = Specialization::factory()->forProgram($this->program)->active()->create();
    Specialization::factory()->forProgram($this->program)->inactive()->create();

    Student::factory()->forCampus($this->campus)->forProgram($this->program)->create([
        'student_id' => 'SE800001',
        'specialization_id' => $specialization->id,
        'status' => 'graduated',
        'intake' => 1,
        'intake_semester_id' => $this->semester->id,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::INDEX, [
            'program_ids' => [$this->program->id],
            'specialization_ids' => [$specialization->id],
            'statuses' => ['graduated'],
            'intake_semester_ids' => [$this->semester->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/Index')
            ->where('filters.program_ids', [$this->program->id])
            ->where('filters.specialization_ids', [$specialization->id])
            ->where('filters.statuses', ['graduated'])
            ->where('filters.intake_semester_ids', [$this->semester->id])
            ->where('programs', fn ($programs) => collect($programs)->pluck('id')->contains($this->program->id))
            ->has('specializations', 1)
            ->where('specializations.0.id', $specialization->id)
            ->where('intake_semesters', fn ($semesters) => collect($semesters)->pluck('id')->contains($this->semester->id))
            ->has('students.data', 1)
            ->where('students.data.0.student_id', 'SE800001'));
});

it('normalizes legacy singular program and status filters', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::INDEX, [
            'program_id' => $this->program->id,
            'status' => 'graduated',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.program_ids', [$this->program->id])
            ->where('filters.statuses', ['graduated']));
});

it('rejects invalid advanced filter values', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(StudentRoutes::INDEX, [
            'program_ids' => [999999],
            'specialization_ids' => [999999],
            'statuses' => ['unknown'],
            'intake_semester_ids' => [999999],
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors([
            'program_ids.0',
            'specialization_ids.0',
            'statuses.0',
            'intake_semester_ids.0',
        ]);
});
