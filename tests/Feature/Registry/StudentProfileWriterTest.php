<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\StudentRegistry\StudentProfileWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates Registry-owned profile and contact data without changing lifecycle facts', function (): void {
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
            'intake_mode' => 'sequential',
            'status' => 'deferred',
            'academic_status' => 'suspended',
        ])
        ->create();

    $updated = app(StudentProfileWriter::class)->update((int) $student->id, [
        'full_name' => 'Updated Registry Student',
        'email' => 'student.updated@example.test',
        'phone' => '+84 900 123 456',
        'current_address_line' => '1 Registry Street',
        'emergency_contact_name' => 'Durable Guardian Contact',
        'status' => 'active',
        'program_id' => 999_999,
    ]);

    expect($updated)->toBeTrue()
        ->and($student->fresh()->full_name)->toBe('UPDATED REGISTRY STUDENT')
        ->and($student->fresh()->email)->toBe('student.updated@example.test')
        ->and($student->fresh()->phone)->toBe('+84 900 123 456')
        ->and($student->fresh()->current_address_line)->toBe('1 Registry Street')
        ->and($student->fresh()->emergency_contact_name)->toBe('Durable Guardian Contact')
        ->and($student->fresh()->status)->toBe('deferred')
        ->and($student->fresh()->academic_status)->toBe('suspended')
        ->and($student->fresh()->program_id)->toBe($program->id)
        ->and($account->fresh()->email)->toBe('student.old@example.test');
});
