<?php

declare(strict_types=1);

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Enums\StudentActionType;
use App\Models\AcademicProgressionEvent;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\AttachDecisionToTransitionAction;
use App\Modules\Academic\Progression\Queries\GetMissingDecisionReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->create([
            'student_id' => 'SE810001',
            'status' => 'intake_pre_uni_gc',
            'intake' => 1,
            'intake_semester_id' => $this->semester->id,
        ]);
    $this->decision = StudentDecision::query()->create([
        'decision_name' => 'Backfill decision',
        'decision_number' => 'QD-810-001',
        'decision_signer' => 'Academic Office',
        'issued_at' => '2026-05-01',
        'changed_by_user_id' => $this->user->id,
    ]);
});

it('backfills a decision onto an action log and adds the student to the decision roster', function () {
    $action = StudentActionLog::query()->create([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Backfill fixture',
        'changed_by_user_id' => $this->user->id,
    ]);

    expect((new GetMissingDecisionReportQuery)->handle([], $this->campus->id)->total())->toBe(1);

    AttachDecisionToTransitionAction::run([
        'student_id' => $this->student->id,
        'source' => 'action',
        'source_id' => $action->id,
        'decision_id' => $this->decision->id,
    ]);

    expect($action->fresh()->decision_id)->toBe($this->decision->id)
        ->and($this->decision->students()->whereKey($this->student->id)->exists())->toBeTrue()
        ->and((new GetMissingDecisionReportQuery)->handle([], $this->campus->id)->total())->toBe(0);
});

it('attaches a decision onto a progression event (optional — progression needs no decision)', function () {
    $event = AcademicProgressionEvent::create([
        'student_id' => $this->student->id,
        'event_type' => AcademicProgressionEventType::COURSE_STAGE_CHANGED->value,
        'semester_id' => $this->semester->id,
        'effective_at' => now(),
        'trigger_source' => ProgressionTriggerSource::MANUAL_ADMIN->value,
        'created_by_user_id' => $this->user->id,
    ]);

    // A progression event never requires a decision (ADR-0048, revised), so it
    // never appears in the missing-decision report — but a decision may still be
    // attached for the record, and that covers the student on the roster.
    expect((new GetMissingDecisionReportQuery)->handle([], $this->campus->id)->total())->toBe(0);

    AttachDecisionToTransitionAction::run([
        'student_id' => $this->student->id,
        'source' => 'progression',
        'source_id' => $event->id,
        'decision_id' => $this->decision->id,
    ]);

    expect($event->fresh()->decision_id)->toBe($this->decision->id)
        ->and($this->decision->students()->whereKey($this->student->id)->exists())->toBeTrue();
});

it('rejects attaching a transition that does not belong to the given student', function () {
    $otherStudent = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->create(['student_id' => 'SE810002', 'intake' => 1, 'intake_semester_id' => $this->semester->id]);

    $action = StudentActionLog::query()->create([
        'student_id' => $otherStudent->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Other student fixture',
        'changed_by_user_id' => $this->user->id,
    ]);

    expect(fn () => AttachDecisionToTransitionAction::run([
        'student_id' => $this->student->id,
        'source' => 'action',
        'source_id' => $action->id,
        'decision_id' => $this->decision->id,
    ]))->toThrow(InvalidArgumentException::class);
});
