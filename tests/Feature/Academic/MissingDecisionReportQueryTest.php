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
use App\Modules\Academic\Queries\GetMissingDecisionReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();
});

function missingDecisionStudent(Campus $campus, Program $program, Semester $semester, string $code, string $status = 'intake_course'): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $code,
            'status' => $status,
            'intake' => 1,
            'intake_semester_id' => $semester->id,
        ]);
}

function missingDecisionAction(Student $student, User $user, StudentActionType $type, ?StudentDecision $decision = null): StudentActionLog
{
    return StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => $type->value,
        'reason' => 'Report fixture',
        'changed_by_user_id' => $user->id,
        'decision_id' => $decision?->id,
    ]);
}

function missingDecisionEvent(Student $student, User $user, Semester $semester, AcademicProgressionEventType $type, ?StudentDecision $decision = null): AcademicProgressionEvent
{
    return AcademicProgressionEvent::create([
        'student_id' => $student->id,
        'event_type' => $type->value,
        'semester_id' => $semester->id,
        'effective_at' => now(),
        'trigger_source' => ProgressionTriggerSource::MANUAL_ADMIN->value,
        'created_by_user_id' => $user->id,
        'decision_id' => $decision?->id,
    ]);
}

it('lists requires-decision status actions that lack a decision', function () {
    $student = missingDecisionStudent($this->campus, $this->program, $this->semester, 'SE700001');

    $defer = missingDecisionAction($student, $this->user, StudentActionType::ACADEMIC_DEFER);
    $dropout = missingDecisionAction($student, $this->user, StudentActionType::ACADEMIC_DROPOUT);
    $transfer = missingDecisionAction($student, $this->user, StudentActionType::CAMPUS_TRANSFER);
    $resume = missingDecisionAction($student, $this->user, StudentActionType::ACADEMIC_RESUME);

    $result = (new GetMissingDecisionReportQuery)->handle([], $this->campus->id);

    $keys = collect($result->items())->pluck('id')->sort()->values()->all();

    expect($keys)->toBe(collect([$defer, $dropout, $transfer, $resume])
        ->map(fn (StudentActionLog $log): string => 'action-'.$log->id)
        ->sort()
        ->values()
        ->all());
});

it('never lists progression transitions (placement / stage change no longer require a decision)', function () {
    $student = missingDecisionStudent($this->campus, $this->program, $this->semester, 'SE700002');

    // ADR-0008 (revised): EGC progression events do not require a Decision, so
    // none of them — including placement and the move into intake_course —
    // appear in the missing-decision report.
    missingDecisionEvent($student, $this->user, $this->semester, AcademicProgressionEventType::PLACEMENT_INITIALIZED);
    missingDecisionEvent($student, $this->user, $this->semester, AcademicProgressionEventType::COURSE_STAGE_CHANGED);

    expect((new GetMissingDecisionReportQuery)->handle([], $this->campus->id)->total())->toBe(0);
});

it('never lists admission deferral or pure progression records', function () {
    $student = missingDecisionStudent($this->campus, $this->program, $this->semester, 'SE700003', 'intake_pre_uni_gc');

    missingDecisionAction($student, $this->user, StudentActionType::ADMISSION_DEFERRAL);
    missingDecisionAction($student, $this->user, StudentActionType::STUDENT_ENROLLMENT_NE);
    missingDecisionAction($student, $this->user, StudentActionType::WAITING_COURSE_OPENING);
    missingDecisionEvent($student, $this->user, $this->semester, AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED);
    missingDecisionEvent($student, $this->user, $this->semester, AcademicProgressionEventType::IELTS_RECORDED);

    $result = (new GetMissingDecisionReportQuery)->handle([], $this->campus->id);

    expect($result->total())->toBe(0);
});

it('drops a transition from the report once a decision is attached', function () {
    $student = missingDecisionStudent($this->campus, $this->program, $this->semester, 'SE700004');
    $decision = StudentDecision::query()->create([
        'decision_name' => 'Dropout decision',
        'decision_number' => 'QD-700-004',
        'decision_signer' => 'Academic Office',
        'issued_at' => '2026-05-01',
        'changed_by_user_id' => $this->user->id,
    ]);

    $action = missingDecisionAction($student, $this->user, StudentActionType::ACADEMIC_DROPOUT);
    // A progression event sits alongside but is never counted (it needs no decision).
    missingDecisionEvent($student, $this->user, $this->semester, AcademicProgressionEventType::COURSE_STAGE_CHANGED);

    expect((new GetMissingDecisionReportQuery)->handle([], $this->campus->id)->total())->toBe(1);

    $action->update(['decision_id' => $decision->id]);

    expect((new GetMissingDecisionReportQuery)->handle([], $this->campus->id)->total())->toBe(0);
});

it('scopes the report to the given campus', function () {
    $currentStudent = missingDecisionStudent($this->campus, $this->program, $this->semester, 'SE700005');
    $otherStudent = missingDecisionStudent($this->otherCampus, $this->program, $this->semester, 'SE700006');

    $included = missingDecisionAction($currentStudent, $this->user, StudentActionType::ACADEMIC_DROPOUT);
    missingDecisionAction($otherStudent, $this->user, StudentActionType::ACADEMIC_DROPOUT);

    $result = (new GetMissingDecisionReportQuery)->handle([], $this->campus->id);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]['id'])->toBe('action-'.$included->id)
        ->and($result->items()[0]['student']['student_id'])->toBe('SE700005')
        ->and($result->items()[0]['student']['campus']['id'])->toBe($this->campus->id);
});

it('returns a normalized row shape with a human transition label', function () {
    $student = missingDecisionStudent($this->campus, $this->program, $this->semester, 'SE700007');
    missingDecisionAction($student, $this->user, StudentActionType::CAMPUS_TRANSFER);

    $row = (new GetMissingDecisionReportQuery)->handle([], $this->campus->id)->items()[0];

    expect($row)->toHaveKeys(['id', 'source', 'source_id', 'transition_type', 'transition_label', 'occurred_at', 'student'])
        ->and($row['source'])->toBe('action')
        ->and($row['transition_type'])->toBe(StudentActionType::CAMPUS_TRANSFER->value)
        ->and($row['transition_label'])->toBe(StudentActionType::CAMPUS_TRANSFER->labelEn())
        ->and($row['student']['full_name'])->toBe($student->full_name);
});

it('filters the report by student search term', function () {
    $matching = missingDecisionStudent($this->campus, $this->program, $this->semester, 'SE700008');
    $other = missingDecisionStudent($this->campus, $this->program, $this->semester, 'SE700009');

    missingDecisionAction($matching, $this->user, StudentActionType::ACADEMIC_DROPOUT);
    missingDecisionAction($other, $this->user, StudentActionType::ACADEMIC_DROPOUT);

    $result = (new GetMissingDecisionReportQuery)->handle(['search' => 'SE700008'], $this->campus->id);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]['student']['student_id'])->toBe('SE700008');
});
