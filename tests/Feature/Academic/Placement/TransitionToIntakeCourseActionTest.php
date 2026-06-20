<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\IeltsCertificate;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Academic\Actions\Placement\TransitionToIntakeCourseAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function transitionToIntakeFixture(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create([
        'code' => '2026SP',
        'name' => 'Spring 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-05-31',
    ]);
    $user = User::factory()->create();

    return compact('campus', 'program', 'semester', 'user');
}

function transitionToIntakeStudent(Campus $campus, Program $program, Semester $semester): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => 'TRN100001',
            'status' => 'intake_pre_uni_gc',
            'gc_starting_level' => 2,
            'gc_current_level' => 4,
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]);
}

it('creates a major enrollment action log when transitioning to intake course', function () {
    ['campus' => $campus, 'program' => $program, 'semester' => $semester, 'user' => $user] = transitionToIntakeFixture();
    $student = transitionToIntakeStudent($campus, $program, $semester);

    $certificate = IeltsCertificate::query()->create([
        'student_id' => $student->id,
        'overall_score' => 6.5,
        'submitted_at' => now(),
        'missing_documents' => false,
    ]);

    TransitionToIntakeCourseAction::run([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'ielts_certificate_id' => $certificate->id,
        'notes' => 'Ready for major study',
        'created_by_user_id' => $user->id,
    ]);

    $student->refresh();

    expect($student->status)->toBe('intake_course')
        ->and((int) $student->intake_major)->toBe($semester->id);

    $actionLog = StudentActionLog::query()
        ->where('student_id', $student->id)
        ->latest('id')
        ->first();

    expect($actionLog)->not->toBeNull()
        ->and($actionLog->action_type)->toBe(StudentActionType::STUDENT_MAJOR_ENROLLMENT)
        ->and($actionLog->reason)->toBe('Student officially enters major study.')
        ->and($actionLog->notes)->toBe('Ready for major study')
        ->and($actionLog->changed_by_user_id)->toBe($user->id)
        ->and($actionLog->from_semester_id)->toBe($semester->id)
        ->and($actionLog->previous_status)->toBe('intake_pre_uni_gc')
        ->and($actionLog->new_status)->toBe('intake_course')
        ->and($actionLog->missing_documents)->toBeFalse();
});
