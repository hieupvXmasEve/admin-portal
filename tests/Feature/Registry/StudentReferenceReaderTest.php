<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('publishes a campus-scoped student reference without lifecycle or finance state', function (): void {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->state([
        'intake' => 1,
        'intake_semester_id' => $semester->id,
        'student_id' => 'REG-1001',
        'full_name' => 'Registry Student',
        'email' => 'registry.student@example.test',
        'status' => 'deferred',
    ])->create();
    Student::factory()->forCampus($otherCampus)->forProgram($program)->state([
        'intake' => 1,
        'intake_semester_id' => $semester->id,
        'student_id' => 'REG-1002',
        'full_name' => 'Other Campus Student',
    ])->create();

    $reader = app(StudentReferenceReader::class);
    $reference = $reader->find((int) $student->id);

    expect($reference)
        ->toBeInstanceOf(StudentReference::class)
        ->and($reference?->toArray())->toBe([
            'id' => $student->id,
            'student_code' => 'REG-1001',
            'full_name' => $student->full_name,
            'campus_id' => $campus->id,
        ])
        ->and($reference?->programId)->toBe($program->id)
        ->and($reference?->programCode)->toBe($program->code)
        ->and($reference?->programName)->toBe($program->name)
        ->and($reader->find(999_999))->toBeNull()
        ->and($reader->findMany([(int) $student->id, 999_999]))->toHaveKey((int) $student->id)
        ->and($reader->idsForCampus((int) $campus->id))->toBe([(int) $student->id])
        ->and($reader->idsMatchingSearch('Registry', (int) $campus->id))->toBe([(int) $student->id])
        ->and($reader->findByStudentCode('REG-1001', (int) $campus->id)?->id)->toBe($student->id)
        ->and($reader->findByStudentCode('REG-1001', (int) $otherCampus->id))->toBeNull()
        ->and($reader->findByStudentCodeAnywhere('REG-1001')?->id)->toBe($student->id)
        ->and($reader->findByStudentCodeAnywhere('MISSING'))->toBeNull()
        ->and(array_map(static fn (StudentReference $match): int => $match->id, $reader->search('Registry', (int) $campus->id)))
        ->toBe([$student->id]);
});
