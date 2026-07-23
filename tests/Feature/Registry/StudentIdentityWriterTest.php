<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\StudentAccessWriter;
use App\Shared\Contracts\StudentRegistry\StudentIdentityWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

uses(RefreshDatabase::class);

it('updates the Registry contact email and linked Identity account atomically', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $account = User::factory()->create(['email' => 'student.old@example.test']);
    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'user_id' => $account->id,
            'email' => 'student.old@example.test',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ])
        ->create();

    app(StudentIdentityWriter::class)->updateEmail((int) $student->id, 'student.updated@example.test');

    expect($student->fresh()->email)->toBe('student.updated@example.test')
        ->and($account->fresh()->email)->toBe('student.updated@example.test');
});

it('rolls back the Registry contact email when Identity cannot update the linked account', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $account = User::factory()->create(['email' => 'student.old@example.test']);
    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'user_id' => $account->id,
            'email' => 'student.old@example.test',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ])
        ->create();
    $studentAccessWriter = Mockery::mock(StudentAccessWriter::class);
    $studentAccessWriter->shouldReceive('updateEmail')
        ->once()
        ->andThrow(new RuntimeException('Unable to update linked account email.'));
    app()->instance(StudentAccessWriter::class, $studentAccessWriter);

    expect(fn () => app(StudentIdentityWriter::class)->updateEmail(
        (int) $student->id,
        'student.updated@example.test',
    ))->toThrow(RuntimeException::class, 'Unable to update linked account email.');

    expect($student->fresh()->email)->toBe('student.old@example.test')
        ->and($account->fresh()->email)->toBe('student.old@example.test');
});
