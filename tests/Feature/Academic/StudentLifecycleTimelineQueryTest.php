<?php

declare(strict_types=1);

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Enums\StudentActionType;
use App\Models\AcademicProgressionEvent;
use App\Models\Campus;
use App\Models\IeltsCertificate;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Models\UploadRecord;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\RecordStudentActionAction;
use App\Modules\Academic\Progression\Queries\GetStudentLifecycleTimelineQuery;
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
            'student_id' => 'SE800001',
            'status' => 'intake_pre_uni_gc',
            'intake' => 1,
            'intake_semester_id' => $this->semester->id,
            'gc_starting_level' => 1,
            'gc_current_level' => 2,
        ]);
});

function lifecycleAction(Student $student, User $user, StudentActionType $type, string $effectiveAt, ?StudentDecision $decision = null): StudentActionLog
{
    return StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => $type->value,
        'reason' => 'Timeline fixture',
        'changed_by_user_id' => $user->id,
        'effective_at' => $effectiveAt,
        'decision_id' => $decision?->id,
    ]);
}

function lifecycleEvent(Student $student, User $user, Semester $semester, AcademicProgressionEventType $type, string $effectiveAt, ?StudentDecision $decision = null): AcademicProgressionEvent
{
    return AcademicProgressionEvent::create([
        'student_id' => $student->id,
        'event_type' => $type->value,
        'semester_id' => $semester->id,
        'effective_at' => $effectiveAt,
        'trigger_source' => ProgressionTriggerSource::MANUAL_ADMIN->value,
        'created_by_user_id' => $user->id,
        'decision_id' => $decision?->id,
    ]);
}

it('merges status actions and status-changing progression events ordered by event time (newest first)', function () {
    $first = lifecycleAction($this->student, $this->user, StudentActionType::STUDENT_ENROLLMENT_NE, '2026-01-01 09:00:00');
    $second = lifecycleEvent($this->student, $this->user, $this->semester, AcademicProgressionEventType::PLACEMENT_INITIALIZED, '2026-02-01 09:00:00');
    $third = lifecycleAction($this->student, $this->user, StudentActionType::ACADEMIC_DEFER, '2026-03-01 09:00:00');

    $result = app(GetStudentLifecycleTimelineQuery::class)->handle($this->student);

    $ids = collect($result['timeline'])->pluck('id')->all();

    expect($ids)->toBe([
        'action-'.$third->id,
        'progression-'.$second->id,
        'action-'.$first->id,
    ]);
});

it('keeps english-level changes and ielts records out of the main timeline and in the egc sub-panel', function () {
    lifecycleAction($this->student, $this->user, StudentActionType::STUDENT_ENROLLMENT_NE, '2026-01-01 09:00:00');
    $levelChange = lifecycleEvent($this->student, $this->user, $this->semester, AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED, '2026-02-01 09:00:00');
    $ielts = lifecycleEvent($this->student, $this->user, $this->semester, AcademicProgressionEventType::IELTS_RECORDED, '2026-02-15 09:00:00');

    $result = app(GetStudentLifecycleTimelineQuery::class)->handle($this->student);

    $timelineIds = collect($result['timeline'])->pluck('id')->all();
    $levelHistoryIds = collect($result['egc']['history'])->pluck('id')->all();

    expect($timelineIds)->not->toContain('progression-'.$levelChange->id)
        ->and($timelineIds)->not->toContain('progression-'.$ielts->id)
        ->and($levelHistoryIds)->toContain($levelChange->id)
        ->and($levelHistoryIds)->toContain($ielts->id)
        ->and($result['egc']['current_level'])->toBe(2)
        ->and($result['egc']['starting_level'])->toBe(1);
});

it('exposes the IELTS scan file (url + original_name) in the egc panel, and null when the scan is missing', function () {
    $upload = UploadRecord::factory()->create([
        'context' => 'ielts_certificate',
        'disk' => 'ielts-certificates',
        'original_name' => 'ielts-trf.pdf',
    ]);

    $withScan = IeltsCertificate::create([
        'student_id' => $this->student->id,
        'overall_score' => 6.5,
        'submitted_at' => '2026-02-10 09:00:00',
        'issue_date' => '2026-02-10',
        'upload_record_id' => $upload->id,
        'missing_documents' => false,
    ]);

    $withoutScan = IeltsCertificate::create([
        'student_id' => $this->student->id,
        'overall_score' => 5.5,
        'submitted_at' => '2026-01-05 09:00:00',
        'issue_date' => '2026-01-05',
        'upload_record_id' => null,
        'missing_documents' => true,
    ]);

    $ielts = collect(app(GetStudentLifecycleTimelineQuery::class)->handle($this->student)['egc']['ielts'])
        ->keyBy('id');

    expect($ielts[$withScan->id]['upload_record'])->not->toBeNull()
        ->and($ielts[$withScan->id]['upload_record']['id'])->toBe($upload->id)
        ->and($ielts[$withScan->id]['upload_record']['original_name'])->toBe('ielts-trf.pdf')
        ->and($ielts[$withScan->id]['upload_record']['url'])->toBeString()
        ->and($ielts[$withScan->id]['upload_record']['url'])->not->toBe('')
        ->and($ielts[$withoutScan->id]['upload_record'])->toBeNull();
});

