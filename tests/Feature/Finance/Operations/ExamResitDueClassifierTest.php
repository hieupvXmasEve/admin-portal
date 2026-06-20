<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ExamResitAttempt;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Support\ExamResitDueClassification;
use App\Modules\Finance\Support\ExamResitDueClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->classifier = app(ExamResitDueClassifier::class);
});

function classifierStudent(object $ctx, string $status = 'intake_course', ?string $email = 'resit@example.com'): Student
{
    return Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => 'RS'.fake()->unique()->numberBetween(1000, 9999),
            'full_name' => 'Resit Student',
            'email' => $email,
            'status' => $status,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $ctx->semester->id,
        ])
        ->create();
}

it('blocks a paid attempt regardless of DNG state', function () {
    $student = classifierStudent($this);
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    $charge = createExamResitChargeFor($attempt);
    payExamResitChargeFully($charge);

    $attempt = $attempt->fresh()->load('financeCharge', 'student', 'session.roomSlot');

    $result = $this->classifier->classify($attempt, hasActivePushedDng: true);

    expect($result->reminderState)->toBe(ExamResitDueClassification::REMINDER_STATE_BLOCKED)
        ->and($result->blockedReason)->toBe(ExamResitDueClassification::BLOCKED_PAID)
        ->and($result->isRemindable())->toBeFalse();
});

it('marks an unpaid scheduled future sitting with a pushed DNG as remindable + upcoming', function () {
    $student = classifierStudent($this);
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    createExamResitChargeFor($attempt);
    $attempt = scheduleExamResitAttemptInSlot($attempt->fresh(), now()->addDays(20));

    $attempt = $attempt->load('financeCharge', 'student', 'session.roomSlot');

    $result = $this->classifier->classify($attempt, hasActivePushedDng: true);

    expect($result->reminderState)->toBe(ExamResitDueClassification::REMINDER_STATE_REMINDABLE)
        ->and($result->dueState)->toBe(ExamResitDueClassification::DUE_STATE_UPCOMING)
        ->and($result->daysOverdue)->toBeNull()
        ->and($result->isRemindable())->toBeTrue();
});

it('marks an unpaid past sitting beyond grace with a pushed DNG as remindable + overdue', function () {
    $student = classifierStudent($this);
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    createExamResitChargeFor($attempt);
    // Sat 30 days ago, default 14-day grace -> overdue ~16 days.
    $attempt = scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));

    $attempt = $attempt->load('financeCharge', 'student', 'session.roomSlot');

    $result = $this->classifier->classify($attempt, hasActivePushedDng: true);

    expect($result->reminderState)->toBe(ExamResitDueClassification::REMINDER_STATE_REMINDABLE)
        ->and($result->dueState)->toBe(ExamResitDueClassification::DUE_STATE_OVERDUE)
        ->and($result->daysOverdue)->toBeGreaterThan(0)
        ->and($result->daysOverdue)->toBe(16);
});

it('marks an overdue attempt with a charge but no pushed DNG as needs_charge_or_dng', function () {
    $student = classifierStudent($this);
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    createExamResitChargeFor($attempt);
    $attempt = scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));

    $attempt = $attempt->load('financeCharge', 'student', 'session.roomSlot');

    $result = $this->classifier->classify($attempt, hasActivePushedDng: false);

    expect($result->reminderState)->toBe(ExamResitDueClassification::REMINDER_STATE_NEEDS_CHARGE_OR_DNG)
        ->and($result->dueState)->toBe(ExamResitDueClassification::DUE_STATE_NEEDS_CHARGE_OR_DNG)
        ->and($result->daysOverdue)->toBeGreaterThan(0)
        ->and($result->isRemindable())->toBeFalse();
});

it('marks an overdue attempt with no charge at all as needs_charge_or_dng', function () {
    $student = classifierStudent($this);
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    $attempt = scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));

    $attempt = $attempt->load('financeCharge', 'student', 'session.roomSlot');

    expect($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PENDING);

    $result = $this->classifier->classify($attempt, hasActivePushedDng: false);

    expect($result->reminderState)->toBe(ExamResitDueClassification::REMINDER_STATE_NEEDS_CHARGE_OR_DNG)
        ->and($result->isRemindable())->toBeFalse();
});

it('blocks a lifecycle-exception student even with a pushed DNG', function () {
    $student = classifierStudent($this, status: 'deferred');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    createExamResitChargeFor($attempt);
    $attempt = scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));

    $attempt = $attempt->load('financeCharge', 'student', 'session.roomSlot');

    $result = $this->classifier->classify($attempt, hasActivePushedDng: true);

    expect($result->reminderState)->toBe(ExamResitDueClassification::REMINDER_STATE_BLOCKED)
        ->and($result->blockedReason)->toBe(ExamResitDueClassification::BLOCKED_LIFECYCLE_EXCEPTION);
});

it('blocks an attempt whose student has no email', function () {
    $student = classifierStudent($this, email: '');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    createExamResitChargeFor($attempt);
    $attempt = scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));

    $attempt = $attempt->load('financeCharge', 'student', 'session.roomSlot');

    $result = $this->classifier->classify($attempt, hasActivePushedDng: true);

    expect($result->reminderState)->toBe(ExamResitDueClassification::REMINDER_STATE_BLOCKED)
        ->and($result->blockedReason)->toBe(ExamResitDueClassification::BLOCKED_MISSING_EMAIL);
});
