<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\IeltsCertificate;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\Placement\InitializeStudentPlacementAction;
use App\Modules\Academic\Progression\Actions\Placement\TransitionToIntakeCourseAction;
use App\Modules\Academic\Progression\Actions\Placement\UpdateStudentEnglishLevelAction;
use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records placement and English-level changes on Program Enrollment without rewriting Student Identity state', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()
        ->for(Campus::factory())
        ->for(Program::factory())
        ->create([
            'status' => 'pending',
            'gc_starting_level' => null,
            'gc_current_level' => null,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ]);

    InitializeStudentPlacementAction::run([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'has_ielts' => false,
        'english_level' => 2,
    ]);

    UpdateStudentEnglishLevelAction::run([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'new_level' => 3,
    ]);

    $enrollment = ProgramEnrollment::query()->sole();

    expect($enrollment->enrollment_status)->toBe('active')
        ->and($enrollment->study_stage)->toBe('intake_pre_uni_gc')
        ->and($enrollment->egc_starting_level)->toBe(2)
        ->and($enrollment->egc_current_level)->toBe(3)
        ->and($student->fresh()->status)->toBe('pending')
        ->and($student->fresh()->gc_starting_level)->toBeNull()
        ->and($student->fresh()->gc_current_level)->toBeNull();
});

it('moves the course-stage transition onto Program Enrollment while preserving its decision evidence', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'status' => 'intake_pre_uni_gc',
        'gc_starting_level' => 2,
        'gc_current_level' => 4,
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);
    $certificate = IeltsCertificate::query()->create([
        'student_id' => $student->id,
        'overall_score' => 6.5,
        'submitted_at' => now(),
        'missing_documents' => false,
    ]);
    $user = User::factory()->create();

    TransitionToIntakeCourseAction::run([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'ielts_certificate_id' => $certificate->id,
        'created_by_user_id' => $user->id,
    ]);

    expect(ProgramEnrollment::query()->sole()->study_stage)->toBe('intake_course')
        ->and(ProgramEnrollment::query()->sole()->intake_major_semester_id)->toBe($semester->id)
        ->and($student->fresh()->status)->toBe('intake_pre_uni_gc')
        ->and($student->fresh()->gc_current_level)->toBe(4);
});

it('rolls back enrollment materialization when an invalid level transition is rejected', function (): void {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'status' => 'pending',
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);

    expect(fn (): mixed => UpdateStudentEnglishLevelAction::run([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'new_level' => 2,
    ]))->toThrow(InvalidProgressionState::class);

    expect(ProgramEnrollment::query()->where('student_id', $student->id)->exists())->toBeFalse();
});
