<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Actions\RecordStudentActionAction;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\StudentRegistry\Queries\ExportStudentsQuery;
use App\Modules\StudentRegistry\Queries\ListStudentsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function materializeStudentEnrollment(Student $student): void
{
    MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);
}

it('filters lifecycle status from Program Enrollment after the Student snapshot becomes stale', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'status' => 'intake_course',
        'academic_status' => 'active',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Approved withdrawal',
        'dropout_semester_id' => $semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $students = app(ListStudentsQuery::class)->handle(['status' => 'dropout'], $campus->id);

    expect(collect($students->items())->pluck('id')->all())->toBe([$student->id])
        ->and($student->fresh()->status)->toBe('intake_course');
});

it('reflects the live study_stage in the returned rows, not the stale students.status column', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'status' => 'intake_pre_uni_gc',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    materializeStudentEnrollment($student);
    ProgramEnrollment::query()->where('student_id', $student->id)->update([
        'study_stage' => 'intake_course',
    ]);

    $students = app(ListStudentsQuery::class)->handle([], $campus->id);

    expect(collect($students->items())->firstWhere('id', $student->id)->status)->toBe('intake_course')
        ->and($student->fresh()->status)->toBe('intake_pre_uni_gc');
});

it('uses Program Enrollment for matching directory and export status filters', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();

    $legacyOnlyStudent = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'status' => 'intake_pre_uni_gc',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    $activeStudent = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    materializeStudentEnrollment($legacyOnlyStudent);
    materializeStudentEnrollment($activeStudent);
    ProgramEnrollment::query()->where('student_id', $legacyOnlyStudent->id)->update([
        'enrollment_status' => 'deferred',
        'study_stage' => 'intake_pre_uni_gc',
    ]);

    $students = app(ListStudentsQuery::class)->handle([
        'statuses' => ['intake_pre_uni_gc', 'intake_course'],
    ], $campus->id);
    $exportedStudentIds = app(ExportStudentsQuery::class)
        ->getBuilder($campus->id, [
            'scope' => 'filtered',
            'statuses' => ['intake_pre_uni_gc', 'intake_course'],
        ])
        ->pluck('id')
        ->all();

    expect(collect($students->items())->pluck('id')->all())->toBe([$activeStudent->id])
        ->and($exportedStudentIds)->toBe([$activeStudent->id])
        ->and($legacyOnlyStudent->fresh()->status)->toBe('intake_pre_uni_gc');
});

it('keeps normalized Program Enrollment lifecycle subtypes exact in staff filters', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $students = collect([
        'dropout' => Student::factory()->forCampus($campus)->forProgram($program)->create([
            'status' => 'dropout',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]),
        'dropout_transfer' => Student::factory()->forCampus($campus)->forProgram($program)->create([
            'status' => 'dropout_transfer',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]),
        'deferred' => Student::factory()->forCampus($campus)->forProgram($program)->create([
            'status' => 'deferred',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]),
        'admission_deferred' => Student::factory()->forCampus($campus)->forProgram($program)->create([
            'status' => 'admission_deferred',
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]),
    ]);
    $students->each(static fn (Student $student) => MaterializeProgramEnrollmentAction::run([
        'student_id' => $student->id,
    ]));

    foreach ($students as $status => $student) {
        $filtered = app(ListStudentsQuery::class)->handle(['status' => $status], $campus->id);

        expect(collect($filtered->items())->pluck('id')->all())->toBe([$student->id]);
    }
});

it('prefers the backfill snapshot over lifecycle history recorded before materialization', function (): void {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $user = User::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)->create([
        'status' => 'dropout_transfer',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);
    $historicalLog = StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Historical dropout before transfer classification',
        'new_status' => 'dropout',
        'changed_by_user_id' => $user->id,
    ]);
    $historicalLog->created_at = now()->subDay();
    $historicalLog->saveQuietly();
    MaterializeProgramEnrollmentAction::run(['student_id' => $student->id]);

    $transferStudents = app(ListStudentsQuery::class)->handle(['status' => 'dropout_transfer'], $campus->id);
    $dropoutStudents = app(ListStudentsQuery::class)->handle(['status' => 'dropout'], $campus->id);

    expect(collect($transferStudents->items())->pluck('id')->all())->toBe([$student->id])
        ->and(collect($dropoutStudents->items())->pluck('id')->all())->toBe([]);
});

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

    $students = app(ListStudentsQuery::class)->handle([
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

    foreach (Student::query()->whereIn('student_id', ['SE300001', 'SE300002', 'SE300003'])->get() as $student) {
        materializeStudentEnrollment($student);
    }

    $students = app(ListStudentsQuery::class)->handle([
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

    $studentIds = app(ExportStudentsQuery::class)
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

    foreach (Student::query()->whereIn('student_id', ['SE600001', 'SE600002', 'SE600003', 'SE600004'])->get() as $student) {
        materializeStudentEnrollment($student);
    }

    $students = app(ListStudentsQuery::class)->handle([
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

    foreach (Student::query()->whereIn('student_id', ['SE700001', 'SE700002'])->get() as $student) {
        materializeStudentEnrollment($student);
    }

    $studentIds = app(ExportStudentsQuery::class)
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
