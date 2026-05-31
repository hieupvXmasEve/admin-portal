<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Modules\Academic\Queries\ExportStudentsQuery;
use App\Modules\Academic\Queries\ListStudentsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters exact student codes within the current campus', function () {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();

    $includedStudent = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'student_id' => 'SE100001',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    Student::factory()->forCampus($campus)->forProgram($program)->create([
        'student_id' => 'SE100001-OLD',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    Student::factory()->forCampus($otherCampus)->forProgram($program)->create([
        'student_id' => 'SE200001',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    $students = (new ListStudentsQuery)->handle([
        'student_ids' => ['SE100001', 'SE200001'],
    ], $campus->id);

    expect(collect($students->items())->pluck('id')->all())
        ->toBe([$includedStudent->id]);
});

it('combines student codes with the existing search program and status filters', function () {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $semester = Semester::factory()->create();

    $includedStudent = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'student_id' => 'SE300001',
        'full_name' => 'Included Nguyen',
        'status' => 'graduated',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    Student::factory()->forCampus($campus)->forProgram($otherProgram)->create([
        'student_id' => 'SE300002',
        'full_name' => 'Included Tran',
        'status' => 'graduated',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    Student::factory()->forCampus($campus)->forProgram($program)->create([
        'student_id' => 'SE300003',
        'full_name' => 'Excluded Nguyen',
        'status' => 'pending',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    $students = (new ListStudentsQuery)->handle([
        'student_ids' => ['SE300001', 'SE300002', 'SE300003'],
        'search' => 'Included',
        'program_id' => $program->id,
        'status' => 'graduated',
    ], $campus->id);

    expect(collect($students->items())->pluck('id')->all())
        ->toBe([$includedStudent->id]);
});

it('applies the student code filter to filtered exports', function () {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();

    $includedStudent = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'student_id' => 'SE400001',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    Student::factory()->forCampus($campus)->forProgram($program)->create([
        'student_id' => 'SE400002',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    Student::factory()->forCampus($otherCampus)->forProgram($program)->create([
        'student_id' => 'SE400003',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    $studentIds = (new ExportStudentsQuery)
        ->getBuilder($campus->id, [
            'scope' => 'filtered',
            'student_ids' => ['SE400001', 'SE400003'],
        ])
        ->pluck('id')
        ->all();

    expect($studentIds)->toBe([$includedStudent->id]);
});

it('combines advanced multi-select filters within the current campus', function () {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $firstProgram = Program::factory()->create();
    $secondProgram = Program::factory()->create();
    $firstSpecialization = Specialization::factory()->forProgram($firstProgram)->active()->create();
    $secondSpecialization = Specialization::factory()->forProgram($secondProgram)->active()->create();
    $otherSpecialization = Specialization::factory()->forProgram($firstProgram)->active()->create();
    $firstSemester = Semester::factory()->create();
    $secondSemester = Semester::factory()->create();

    $firstIncludedStudent = Student::factory()->forCampus($campus)->forProgram($firstProgram)->create([
        'student_id' => 'SE600001',
        'specialization_id' => $firstSpecialization->id,
        'status' => 'graduated',
        'intake' => 1,
        'intake_semester_id' => $firstSemester->id,
    ]);
    $secondIncludedStudent = Student::factory()->forCampus($campus)->forProgram($secondProgram)->create([
        'student_id' => 'SE600002',
        'specialization_id' => $secondSpecialization->id,
        'status' => 'pending',
        'intake' => 1,
        'intake_semester_id' => $secondSemester->id,
    ]);
    Student::factory()->forCampus($campus)->forProgram($firstProgram)->create([
        'student_id' => 'SE600003',
        'specialization_id' => $otherSpecialization->id,
        'status' => 'graduated',
        'intake' => 1,
        'intake_semester_id' => $firstSemester->id,
    ]);
    Student::factory()->forCampus($otherCampus)->forProgram($firstProgram)->create([
        'student_id' => 'SE600004',
        'specialization_id' => $firstSpecialization->id,
        'status' => 'graduated',
        'intake' => 1,
        'intake_semester_id' => $firstSemester->id,
    ]);

    $students = (new ListStudentsQuery)->handle([
        'program_ids' => [$firstProgram->id, $secondProgram->id],
        'specialization_ids' => [$firstSpecialization->id, $secondSpecialization->id],
        'statuses' => ['graduated', 'pending'],
        'intake_semester_ids' => [$firstSemester->id, $secondSemester->id],
    ], $campus->id);

    expect(collect($students->items())->pluck('id')->sort()->values()->all())
        ->toBe(collect([$firstIncludedStudent->id, $secondIncludedStudent->id])->sort()->values()->all());
});

it('applies advanced multi-select filters to filtered exports', function () {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $specialization = Specialization::factory()->forProgram($program)->active()->create();
    $otherSpecialization = Specialization::factory()->forProgram($otherProgram)->active()->create();
    $semester = Semester::factory()->create();
    $otherSemester = Semester::factory()->create();

    $includedStudent = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'student_id' => 'SE700001',
        'specialization_id' => $specialization->id,
        'status' => 'graduated',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    Student::factory()->forCampus($campus)->forProgram($otherProgram)->create([
        'student_id' => 'SE700002',
        'specialization_id' => $otherSpecialization->id,
        'status' => 'pending',
        'intake' => 1,
        'intake_semester_id' => $otherSemester->id,
    ]);

    $studentIds = (new ExportStudentsQuery)
        ->getBuilder($campus->id, [
            'scope' => 'filtered',
            'program_ids' => [$program->id],
            'specialization_ids' => [$specialization->id],
            'statuses' => ['graduated'],
            'intake_semester_ids' => [$semester->id],
        ])
        ->pluck('id')
        ->all();

    expect($studentIds)->toBe([$includedStudent->id]);
});
