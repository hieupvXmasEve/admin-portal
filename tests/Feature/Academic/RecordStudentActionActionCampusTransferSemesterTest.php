<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\RecordStudentActionAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A campus transfer is dated by `effective_at` rather than a semester field, so
 * without derivation it never lands on the lifecycle timeline (which reads
 * effective_semester_id). These pin the derivation the action performs.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->fromCampus = Campus::factory()->create();
    $this->toCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
});

/**
 * `SemesterSeeder` permanently seeds FALL2025 through FALL2027 as baseline
 * reference data via a migration, so every date here must sit well clear of
 * that 2025-2027 range to avoid colliding with it.
 */
function transferStudent(Campus $campus, Program $program): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'status' => 'intake_course',
            'intake' => 1,
            'intake_semester_id' => Semester::factory()->create(['start_date' => '2090-01-01', 'end_date' => '2090-05-01']),
        ]);
}

it('derives effective_semester_id from the semester containing effective_at', function () {
    $containing = Semester::factory()->create(['start_date' => '2091-01-19', 'end_date' => '2091-05-22']);
    $student = transferStudent($this->fromCampus, $this->program);

    $log = RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::CAMPUS_TRANSFER->value,
        'reason' => 'Family relocation',
        'changed_by_user_id' => $this->user->id,
        'from_campus_id' => $this->fromCampus->id,
        'to_campus_id' => $this->toCampus->id,
        'effective_at' => '2091-03-01',
    ]);

    expect($log->effective_semester_id)->toBe($containing->id);
});

it('falls back to the next semester to start when effective_at lands between terms', function () {
    Semester::factory()->create(['start_date' => '2091-09-22', 'end_date' => '2092-01-16']);
    $next = Semester::factory()->create(['start_date' => '2092-01-19', 'end_date' => '2092-05-22']);
    $student = transferStudent($this->fromCampus, $this->program);

    $log = RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::CAMPUS_TRANSFER->value,
        'reason' => 'Family relocation',
        'changed_by_user_id' => $this->user->id,
        'from_campus_id' => $this->fromCampus->id,
        'to_campus_id' => $this->toCampus->id,
        'effective_at' => '2092-01-17',
    ]);

    expect($log->effective_semester_id)->toBe($next->id);
});

it('leaves effective_semester_id null when effective_at is after every known semester', function () {
    Semester::factory()->create(['start_date' => '2091-09-22', 'end_date' => '2092-01-16']);
    $student = transferStudent($this->fromCampus, $this->program);

    $log = RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::CAMPUS_TRANSFER->value,
        'reason' => 'Family relocation',
        'changed_by_user_id' => $this->user->id,
        'from_campus_id' => $this->fromCampus->id,
        'to_campus_id' => $this->toCampus->id,
        'effective_at' => '2093-01-01',
    ]);

    expect($log->effective_semester_id)->toBeNull();
});

it('does not derive a semester for actions other than campus transfer', function () {
    Semester::factory()->create(['start_date' => '2092-01-19', 'end_date' => '2092-05-22']);
    $student = transferStudent($this->fromCampus, $this->program);

    $log = RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Left the program',
        'changed_by_user_id' => $this->user->id,
        'dropout_semester_id' => Semester::factory()->create()->id,
        'effective_at' => '2092-03-01',
    ]);

    expect($log->effective_semester_id)->toBeNull();
});

it('prefers an explicit effective_semester_id over derivation', function () {
    $explicit = Semester::factory()->create(['start_date' => '2091-09-22', 'end_date' => '2092-01-16']);
    Semester::factory()->create(['start_date' => '2092-01-19', 'end_date' => '2092-05-22']);
    $student = transferStudent($this->fromCampus, $this->program);

    $log = RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::CAMPUS_TRANSFER->value,
        'reason' => 'Family relocation',
        'changed_by_user_id' => $this->user->id,
        'from_campus_id' => $this->fromCampus->id,
        'to_campus_id' => $this->toCampus->id,
        'effective_at' => '2092-03-01',
        'effective_semester_id' => $explicit->id,
    ]);

    expect($log->effective_semester_id)->toBe($explicit->id);
});