it('flags a requires-decision transition with no decision and clears the flag once attached', function () {
    $decision = StudentDecision::query()->create([
        'decision_name' => 'Dropout decision',
        'decision_number' => 'QD-800-001',
        'decision_signer' => 'Academic Office',
        'issued_at' => '2026-05-01',
        'changed_by_user_id' => $this->user->id,
    ]);

    $missing = lifecycleAction($this->student, $this->user, StudentActionType::ACADEMIC_DROPOUT, '2026-03-01 09:00:00');
    $authorized = lifecycleAction($this->student, $this->user, StudentActionType::CAMPUS_TRANSFER, '2026-04-01 09:00:00', $decision);
    $neverRequires = lifecycleAction($this->student, $this->user, StudentActionType::ADMISSION_DEFERRAL, '2026-02-01 09:00:00');

    $rows = collect(app(GetStudentLifecycleTimelineQuery::class)->handle($this->student)['timeline'])
        ->keyBy('id');

    expect($rows['action-'.$missing->id]['requires_decision'])->toBeTrue()
        ->and($rows['action-'.$missing->id]['missing_decision'])->toBeTrue()
        ->and($rows['action-'.$missing->id]['decision'])->toBeNull()
        ->and($rows['action-'.$authorized->id]['requires_decision'])->toBeTrue()
        ->and($rows['action-'.$authorized->id]['missing_decision'])->toBeFalse()
        ->and($rows['action-'.$authorized->id]['decision']['decision_number'])->toBe('QD-800-001')
        ->and($rows['action-'.$neverRequires->id]['requires_decision'])->toBeFalse()
        ->and($rows['action-'.$neverRequires->id]['missing_decision'])->toBeFalse();
});

it('exposes the attached Decision document (url + original_name) on the timeline entry it authorizes', function () {
    $scan = UploadRecord::factory()->create([
        'context' => 'general',
        'original_name' => 'decision-qd-900.pdf',
    ]);

    $decision = StudentDecision::query()->create([
        'decision_name' => 'Transition decision',
        'decision_number' => 'QD-900-001',
        'decision_signer' => 'Academic Office',
        'issued_at' => '2026-05-01',
        'upload_record_id' => $scan->id,
        'changed_by_user_id' => $this->user->id,
    ]);

    $withDoc = lifecycleAction($this->student, $this->user, StudentActionType::CAMPUS_TRANSFER, '2026-04-01 09:00:00', $decision);
    $noDecision = lifecycleAction($this->student, $this->user, StudentActionType::ADMISSION_DEFERRAL, '2026-02-01 09:00:00');

    $rows = collect(app(GetStudentLifecycleTimelineQuery::class)->handle($this->student)['timeline'])->keyBy('id');

    expect($rows['action-'.$withDoc->id]['decision']['upload_record'])->not->toBeNull()
        ->and($rows['action-'.$withDoc->id]['decision']['upload_record']['id'])->toBe($scan->id)
        ->and($rows['action-'.$withDoc->id]['decision']['upload_record']['original_name'])->toBe('decision-qd-900.pdf')
        ->and($rows['action-'.$withDoc->id]['decision']['upload_record']['url'])->toBeString()
        ->and($rows['action-'.$withDoc->id]['decision']['upload_record']['url'])->not->toBe('')
        ->and($rows['action-'.$noDecision->id]['decision'])->toBeNull();
});

it('never flags an EGC progression transition as missing a decision (placement / stage change need none)', function () {
    $placement = lifecycleEvent($this->student, $this->user, $this->semester, AcademicProgressionEventType::PLACEMENT_INITIALIZED, '2026-02-01 09:00:00');
    $stage = lifecycleEvent($this->student, $this->user, $this->semester, AcademicProgressionEventType::COURSE_STAGE_CHANGED, '2026-03-01 09:00:00');

    $rows = collect(app(GetStudentLifecycleTimelineQuery::class)->handle($this->student)['timeline'])
        ->keyBy('id');

    // Both still appear in the main timeline (they are status-changing), but
    // neither requires a decision, so neither is flagged "Decision missing".
    expect($rows['progression-'.$stage->id]['requires_decision'])->toBeFalse()
        ->and($rows['progression-'.$stage->id]['missing_decision'])->toBeFalse()
        ->and($rows['progression-'.$placement->id]['requires_decision'])->toBeFalse()
        ->and($rows['progression-'.$placement->id]['missing_decision'])->toBeFalse()
        ->and($rows['progression-'.$stage->id]['label'])->toBe(AcademicProgressionEventType::COURSE_STAGE_CHANGED->labelEn());
});

it('surfaces an action recorded through the write seam in the timeline, flagged as missing its decision', function () {
    // Records via the same Action the Lifecycle tab posts to (no Decision attached),
    // then reads it back through the timeline Query — the record→read round trip.
    $log = RecordStudentActionAction::run([
        'student_id' => $this->student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Left the program',
        'changed_by_user_id' => $this->user->id,
        'dropout_semester_id' => $this->semester->id,
    ]);

    $rows = collect(app(GetStudentLifecycleTimelineQuery::class)->handle($this->student->fresh())['timeline'])
        ->keyBy('id');

    expect($rows)->toHaveKey('action-'.$log->id)
        ->and($rows['action-'.$log->id]['type'])->toBe(StudentActionType::ACADEMIC_DROPOUT->value)
        ->and($rows['action-'.$log->id]['requires_decision'])->toBeTrue()
        ->and($rows['action-'.$log->id]['missing_decision'])->toBeTrue()
        ->and($rows['action-'.$log->id]['decision'])->toBeNull();
});
